<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use App\Core\Request;

final class RateLimiterService extends Model
{
    public function tooManyAttempts(string $action, string $key, int $maxAttempts, int $windowMinutes, string $outcome = 'any'): bool
    {
        $sql = 'SELECT COUNT(*) AS total FROM activity_logs WHERE action = :action AND target_type = :target_type AND target_id IS NULL AND metadata_json LIKE :key';
        if ($outcome !== 'any') {
            $sql .= ' AND metadata_json LIKE :outcome';
        }
        $sql .= ' AND created_at >= DATE_SUB(NOW(), INTERVAL :window MINUTE)';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':target_type', 'rate_limit');
        $stmt->bindValue(':key', '%"key":"' . $key . '"%');
        if ($outcome !== 'any') {
            $stmt->bindValue(':outcome', '%"outcome":"' . $outcome . '"%');
        }
        $stmt->bindValue(':window', $windowMinutes, \PDO::PARAM_INT);
        $stmt->execute();

        return (int) ($stmt->fetch()['total'] ?? 0) >= $maxAttempts;
    }

    public function hit(string $action, string $key, ?int $actorId = null): void
    {
        $this->record($action, $key, 'attempt', $actorId);
    }

    public function hitSuccess(string $action, string $key, ?int $actorId = null, array $meta = []): void
    {
        $this->record($action, $key, 'success', $actorId, $meta);
    }

    public function hitFailure(string $action, string $key, ?int $actorId = null, array $meta = []): void
    {
        $this->record($action, $key, 'failed', $actorId, $meta);
    }

    private function record(string $action, string $key, string $outcome, ?int $actorId = null, array $meta = []): void
    {
        $payload = array_merge($meta, ['key' => $key, 'outcome' => $outcome]);
        $this->execute('INSERT INTO activity_logs (actor_type,actor_id,action,target_type,target_id,metadata_json,ip_address,created_at) VALUES (:actor_type,:actor_id,:action,:target_type,:target_id,:metadata,:ip,NOW())', [
            ':actor_type' => $actorId ? 'user' : 'system',
            ':actor_id' => $actorId,
            ':action' => $action,
            ':target_type' => 'rate_limit',
            ':target_id' => null,
            ':metadata' => json_encode($payload, JSON_THROW_ON_ERROR),
            ':ip' => Request::ip(),
        ]);
    }
}
