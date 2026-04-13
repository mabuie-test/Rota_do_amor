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
            $recent = array_map(static function (array $item, int $index): array {
                $visible = $index < 2;
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
        ];
    }
}
