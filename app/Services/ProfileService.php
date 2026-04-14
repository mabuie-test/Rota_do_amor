<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use RuntimeException;
use Throwable;

final class ProfileService extends Model
{
    private const MAX_GALLERY_PHOTOS = 8;
    private ?bool $supportsPhotoThumbnailPath = null;

    public function __construct(private readonly UploadService $uploads = new UploadService())
    {
        parent::__construct();
    }

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
        } catch (Throwable) {
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

    public function savePhoto(int $userId, string $path, bool $isPrimary = false, ?string $thumbnailPath = null): int
    {
        if (!$isPrimary) {
            $count = (int) ($this->fetchOne('SELECT COUNT(*) AS total FROM user_photos WHERE user_id = :id AND is_primary = 0', [':id' => $userId])['total'] ?? 0);
            if ($count >= self::MAX_GALLERY_PHOTOS) {
                throw new RuntimeException('Limite de fotos da galeria atingido.');
            }
        }

        $this->db->beginTransaction();
        try {
            if ($isPrimary) {
                $this->execute('UPDATE user_photos SET is_primary = 0 WHERE user_id = :user_id', [':user_id' => $userId]);
            }

            if ($this->supportsPhotoThumbnailPath()) {
                $this->execute('INSERT INTO user_photos (user_id,image_path,thumbnail_path,is_primary,sort_order,created_at) VALUES (:user_id,:path,:thumbnail_path,:is_primary,0,NOW())', [
                    ':user_id' => $userId,
                    ':path' => $path,
                    ':thumbnail_path' => $thumbnailPath !== null && $thumbnailPath !== '' ? $thumbnailPath : null,
                    ':is_primary' => $isPrimary ? 1 : 0,
                ]);
            } else {
                $this->execute('INSERT INTO user_photos (user_id,image_path,is_primary,sort_order,created_at) VALUES (:user_id,:path,:is_primary,0,NOW())', [
                    ':user_id' => $userId,
                    ':path' => $path,
                    ':is_primary' => $isPrimary ? 1 : 0,
                ]);
            }
            $photoId = (int) $this->db->lastInsertId();

            if ($isPrimary) {
                $this->execute('UPDATE users SET profile_photo_path = :path WHERE id = :id', [':path' => $path, ':id' => $userId]);
            }
            $this->db->commit();

            return $photoId;
        } catch (Throwable $exception) {
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

        $this->db->beginTransaction();
        try {
            $this->execute('UPDATE user_photos SET is_primary = 0 WHERE user_id = :user_id', [':user_id' => $userId]);
            $this->execute('UPDATE user_photos SET is_primary = 1 WHERE id = :id AND user_id = :user_id', [':id' => $photoId, ':user_id' => $userId]);
            $this->execute('UPDATE users SET profile_photo_path = :path WHERE id = :id', [':path' => $photo['image_path'], ':id' => $userId]);
            $this->db->commit();
            return true;
        } catch (Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return false;
        }
    }

    public function reorderGallery(int $userId, array $photoIds): bool
    {
        $ids = array_values(array_unique(array_map(static fn(mixed $id): int => (int) $id, $photoIds)));
        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
        if ($ids === []) {
            return false;
        }

        $bind = [':user_id' => $userId];
        $in = [];
        foreach ($ids as $idx => $id) {
            $key = ':photo_' . $idx;
            $in[] = $key;
            $bind[$key] = $id;
        }

        $rows = $this->fetchAllRows('SELECT id FROM user_photos WHERE user_id = :user_id AND is_primary = 0 AND id IN (' . implode(',', $in) . ')', $bind);
        if (count($rows) !== count($ids)) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $order = 1;
            foreach ($ids as $photoId) {
                $this->execute('UPDATE user_photos SET sort_order = :sort_order WHERE id = :id AND user_id = :user_id AND is_primary = 0', [
                    ':sort_order' => $order++,
                    ':id' => $photoId,
                    ':user_id' => $userId,
                ]);
            }
            $this->db->commit();
            return true;
        } catch (Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return false;
        }
    }

    public function deletePhoto(int $userId, int $photoId): bool
    {
        $bundle = $this->deletePhotoWithBundle($userId, $photoId);
        if ($bundle === null) {
            return false;
        }

        $this->uploads->deleteImageBundle($bundle);
        return true;
    }

    public function deletePhotoWithBundle(int $userId, int $photoId): ?array
    {
        $columns = $this->supportsPhotoThumbnailPath()
            ? 'id,image_path,thumbnail_path,is_primary'
            : 'id,image_path,is_primary';
        $photo = $this->fetchOne('SELECT ' . $columns . ' FROM user_photos WHERE id=:id AND user_id=:user_id', [':id' => $photoId, ':user_id' => $userId]);
        if (!$photo) {
            return null;
        }

        $this->db->beginTransaction();
        try {
            $this->execute('DELETE FROM user_photos WHERE id=:id AND user_id=:user_id', [':id' => $photoId, ':user_id' => $userId]);
            if ((int) ($photo['is_primary'] ?? 0) === 1) {
                $next = $this->fetchOne('SELECT id,image_path FROM user_photos WHERE user_id=:id ORDER BY sort_order ASC, created_at DESC LIMIT 1', [':id' => $userId]);
                if ($next) {
                    $this->execute('UPDATE user_photos SET is_primary = 1 WHERE id = :id AND user_id = :user_id', [':id' => (int) $next['id'], ':user_id' => $userId]);
                    $this->execute('UPDATE users SET profile_photo_path = :path WHERE id = :id', [':path' => $next['image_path'], ':id' => $userId]);
                } else {
                    $this->execute('UPDATE users SET profile_photo_path=NULL WHERE id=:id', [':id' => $userId]);
                }
            }

            $this->db->commit();

            return [
                'path' => (string) ($photo['image_path'] ?? ''),
                'thumbnail_path' => $this->resolveThumbnailPath($photo),
            ];
        } catch (Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return null;
        }
    }

    private function supportsPhotoThumbnailPath(): bool
    {
        if ($this->supportsPhotoThumbnailPath !== null) {
            return $this->supportsPhotoThumbnailPath;
        }

        try {
            $column = $this->fetchOne("SHOW COLUMNS FROM user_photos LIKE 'thumbnail_path'");
            $this->supportsPhotoThumbnailPath = $column !== null && $column !== [];
        } catch (Throwable) {
            $this->supportsPhotoThumbnailPath = false;
        }

        return $this->supportsPhotoThumbnailPath;
    }

    private function resolveThumbnailPath(array $photo): ?string
    {
        $thumbnailPath = trim((string) ($photo['thumbnail_path'] ?? ''));
        if ($thumbnailPath !== '') {
            return $thumbnailPath;
        }

        return null;
    }
}
