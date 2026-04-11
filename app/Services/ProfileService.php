<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class ProfileService extends Model
{
    private const MAX_GALLERY_PHOTOS = 8;

    public function completionSignals(int $userId): array
    {
        return $this->fetchOne("SELECT
                (SELECT COUNT(*) FROM user_photos up WHERE up.user_id = :photos_user_id) AS photos_count,
                (SELECT COUNT(*) FROM user_interests ui WHERE ui.user_id = :interests_user_id) AS interests_count,
                (SELECT COUNT(*) FROM user_preferences pr WHERE pr.user_id = :preferences_user_id) AS preferences_count,
                (SELECT COUNT(*) FROM identity_verifications iv WHERE iv.user_id = :identity_verified_user_id AND iv.status = 'approved') AS identity_verified
            ", [
                ':photos_user_id' => $userId,
                ':interests_user_id' => $userId,
                ':preferences_user_id' => $userId,
                ':identity_verified_user_id' => $userId,
            ]) ?: [];
    }

    public function getProfile(int $userId): ?array
    {
        $sql = 'SELECT u.*, p.name AS province_name, c.name AS city_name FROM users u JOIN provinces p ON p.id=u.province_id JOIN cities c ON c.id=u.city_id WHERE u.id=:id';
        return $this->fetchOne($sql, [':id' => $userId]);
    }



    public function getInterests(int $userId): array
    {
        return $this->fetchAllRows('SELECT interest_name FROM user_interests WHERE user_id = :id ORDER BY interest_name ASC', [':id' => $userId]);
    }

    public function syncInterests(int $userId, array $interests): bool
    {
        $this->db->beginTransaction();
        try {
            $this->execute('DELETE FROM user_interests WHERE user_id = :id', [':id' => $userId]);
            foreach (array_slice($interests, 0, 20) as $interest) {
                $this->execute('INSERT INTO user_interests (user_id, interest_name, created_at) VALUES (:user_id, :interest_name, NOW())', [
                    ':user_id' => $userId,
                    ':interest_name' => $interest,
                ]);
            }
            $this->db->commit();

            return true;
        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return false;
        }
    }

    public function getPreferences(int $userId): array
    {
        return $this->fetchOne('SELECT * FROM user_preferences WHERE user_id = :id LIMIT 1', [':id' => $userId]) ?: [];
    }

    public function upsertPreferences(int $userId, array $payload): bool
    {
        $interestedIn = (string) ($payload['interested_in'] ?? 'all');
        if (!in_array($interestedIn, ['male', 'female', 'all'], true)) {
            $interestedIn = 'all';
        }

        $preferredGoal = (string) ($payload['preferred_goal'] ?? 'any');
        if (!in_array($preferredGoal, ['friendship', 'dating', 'marriage', 'any'], true)) {
            $preferredGoal = 'any';
        }

        $ageMin = max(18, min(90, (int) ($payload['age_min'] ?? 18)));
        $ageMax = max($ageMin, min(99, (int) ($payload['age_max'] ?? 70)));

        $provinceId = (int) ($payload['preferred_province_id'] ?? 0);
        $cityId = (int) ($payload['preferred_city_id'] ?? 0);

        return $this->execute('INSERT INTO user_preferences (user_id, interested_in, age_min, age_max, preferred_province_id, preferred_city_id, preferred_goal, updated_at, created_at)
            VALUES (:user_id, :interested_in, :age_min, :age_max, :preferred_province_id, :preferred_city_id, :preferred_goal, NOW(), NOW())
            ON DUPLICATE KEY UPDATE interested_in = VALUES(interested_in), age_min = VALUES(age_min), age_max = VALUES(age_max), preferred_province_id = VALUES(preferred_province_id), preferred_city_id = VALUES(preferred_city_id), preferred_goal = VALUES(preferred_goal), updated_at = NOW()', [
            ':user_id' => $userId,
            ':interested_in' => $interestedIn,
            ':age_min' => $ageMin,
            ':age_max' => $ageMax,
            ':preferred_province_id' => $provinceId > 0 ? $provinceId : null,
            ':preferred_city_id' => $cityId > 0 ? $cityId : null,
            ':preferred_goal' => $preferredGoal,
        ]);
    }

    public function getUserPhotos(int $userId): array
    {
        return $this->fetchAllRows('SELECT * FROM user_photos WHERE user_id=:id ORDER BY is_primary DESC, sort_order ASC, created_at DESC', [':id' => $userId]);
    }

    public function savePhoto(int $userId, string $path, bool $isPrimary = false): int
    {
        if (!$isPrimary) {
            $count = (int) ($this->fetchOne('SELECT COUNT(*) AS total FROM user_photos WHERE user_id = :id AND is_primary = 0', [':id' => $userId])['total'] ?? 0);
            if ($count >= self::MAX_GALLERY_PHOTOS) {
                throw new \RuntimeException('Limite de fotos da galeria atingido.');
            }
        }

        $this->db->beginTransaction();
        try {
            if ($isPrimary) {
                $this->execute('UPDATE user_photos SET is_primary = 0 WHERE user_id = :user_id', [':user_id' => $userId]);
            }

            $this->execute('INSERT INTO user_photos (user_id,image_path,is_primary,sort_order,created_at) VALUES (:user_id,:path,:is_primary,0,NOW())', [
                ':user_id' => $userId,
                ':path' => $path,
                ':is_primary' => $isPrimary ? 1 : 0,
            ]);
            $photoId = (int) $this->db->lastInsertId();

            if ($isPrimary) {
                $this->execute('UPDATE users SET profile_photo_path = :path WHERE id = :id', [':path' => $path, ':id' => $userId]);
            }
            $this->db->commit();

            return $photoId;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function setPrimaryPhoto(int $userId, int $photoId): bool
    {
        $photo = $this->fetchOne('SELECT id,image_path FROM user_photos WHERE id=:id AND user_id=:user_id', [':id' => $photoId, ':user_id' => $userId]);
        if (!$photo) {
            return false;
        }

        $this->execute('UPDATE user_photos SET is_primary = 0 WHERE user_id = :user_id', [':user_id' => $userId]);
        $this->execute('UPDATE user_photos SET is_primary = 1 WHERE id = :id AND user_id = :user_id', [':id' => $photoId, ':user_id' => $userId]);
        $this->execute('UPDATE users SET profile_photo_path = :path WHERE id = :id', [':path' => $photo['image_path'], ':id' => $userId]);
        return true;
    }

    public function reorderGallery(int $userId, array $photoIds): void
    {
        $order = 1;
        foreach ($photoIds as $photoId) {
            $this->execute('UPDATE user_photos SET sort_order = :sort_order WHERE id = :id AND user_id = :user_id', [
                ':sort_order' => $order++,
                ':id' => (int) $photoId,
                ':user_id' => $userId,
            ]);
        }
    }

    public function deletePhoto(int $userId, int $photoId): bool
    {
        $photo = $this->fetchOne('SELECT id,image_path,is_primary FROM user_photos WHERE id=:id AND user_id=:user_id', [':id' => $photoId, ':user_id' => $userId]);
        if (!$photo) {
            return false;
        }

        $this->execute('DELETE FROM user_photos WHERE id=:id AND user_id=:user_id', [':id' => $photoId, ':user_id' => $userId]);
        if ((int) ($photo['is_primary'] ?? 0) === 1) {
            $next = $this->fetchOne('SELECT id,image_path FROM user_photos WHERE user_id=:id ORDER BY sort_order ASC, created_at DESC LIMIT 1', [':id' => $userId]);
            if ($next) {
                $this->setPrimaryPhoto($userId, (int) $next['id']);
            } else {
                $this->execute('UPDATE users SET profile_photo_path=NULL WHERE id=:id', [':id' => $userId]);
            }
        }

        return true;
    }
}
