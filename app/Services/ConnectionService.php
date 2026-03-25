<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class ConnectionService extends Model
{
    public function request(int $requesterId, int $receiverId): int
    {
        $this->execute('INSERT INTO connections (requester_id,receiver_id,status,created_at,updated_at) VALUES (:r,:v,:s,NOW(),NOW()) ON DUPLICATE KEY UPDATE status=:s,updated_at=NOW()', [':r' => $requesterId, ':v' => $receiverId, ':s' => 'pending']);
        return (int) $this->db->lastInsertId();
    }

    public function accept(int $connectionId): bool
    {
        return $this->execute("UPDATE connections SET status='accepted',updated_at=NOW() WHERE id=:id", [':id' => $connectionId]);
    }
}
