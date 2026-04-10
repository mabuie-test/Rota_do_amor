<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class ProfileService extends Model
{
    private const MAX_GALLERY_PHOTOS = 8;

    public function getProfile(int $userId): ?array
    {
        $sql = 'SELECT u.*, p.name AS province_name, c.name AS city_name FROM users u JOIN provinces p ON p.id=u.province_id JOIN cities c ON c.id=u.city_id WHERE u.id=:id';
        return $this->fetchOne($sql, [':id' => $userId]);
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

        if ($isPrimary) {
            $this->execute('UPDATE user_photos SET is_primary = 0 WHERE user_id = :user_id', [':user_id' => $userId]);
        }

        $this->execute('INSERT INTO user_photos (user_id,image_path,is_primary,sort_order,created_at) VALUES (:user_id,:path,:is_primary,0,NOW())', [
            ':user_id' => $userId,
            ':path' => $path,
            ':is_primary' => $isPrimary ? 1 : 0,
        ]);

        if ($isPrimary) {
            $this->execute('UPDATE users SET profile_photo_path = :path WHERE id = :id', [':path' => $path, ':id' => $userId]);
        }

        return (int) $this->db->lastInsertId();
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
