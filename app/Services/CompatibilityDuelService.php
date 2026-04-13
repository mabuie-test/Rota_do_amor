<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use Throwable;

final class CompatibilityDuelService extends Model
{
    public function __construct(
        private readonly DiscoveryService $discovery = new DiscoveryService(),
        private readonly NotificationService $notifications = new NotificationService(),
        private readonly AuditService $audit = new AuditService()
    ) {
        parent::__construct();
    }

    public function getOrCreateDailyDuel(int $userId): array
    {
        $duel = $this->fetchOne('SELECT * FROM compatibility_duels WHERE user_id = :user_id AND duel_date = CURDATE() LIMIT 1', [':user_id' => $userId]);
        if (!$duel) {
            $duelId = $this->createDuel($userId);
            $duel = $this->fetchOne('SELECT * FROM compatibility_duels WHERE id = :id LIMIT 1', [':id' => $duelId]);
        }

        if (!$duel) {
            return [];
        }

        $options = $this->fetchAllRows(
            'SELECT o.*, CONCAT(u.first_name, " ", u.last_name) AS candidate_name, u.profile_photo_path, u.profession, u.relationship_goal
             FROM compatibility_duel_options o
             JOIN users u ON u.id = o.candidate_user_id
             WHERE o.duel_id = :duel_id
             ORDER BY o.sort_order ASC, o.id ASC',
            [':duel_id' => (int) $duel['id']]
        );

        $duel['options'] = $options;
        return $duel;
    }

    public function vote(int $duelId, int $userId, int $selectedOptionId): bool
    {
        $duel = $this->fetchOne('SELECT * FROM compatibility_duels WHERE id = :id AND user_id = :user_id LIMIT 1', [':id' => $duelId, ':user_id' => $userId]);
        if (!$duel) {
            return false;
        }

        $option = $this->fetchOne('SELECT * FROM compatibility_duel_options WHERE id = :id AND duel_id = :duel_id LIMIT 1', [':id' => $selectedOptionId, ':duel_id' => $duelId]);
        if (!$option) {
            return false;
        }

        $this->execute('INSERT INTO compatibility_duel_choices (duel_id, user_id, selected_option_id, created_at) VALUES (:duel_id, :user_id, :selected_option_id, NOW()) ON DUPLICATE KEY UPDATE selected_option_id = VALUES(selected_option_id), created_at = NOW()', [
            ':duel_id' => $duelId,
            ':user_id' => $userId,
            ':selected_option_id' => $selectedOptionId,
        ]);

        $this->execute("UPDATE compatibility_duels SET status='voted', selected_option_id=:selected_option_id, updated_at = NOW() WHERE id = :id", [
            ':selected_option_id' => $selectedOptionId,
            ':id' => $duelId,
        ]);

        $this->audit->logSystemEvent('compatibility_duel_voted', 'compatibility_duel', $duelId, [
            'user_id' => $userId,
            'selected_option_id' => $selectedOptionId,
            'candidate_user_id' => (int) ($option['candidate_user_id'] ?? 0),
        ]);

        return true;
    }

    public function registerAction(int $duelId, int $userId, string $actionType): bool
    {
        if (!in_array($actionType, ['view_profile', 'invite', 'favorite', 'discover'], true)) {
            return false;
        }

        $duel = $this->fetchOne('SELECT selected_option_id FROM compatibility_duels WHERE id = :id AND user_id = :user_id LIMIT 1', [':id' => $duelId, ':user_id' => $userId]);
        if (!$duel || (int) ($duel['selected_option_id'] ?? 0) <= 0) {
            return false;
        }

        $this->execute('UPDATE compatibility_duels SET status = :status, updated_at = NOW() WHERE id = :id', [':status' => 'engaged', ':id' => $duelId]);
        $this->execute(
            'INSERT INTO compatibility_duel_actions (duel_id, user_id, action_type, created_at) VALUES (:duel_id, :user_id, :action_type, NOW())',
            [':duel_id' => $duelId, ':user_id' => $userId, ':action_type' => $actionType]
        );

        $this->notifications->create($userId, 'compatibility_duel_action_taken', 'Boa decisão no Duelo de Compatibilidade', 'A tua ação reforça teu sinal de intenção na descoberta.', [
            'duel_id' => $duelId,
            'action' => $actionType,
        ]);

        return true;
    }

