<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use App\Core\Request;

final class RateLimiterService extends Model
{
    public function tooManyAttempts(string $action, string $key, int $maxAttempts, int $windowMinutes): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS total FROM activity_logs WHERE action = :action AND target_type = :target_type AND target_id IS NULL AND metadata_json LIKE :key AND created_at >= DATE_SUB(NOW(), INTERVAL :window MINUTE)');
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':target_type', 'rate_limit');
        $stmt->bindValue(':key', '%"key":"' . $key . '"%');
        $stmt->bindValue(':window', $windowMinutes, \PDO::PARAM_INT);
        $stmt->execute();

        return (int) ($stmt->fetch()['total'] ?? 0) >= $maxAttempts;
    }

    public function hit(string $action, string $key, ?int $actorId = null): void
    {
        $this->execute('INSERT INTO activity_logs (actor_type,actor_id,action,target_type,target_id,metadata_json,ip_address,created_at) VALUES (:actor_type,:actor_id,:action,:target_type,:target_id,:metadata,:ip,NOW())', [
            ':actor_type' => $actorId ? 'user' : 'system',
            ':actor_id' => $actorId,
            ':action' => $action,
            ':target_type' => 'rate_limit',
            ':target_id' => null,
            ':metadata' => json_encode(['key' => $key], JSON_THROW_ON_ERROR),
            ':ip' => Request::ip(),
        ]);
    }
}

