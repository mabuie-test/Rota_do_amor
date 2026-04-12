<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class AuditService extends Model
{
    public function logAdminEvent(int $adminId, string $action, ?string $targetType = null, ?int $targetId = null, array $metadata = []): void
    {
        $this->insertEvent('admin', $adminId > 0 ? $adminId : null, $action, $targetType, $targetId, $metadata);
    }

    public function logSystemEvent(string $action, ?string $targetType = null, ?int $targetId = null, array $metadata = []): void
    {
        $this->insertEvent('system', null, $action, $targetType, $targetId, $metadata);
    }

    public function listEvents(array $filters = []): array
    {
        $conditions = [];
        $params = [];

        if (($filters['actor_type'] ?? '') !== '') {
            $conditions[] = 'al.actor_type = :actor_type';
            $params[':actor_type'] = (string) $filters['actor_type'];
        }

        if ((int) ($filters['actor_id'] ?? 0) > 0) {
            $conditions[] = 'al.actor_id = :actor_id';
            $params[':actor_id'] = (int) $filters['actor_id'];
        }

        if ((int) ($filters['admin_id'] ?? 0) > 0) {
            $conditions[] = 'al.actor_type = :admin_actor_type AND al.actor_id = :admin_id';
            $params[':admin_actor_type'] = 'admin';
            $params[':admin_id'] = (int) $filters['admin_id'];
        }

        if (($filters['action'] ?? '') !== '') {
            $conditions[] = 'al.action = :action';
            $params[':action'] = (string) $filters['action'];
        }

        if (($filters['target_type'] ?? '') !== '') {
            $conditions[] = 'al.target_type = :target_type';
            $params[':target_type'] = (string) $filters['target_type'];
        }

        if ((int) ($filters['target_id'] ?? 0) > 0) {
            $conditions[] = 'al.target_id = :target_id';
            $params[':target_id'] = (int) $filters['target_id'];
        }

        if (($filters['q'] ?? '') !== '') {
            $conditions[] = '(al.action LIKE :q OR al.target_type LIKE :q OR CAST(al.metadata_json AS CHAR) LIKE :q)';
            $params[':q'] = '%' . (string) $filters['q'] . '%';
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
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(20, min((int) ($filters['per_page'] ?? 50), 200));
        $offset = ($page - 1) * $perPage;

        $total = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM activity_logs al {$where}", $params)['c'] ?? 0);
        $items = $this->fetchAll(
            "SELECT al.*, a.name AS admin_name
             FROM activity_logs al
             LEFT JOIN admins a ON a.id = al.actor_id AND al.actor_type='admin'
             {$where}
             ORDER BY al.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) max(1, ceil($total / max(1, $perPage))),
        ];
    }

    private function insertEvent(string $actorType, ?int $actorId, string $action, ?string $targetType, ?int $targetId, array $metadata): void
    {
        $this->execute(
            'INSERT INTO activity_logs (actor_type,actor_id,action,target_type,target_id,metadata_json,ip_address,created_at) VALUES (:actor_type,:actor_id,:action,:target_type,:target_id,:metadata,:ip,NOW())',
            [
                ':actor_type' => $actorType,
                ':actor_id' => $actorId,
                ':action' => $action,
                ':target_type' => $targetType,
                ':target_id' => $targetId,
                ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
    }
}