    public function dashboardSummary(int $userId): array
    {
        try {
            $today = $this->getOrCreateDailyDuel($userId);
            $participation7d = (int) ($this->fetchOne("SELECT COUNT(*) c FROM compatibility_duels WHERE user_id = :user_id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status IN ('voted','engaged')", [':user_id' => $userId])['c'] ?? 0);

            return [
                'today' => $today,
                'participation_last_7d' => $participation7d,
            ];
        } catch (Throwable $exception) {
            error_log('[compatibility_duel.dashboard_fallback] ' . $exception->getMessage());
            return [
                'today' => [],
                'participation_last_7d' => 0,
            ];
        }
    }

    public function superAdminMetrics(int $days = 30): array
    {
        $days = max(1, min(90, $days));
        $window = sprintf('DATE_SUB(NOW(), INTERVAL %d DAY)', $days);

        return [
            'duels_generated' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM compatibility_duels WHERE created_at >= {$window}")['c'] ?? 0),
            'duels_participated' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM compatibility_duels WHERE created_at >= {$window} AND status IN ('voted','engaged')")['c'] ?? 0),
            'choices_recorded' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM compatibility_duel_choices WHERE created_at >= {$window}")['c'] ?? 0),
            'engagement_rate_percent' => round((float) ($this->fetchOne("SELECT COALESCE(100 * SUM(CASE WHEN status IN ('voted','engaged') THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0),0) c FROM compatibility_duels WHERE created_at >= {$window}")['c'] ?? 0), 2),
            'actions_total' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM compatibility_duel_actions WHERE created_at >= {$window}")['c'] ?? 0),
            'action_rate_percent' => round((float) ($this->fetchOne("SELECT COALESCE(100 * (SELECT COUNT(*) FROM compatibility_duel_actions a WHERE a.created_at >= {$window}) / NULLIF((SELECT COUNT(*) FROM compatibility_duel_choices c WHERE c.created_at >= {$window}), 0),0) c")['c'] ?? 0), 2),
            'invite_actions' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM compatibility_duel_actions WHERE action_type='invite' AND created_at >= {$window}")['c'] ?? 0),
            'profile_view_actions' => (int) ($this->fetchOne("SELECT COUNT(*) c FROM compatibility_duel_actions WHERE action_type='view_profile' AND created_at >= {$window}")['c'] ?? 0),
        ];
    }

