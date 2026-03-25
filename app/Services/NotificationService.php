<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class NotificationService extends Model
{
    public function create(int $userId, string $type, string $title, string $body, array $payload = []): int
    {
        $this->execute('INSERT INTO notifications (user_id,type,title,body,payload_json,is_read,created_at) VALUES (:user_id,:type,:title,:body,:payload,0,NOW())', [
            ':user_id' => $userId,
            ':type' => $type,
            ':title' => $title,
            ':body' => $body,
            ':payload' => $payload ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listForUser(int $userId): array
    {
        return $this->fetchAllRows('SELECT * FROM notifications WHERE user_id=:id ORDER BY created_at DESC LIMIT 100', [':id' => $userId]);
    }
}
