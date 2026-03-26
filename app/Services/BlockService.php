<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class BlockService extends Model
{
    public function block(int $actorId, int $targetId, ?string $reason = null): int
    {
        $this->execute('INSERT IGNORE INTO blocks (actor_user_id,target_user_id,reason,created_at) VALUES (:a,:t,:r,NOW())', [':a' => $actorId, ':t' => $targetId, ':r' => $reason]);
        return (int) $this->db->lastInsertId();
    }

    public function isBlocked(int $a, int $b): bool
    {
        return (bool) $this->fetchOne('SELECT id FROM blocks WHERE (actor_user_id=:a AND target_user_id=:b) OR (actor_user_id=:b AND target_user_id=:a) LIMIT 1', [':a' => $a, ':b' => $b]);
    }
}