    public function adminList(array $filters): array
    {
        $days = max(1, min(90, (int) ($filters['days'] ?? 30)));
        $conditions = ["d.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)"];
        $params = [];
        if (($filters['status'] ?? '') !== '') {
            $conditions[] = 'd.status = :status';
            $params[':status'] = (string) $filters['status'];
        }
        if (($filters['from'] ?? '') !== '') {
            $conditions[] = 'd.created_at >= :from';
            $params[':from'] = (string) $filters['from'] . ' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $conditions[] = 'd.created_at <= :to';
            $params[':to'] = (string) $filters['to'] . ' 23:59:59';
        }
        if ((int) ($filters['user_id'] ?? 0) > 0) {
            $conditions[] = 'd.user_id = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }
        if ((int) ($filters['only_with_action'] ?? 0) === 1) {
            $conditions[] = 'COALESCE(act.actions_total,0) > 0';
        }
        $where = 'WHERE ' . implode(' AND ', $conditions);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(20, min(100, (int) ($filters['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $joins = "FROM compatibility_duels d
                JOIN users u ON u.id = d.user_id
                LEFT JOIN compatibility_duel_options so ON so.id = d.selected_option_id
                LEFT JOIN (
                    SELECT duel_id, COUNT(*) actions_total, SUM(CASE WHEN action_type='invite' THEN 1 ELSE 0 END) invite_actions
                    FROM compatibility_duel_actions GROUP BY duel_id
                ) act ON act.duel_id = d.id";

        $total = (int) ($this->fetchOne("SELECT COUNT(*) c {$joins} {$where}", $params)['c'] ?? 0);
        $items = $this->fetchAllRows(
            "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) user_name,
                    so.candidate_user_id selected_candidate_user_id,
                    COALESCE(act.actions_total,0) actions_total, COALESCE(act.invite_actions,0) invite_actions,
                    EXISTS(SELECT 1 FROM subscriptions s WHERE s.user_id = d.user_id AND s.status='active' AND s.ends_at > d.created_at) AS user_is_premium
             {$joins}
             {$where}
             ORDER BY d.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        foreach ($items as &$item) {
            $item['links'] = [
                'user' => '/admin/users/' . (int) ($item['user_id'] ?? 0),
                'selected_candidate' => '/admin/users/' . (int) ($item['selected_candidate_user_id'] ?? 0),
                'audit' => '/admin/audit?target_type=compatibility_duel&target_id=' . (int) ($item['id'] ?? 0),
                'risk' => '/admin/risk',
            ];
        }

        return [
            'items' => $items,
            'filters' => $filters,
            'pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) max(1, ceil($total / $perPage))],
            'overview' => $this->superAdminMetrics($days),
            'statuses' => ['open', 'voted', 'engaged', 'expired'],
            'premium_policy' => $this->premiumPolicy(),
        ];
    }

    public function premiumPolicy(): array
    {
        return [
            'free_daily_duels' => $this->settingInt('compatibility_duel_free_daily_limit', 1),
            'premium_daily_duels' => $this->settingInt('compatibility_duel_premium_daily_limit', 3),
            'extra_duels_enabled' => $this->settingInt('compatibility_duel_extra_enabled', 1) === 1,
            'premium_insights_enabled' => $this->settingInt('compatibility_duel_premium_insights_enabled', 1) === 1,
        ];
    }

    private function createDuel(int $userId): int
    {
        $profiles = $this->discovery->searchProfiles(['exclude_user_id' => $userId]);
        $profiles = array_slice($profiles, 0, 12);
        if (count($profiles) < 2) {
            return 0;
        }

        $a = $profiles[0];
        $b = $profiles[1];

        $this->execute("INSERT INTO compatibility_duels (user_id, duel_date, status, created_at, updated_at) VALUES (:user_id, CURDATE(), 'open', NOW(), NOW())", [':user_id' => $userId]);
        $duelId = (int) $this->db->lastInsertId();

        foreach ([$a, $b] as $index => $profile) {
            $this->execute(
                'INSERT INTO compatibility_duel_options (duel_id, candidate_user_id, compatibility_score_snapshot, compatibility_breakdown_snapshot, sort_order, created_at)
                 VALUES (:duel_id, :candidate_user_id, :compatibility_score_snapshot, :compatibility_breakdown_snapshot, :sort_order, NOW())',
                [
                    ':duel_id' => $duelId,
                    ':candidate_user_id' => (int) ($profile['id'] ?? 0),
                    ':compatibility_score_snapshot' => (float) ($profile['_compatibility'] ?? 0),
                    ':compatibility_breakdown_snapshot' => json_encode([
                        'intention_alignment_percent' => (int) ($profile['_intention_alignment_percent'] ?? 0),
                        'pace_alignment_percent' => (int) ($profile['_pace_alignment_percent'] ?? 0),
                        'rank' => (float) ($profile['_rank'] ?? 0),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ':sort_order' => $index + 1,
                ]
            );
        }

        $this->audit->logSystemEvent('compatibility_duel_generated', 'compatibility_duel', $duelId, ['user_id' => $userId]);

        return $duelId;
    }

    private function settingInt(string $key, int $default): int
    {
        $row = $this->fetchOne('SELECT setting_value FROM site_settings WHERE setting_key = :key LIMIT 1', [':key' => $key]);
        return is_numeric($row['setting_value'] ?? null) ? (int) $row['setting_value'] : $default;
    }
}
