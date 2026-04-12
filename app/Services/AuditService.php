<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class AuditService extends Model
{
    public function logAdminEvent(int $adminId, string $action, ?string $targetType = null, ?int $targetId = null, array $metadata = []): void
    {
        $this->execute(
            'INSERT INTO activity_logs (actor_type,actor_id,action,target_type,target_id,metadata_json,ip_address,created_at) VALUES (:actor_type,:actor_id,:action,:target_type,:target_id,:metadata,:ip,NOW())',
            [
                ':actor_type' => 'admin',
                ':actor_id' => $adminId > 0 ? $adminId : null,
                ':action' => $action,
                ':target_type' => $targetType,
                ':target_id' => $targetId,
                ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }

    public function listEvents(array $filters = [], int $limit = 200): array
    {
        $conditions = [];
        $params = [];

        if (($filters['actor_type'] ?? '') !== '') {
            $conditions[] = 'al.actor_type = :actor_type';
            $params[':actor_type'] = (string) $filters['actor_type'];
        }

        if (($filters['action'] ?? '') !== '') {
            $conditions[] = 'al.action = :action';
            $params[':action'] = (string) $filters['action'];
        }

        if (($filters['target_type'] ?? '') !== '') {
            $conditions[] = 'al.target_type = :target_type';
            $params[':target_type'] = (string) $filters['target_type'];
        }

        if (($filters['from'] ?? '') !== '') {
            $conditions[] = 'al.created_at >= :from';
            $params[':from'] = (string) $filters['from'] . ' 00:00:00';
        }

        if (($filters['to'] ?? '') !== '') {
            $conditions[] = 'al.created_at <= :to';
            $params[':to'] = (string) $filters['to'] . ' 23:59:59';
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $safeLimit = max(10, min($limit, 1000));

        return $this->fetchAll(
            "SELECT al.*, a.name AS admin_name
             FROM activity_logs al
             LEFT JOIN admins a ON a.id = al.actor_id AND al.actor_type='admin'
             {$where}
             ORDER BY al.id DESC
             LIMIT {$safeLimit}",
            $params
        );
    }
}
