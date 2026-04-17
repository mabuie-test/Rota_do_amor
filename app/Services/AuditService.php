<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use Throwable;

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
            "SELECT al.*, a.name AS admin_name, target_admin.name AS target_admin_name, u.email AS target_user_email,
                    u.first_name AS target_user_first_name, u.last_name AS target_user_last_name
             FROM activity_logs al
             LEFT JOIN admins a ON a.id = al.actor_id AND al.actor_type='admin'
             LEFT JOIN admins target_admin ON target_admin.id = al.target_id AND al.target_type='admin'
             LEFT JOIN users u ON u.id = al.target_id AND al.target_type='user'
             {$where}
             ORDER BY al.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        foreach ($items as &$item) {
            $metadata = json_decode((string) ($item['metadata_json'] ?? '{}'), true);
            $item['metadata'] = is_array($metadata) ? $metadata : [];
            $item['actor_display'] = $item['admin_name'] ?? (($item['actor_type'] ?? 'actor') . '#' . ($item['actor_id'] ?? 'n/a'));
            $item['target_display'] = $this->buildTargetDisplay($item);
            $item['source_module'] = (string) ($item['metadata']['origin'] ?? $item['metadata']['module'] ?? 'core');
            $item['reason'] = (string) ($item['metadata']['reason'] ?? '');
            $item['changed_fields'] = array_values(array_filter([
                isset($item['metadata']['from_role']) || isset($item['metadata']['to_role']) ? 'role' : null,
                isset($item['metadata']['from_status']) || isset($item['metadata']['to_status']) ? 'status' : null,
                isset($item['metadata']['key']) ? 'setting:' . (string) $item['metadata']['key'] : null,
            ]));
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) max(1, ceil($total / max(1, $perPage))),
            'actions' => $this->availableActions(),
        ];
    }

    public function availableActions(): array
    {
        $rows = $this->fetchAll('SELECT DISTINCT action FROM activity_logs ORDER BY action ASC LIMIT 200');
        return array_values(array_filter(array_map(static fn(array $r): string => (string) ($r['action'] ?? ''), $rows)));
    }

    private function insertEvent(string $actorType, ?int $actorId, string $action, ?string $targetType, ?int $targetId, array $metadata): void
    {
        try {
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
        } catch (Throwable $exception) {
            error_log('[audit.log_failed] action=' . $action . ' target_type=' . ($targetType ?? 'null') . ' target_id=' . ($targetId ?? 0) . ' error=' . $exception->getMessage());
        }
    }

    private function buildTargetDisplay(array $item): string
    {
        $targetType = (string) ($item['target_type'] ?? 'target');
        $targetId = (int) ($item['target_id'] ?? 0);

        if ($targetType === 'admin' && ($item['target_admin_name'] ?? '') !== '') {
            return 'admin#' . $targetId . ' · ' . (string) $item['target_admin_name'];
        }

        if ($targetType === 'user') {
            $name = trim((string) (($item['target_user_first_name'] ?? '') . ' ' . ($item['target_user_last_name'] ?? '')));
            if ($name !== '') {
                return 'user#' . $targetId . ' · ' . $name;
            }
            if (($item['target_user_email'] ?? '') !== '') {
                return 'user#' . $targetId . ' · ' . (string) $item['target_user_email'];
            }
        }

        return $targetType . '#' . ($targetId > 0 ? $targetId : 'n/a');
    }
}
