<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class ProfileVisitService extends Model
{
    public function __construct(
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly AuditService $audit = new AuditService()
    ) {
        parent::__construct();
    }

    public function registerVisit(int $visitorUserId, int $visitedUserId, string $sourceContext = 'discover'): bool
    {
        if ($visitorUserId <= 0 || $visitedUserId <= 0 || $visitorUserId === $visitedUserId) {
            return false;
        }

        $eligible = $this->fetchOne(
            "SELECT v.id
             FROM users v
             JOIN users d ON d.id = :visited_user_id
             WHERE v.id = :visitor_user_id
               AND v.status = 'active'
               AND d.status = 'active'
               AND NOT EXISTS (
                    SELECT 1 FROM blocks b
                    WHERE (b.actor_user_id = :visitor_user_id_b1 AND b.target_user_id = :visited_user_id_b1)
                       OR (b.actor_user_id = :visited_user_id_b2 AND b.target_user_id = :visitor_user_id_b2)
               )
             LIMIT 1",
            [
                ':visitor_user_id' => $visitorUserId,
                ':visited_user_id' => $visitedUserId,
                ':visitor_user_id_b1' => $visitorUserId,
                ':visited_user_id_b1' => $visitedUserId,
                ':visited_user_id_b2' => $visitedUserId,
                ':visitor_user_id_b2' => $visitorUserId,
            ]
        );

        if (!$eligible) {
            return false;
        }

        $dedup = $this->fetchOne(
            'SELECT id FROM profile_visits WHERE visitor_user_id = :visitor AND visited_user_id = :visited AND created_at >= DATE_SUB(NOW(), INTERVAL 20 MINUTE) ORDER BY id DESC LIMIT 1',
            [':visitor' => $visitorUserId, ':visited' => $visitedUserId]
        );
        if ($dedup) {
            return false;
        }

        $this->execute(
            'INSERT INTO profile_visits (visitor_user_id, visited_user_id, source_context, created_at) VALUES (:visitor_user_id, :visited_user_id, :source_context, NOW())',
            [
                ':visitor_user_id' => $visitorUserId,
                ':visited_user_id' => $visitedUserId,
                ':source_context' => mb_substr(trim($sourceContext), 0, 40) ?: 'discover',
            ]
        );

        $visitId = (int) $this->db->lastInsertId();

        $recentVisits = (int) ($this->fetchOne('SELECT COUNT(*) c FROM profile_visits WHERE visited_user_id = :id AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)', [':id' => $visitedUserId])['c'] ?? 0);
        if ($recentVisits > 0 && $recentVisits % 3 === 0) {
            $this->notifications->create(
                $visitedUserId,
                'visitors_hub_update',
                'Novas visitas ao teu perfil',
                'Mais pessoas passaram no teu perfil hoje. Abre o Radar para agir.',
                ['recent_visits_24h' => $recentVisits]
            );
        }

        $this->audit->logSystemEvent('profile_visited', 'profile_visit', $visitId, [
            'visitor_user_id' => $visitorUserId,
            'visited_user_id' => $visitedUserId,
            'source_context' => $sourceContext,
        ]);

        return true;
    }

    public function getSummaryForUser(int $userId, bool $isPremium): array
    {
        $policy = $this->premiumPolicy();
        $freeVisible = max(0, (int) ($policy['free_visible_visitors'] ?? 2));
        $total24h = (int) ($this->fetchOne('SELECT COUNT(*) c FROM profile_visits WHERE visited_user_id = :id AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)', [':id' => $userId])['c'] ?? 0);
        $unique7d = (int) ($this->fetchOne('SELECT COUNT(DISTINCT visitor_user_id) c FROM profile_visits WHERE visited_user_id = :id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)', [':id' => $userId])['c'] ?? 0);
        $repeat7d = (int) ($this->fetchOne('SELECT COUNT(*) c FROM (SELECT visitor_user_id FROM profile_visits WHERE visited_user_id = :id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY visitor_user_id HAVING COUNT(*) >= 2) t', [':id' => $userId])['c'] ?? 0);

        $recent = $this->fetchAllRows(
            "SELECT pv.id, pv.visitor_user_id, pv.source_context, pv.created_at,
                    CONCAT(u.first_name, ' ', u.last_name) visitor_name,
                    u.profile_photo_path visitor_photo,
                    (SELECT COUNT(*) FROM profile_visits p2 WHERE p2.visited_user_id = pv.visited_user_id AND p2.visitor_user_id = pv.visitor_user_id AND p2.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)) AS visits_from_same
             FROM profile_visits pv
             JOIN users u ON u.id = pv.visitor_user_id
             WHERE pv.visited_user_id = :id
             ORDER BY pv.created_at DESC
             LIMIT 40",
            [':id' => $userId]
        );

        if (!$isPremium) {
            $recent = array_map(static function (array $item, int $index) use ($freeVisible): array {
                $visible = $index < $freeVisible;
                if ($visible) {
                    $item['is_blurred'] = 0;
                    $item['visitor_name'] = explode(' ', (string) ($item['visitor_name'] ?? 'Perfil oculto'))[0] . ' •';
                    return $item;
                }

                return [
                    'id' => (int) ($item['id'] ?? 0),
                    'visitor_user_id' => 0,
                    'visitor_name' => 'Visitante oculto',
                    'visitor_photo' => null,
                    'source_context' => 'unknown',
                    'created_at' => $item['created_at'] ?? null,
                    'visits_from_same' => (int) ($item['visits_from_same'] ?? 1),
                    'is_blurred' => 1,
                ];
            }, $recent, array_keys($recent));
        }

        return [
            'total_last_24h' => $total24h,
            'unique_last_7d' => $unique7d,
            'repeat_visitors_last_7d' => $repeat7d,
            'recent' => $recent,
            'premium_locked' => !$isPremium,
        ];
    }

    public function superAdminMetrics(int $days = 30): array
    {
        $days = max(1, min(90, $days));
        $window = sprintf('DATE_SUB(NOW(), INTERVAL %d DAY)', $days);

        return [
            'visits_total' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM profile_visits WHERE created_at >= {$window}")['c'] ?? 0),
            'unique_viewers' => (int) ($this->fetchOne("SELECT COUNT(DISTINCT visitor_user_id) c FROM profile_visits WHERE created_at >= {$window}")['c'] ?? 0),
            'users_viewed' => (int) ($this->fetchOne("SELECT COUNT(DISTINCT visited_user_id) c FROM profile_visits WHERE created_at >= {$window}")['c'] ?? 0),
            'repeat_rate_percent' => round((float) ($this->fetchOne("SELECT COALESCE(100 * SUM(CASE WHEN v.total_visits >= 2 THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0),0) val FROM (SELECT visited_user_id, visitor_user_id, COUNT(*) total_visits FROM profile_visits WHERE created_at >= {$window} GROUP BY visited_user_id, visitor_user_id) v")['val'] ?? 0), 2),
            'premium_unlock_signals' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM notifications WHERE type='visitors_hub_update' AND created_at >= {$window}")['c'] ?? 0),
            'premium_generated_visits' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM profile_visits pv JOIN subscriptions s ON s.user_id = pv.visitor_user_id AND s.status='active' AND s.ends_at > pv.created_at WHERE pv.created_at >= {$window}")['c'] ?? 0),
            'suspicious_visitors' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM (SELECT visitor_user_id FROM profile_visits WHERE created_at >= {$window} GROUP BY visitor_user_id HAVING COUNT(*) >= 80 OR COUNT(DISTINCT visited_user_id) >= 60) t")['c'] ?? 0),
        ];
    }

    public function adminList(array $filters): array
    {
        $days = max(1, min(90, (int) ($filters['days'] ?? 30)));
        $conditions = ["pv.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"];
        $params = [];

        if (($filters['from'] ?? '') !== '') {
            $conditions[] = 'pv.created_at >= :from';
            $params[':from'] = (string) $filters['from'] . ' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $conditions[] = 'pv.created_at <= :to';
            $params[':to'] = (string) $filters['to'] . ' 23:59:59';
        }
        if (($filters['source_context'] ?? '') !== '') {
            $conditions[] = 'pv.source_context = :source_context';
            $params[':source_context'] = (string) $filters['source_context'];
        }
        if ((int) ($filters['visitor_user_id'] ?? 0) > 0) {
            $conditions[] = 'pv.visitor_user_id = :visitor_user_id';
            $params[':visitor_user_id'] = (int) $filters['visitor_user_id'];
        }
        if ((int) ($filters['visited_user_id'] ?? 0) > 0) {
            $conditions[] = 'pv.visited_user_id = :visited_user_id';
            $params[':visited_user_id'] = (int) $filters['visited_user_id'];
        }
        if ((int) ($filters['only_suspicious'] ?? 0) === 1) {
            $conditions[] = '(x.visitor_visits >= 50 OR x.unique_targets >= 30)';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(20, min(100, (int) ($filters['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $total = (int) ($this->fetchOne("SELECT COUNT(*) c FROM profile_visits pv JOIN (SELECT visitor_user_id, COUNT(*) visitor_visits, COUNT(DISTINCT visited_user_id) unique_targets FROM profile_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY) GROUP BY visitor_user_id) x ON x.visitor_user_id = pv.visitor_user_id {$where}", $params)['c'] ?? 0);
        $items = $this->fetchAllRows(
            "SELECT pv.*, CONCAT(vu.first_name, ' ', vu.last_name) AS visitor_name, CONCAT(tu.first_name, ' ', tu.last_name) AS visited_name,
                    x.visitor_visits, x.unique_targets,
                    EXISTS(SELECT 1 FROM subscriptions s WHERE s.user_id = pv.visitor_user_id AND s.status='active' AND s.ends_at > pv.created_at) AS visitor_is_premium
             FROM profile_visits pv
             JOIN users vu ON vu.id = pv.visitor_user_id
             JOIN users tu ON tu.id = pv.visited_user_id
             JOIN (SELECT visitor_user_id, COUNT(*) visitor_visits, COUNT(DISTINCT visited_user_id) unique_targets FROM profile_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY) GROUP BY visitor_user_id) x ON x.visitor_user_id = pv.visitor_user_id
             {$where}
             ORDER BY pv.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        foreach ($items as &$item) {
            $item['links'] = [
                'visitor' => '/admin/users/' . (int) ($item['visitor_user_id'] ?? 0),
                'visited' => '/admin/users/' . (int) ($item['visited_user_id'] ?? 0),
                'audit' => '/admin/audit?target_type=profile_visit&target_id=' . (int) ($item['id'] ?? 0),
                'risk' => '/admin/risk',
            ];
        }

        $overview = $this->superAdminMetrics($days);
        $overview['artificial_pattern_rate_percent'] = $overview['unique_viewers'] > 0
            ? round(((int) ($overview['suspicious_visitors'] ?? 0) / max(1, (int) $overview['unique_viewers'])) * 100, 2)
            : 0.0;

        return [
            'items' => $items,
            'filters' => $filters,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) max(1, ceil($total / $perPage))],
            'overview' => $overview,
            'leaders' => [
                'most_visited' => $this->fetchAllRows("SELECT visited_user_id AS user_id, CONCAT(u.first_name,' ',u.last_name) AS user_name, COUNT(*) total FROM profile_visits pv JOIN users u ON u.id = pv.visited_user_id WHERE pv.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY) GROUP BY visited_user_id ORDER BY total DESC LIMIT 10"),
                'most_visitors' => $this->fetchAllRows("SELECT visitor_user_id AS user_id, CONCAT(u.first_name,' ',u.last_name) AS user_name, COUNT(*) total FROM profile_visits pv JOIN users u ON u.id = pv.visitor_user_id WHERE pv.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY) GROUP BY visitor_user_id ORDER BY total DESC LIMIT 10"),
            ],
            'premium_policy' => $this->premiumPolicy(),
            'source_contexts' => array_values(array_filter(array_map(static fn(array $r): string => (string) ($r['source_context'] ?? ''), $this->fetchAllRows('SELECT DISTINCT source_context FROM profile_visits ORDER BY source_context ASC LIMIT 20')))),
        ];
    }

    public function premiumPolicy(): array
    {
        return [
            'free_visible_visitors' => $this->settingInt('visitors_free_visible_visitors', 2),
            'free_history_hours' => $this->settingInt('visitors_free_history_hours', 24),
            'premium_history_days' => $this->settingInt('visitors_premium_history_days', 30),
            'throttle_per_hour' => $this->settingInt('visitors_track_limit_per_hour', 120),
        ];
    }

    private function settingInt(string $key, int $default): int
    {
        $row = $this->fetchOne('SELECT setting_value FROM site_settings WHERE setting_key = :k LIMIT 1', [':k' => $key]);
        return is_numeric($row['setting_value'] ?? null) ? (int) $row['setting_value'] : $default;
    }
}
