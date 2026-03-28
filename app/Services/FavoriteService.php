<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class FavoriteService extends Model
{
    public function toggle(int $userId, int $targetId): bool
    {
        $existing = $this->fetchOne('SELECT id FROM favorites WHERE user_id=:u AND favorite_user_id=:t', [':u' => $userId, ':t' => $targetId]);
        if ($existing) {
            $this->execute('DELETE FROM favorites WHERE id=:id', [':id' => $existing['id']]);
            return false;
        }

        $this->execute('INSERT INTO favorites (user_id,favorite_user_id,created_at) VALUES (:u,:t,NOW())', [':u' => $userId, ':t' => $targetId]);
        return true;
    }
}
