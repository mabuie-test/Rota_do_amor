<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

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

        $this->notifications->create($userId, 'compatibility_duel_action_taken', 'Boa decisão no Duelo de Compatibilidade', 'A tua ação reforça teu sinal de intenção na descoberta.', [
            'duel_id' => $duelId,
            'action' => $actionType,
        ]);

        return true;
    }

    public function dashboardSummary(int $userId): array
    {
        $today = $this->getOrCreateDailyDuel($userId);
        $participation7d = (int) ($this->fetchOne("SELECT COUNT(*) c FROM compatibility_duels WHERE user_id = :user_id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status IN ('voted','engaged')", [':user_id' => $userId])['c'] ?? 0);

        return [
            'today' => $today,
            'participation_last_7d' => $participation7d,
        ];
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
}
