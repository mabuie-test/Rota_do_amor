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
}
