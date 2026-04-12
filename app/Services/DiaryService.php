<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class DiaryService extends Model
{
    public function __construct(private readonly AuditService $audit = new AuditService())
    {
        parent::__construct();
    }

    public function createEntry(int $userId, array $payload): int
    {
        $mode = $this->fetchOne('SELECT current_intention, relational_pace FROM user_connection_modes WHERE user_id=:id LIMIT 1', [':id' => $userId]) ?: [];

        $this->execute('INSERT INTO diary_entries (user_id,title,content,mood,emotional_state,relational_focus,visibility,tags_json,intention_snapshot,relational_pace_snapshot,created_at,updated_at) VALUES (:user_id,:title,:content,:mood,:emotional_state,:relational_focus,:visibility,:tags,:intention,:pace,NOW(),NOW())', [
            ':user_id' => $userId,
            ':title' => $payload['title'] ?? null,
            ':content' => $payload['content'],
            ':mood' => $payload['mood'] ?? null,
            ':emotional_state' => $payload['emotional_state'] ?? null,
            ':relational_focus' => $payload['relational_focus'] ?? null,
            ':visibility' => 'private',
            ':tags' => json_encode($payload['tags'] ?? []),
            ':intention' => $mode['current_intention'] ?? null,
            ':pace' => $mode['relational_pace'] ?? null,
        ]);

        $entryId = (int) $this->db->lastInsertId();
        $this->audit->logSystemEvent('diary_entry_created', 'diary_entry', $entryId, ['user_id' => $userId, 'visibility' => 'private']);

        return $entryId;
    }

    public function updateEntry(int $entryId, int $userId, array $payload): bool
    {
        $updated = $this->execute('UPDATE diary_entries SET title=:title,content=:content,mood=:mood,emotional_state=:emotional_state,relational_focus=:relational_focus,tags_json=:tags,updated_at=NOW() WHERE id=:id AND user_id=:user_id AND deleted_at IS NULL', [
            ':id' => $entryId,
            ':user_id' => $userId,
            ':title' => $payload['title'] ?? null,
            ':content' => $payload['content'],
            ':mood' => $payload['mood'] ?? null,
            ':emotional_state' => $payload['emotional_state'] ?? null,
            ':relational_focus' => $payload['relational_focus'] ?? null,
            ':tags' => json_encode($payload['tags'] ?? []),
        ]);

        if ($updated) {
            $this->audit->logSystemEvent('diary_entry_updated', 'diary_entry', $entryId, ['user_id' => $userId]);
        }

        return $updated;
    }

    public function deleteEntry(int $entryId, int $userId): bool
    {
        $deleted = $this->execute('UPDATE diary_entries SET deleted_at = NOW(), updated_at = NOW() WHERE id=:id AND user_id=:user_id AND deleted_at IS NULL', [':id' => $entryId, ':user_id' => $userId]);
        if ($deleted) {
            $this->audit->logSystemEvent('diary_entry_archived', 'diary_entry', $entryId, ['user_id' => $userId]);
        }

        return $deleted;
    }

    public function getEntry(int $entryId, int $userId): ?array
    {
        return $this->fetchOne('SELECT * FROM diary_entries WHERE id=:id AND user_id=:user_id AND deleted_at IS NULL LIMIT 1', [':id' => $entryId, ':user_id' => $userId]);
    }

    public function listEntries(int $userId, array $filters = []): array
    {
        $where = ['user_id = :user_id', 'deleted_at IS NULL'];
        $params = [':user_id' => $userId];

        if (($filters['mood'] ?? '') !== '') {
            $where[] = 'mood = :mood';
            $params[':mood'] = (string) $filters['mood'];
        }

        if (($filters['from'] ?? '') !== '') {
            $where[] = 'created_at >= :from';
            $params[':from'] = (string) $filters['from'] . ' 00:00:00';
        }

        if (($filters['to'] ?? '') !== '') {
            $where[] = 'created_at <= :to';
            $params[':to'] = (string) $filters['to'] . ' 23:59:59';
        }

        return $this->fetchAll('SELECT * FROM diary_entries WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 300', $params);
    }

    public function dashboardSummary(int $userId): array
    {
        $latest = $this->fetchOne('SELECT id,title,mood,created_at,intention_snapshot,relational_pace_snapshot FROM diary_entries WHERE user_id=:user_id AND deleted_at IS NULL ORDER BY id DESC LIMIT 1', [':user_id' => $userId]);
        $entriesLast7Days = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM diary_entries WHERE user_id=:user_id AND deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [':user_id' => $userId])['c'] ?? 0);
        $entriesLast30Days = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM diary_entries WHERE user_id=:user_id AND deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [':user_id' => $userId])['c'] ?? 0);
        $totalEntries = (int) ($this->fetchOne('SELECT COUNT(*) AS c FROM diary_entries WHERE user_id=:user_id AND deleted_at IS NULL', [':user_id' => $userId])['c'] ?? 0);
        $streak = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM (SELECT DATE(created_at) AS d FROM diary_entries WHERE user_id=:user_id AND deleted_at IS NULL GROUP BY DATE(created_at) ORDER BY d DESC LIMIT 14) t", [':user_id' => $userId])['c'] ?? 0);

        $moodRows = $this->fetchAll("SELECT COALESCE(NULLIF(mood, ''), 'indefinido') AS mood_label, COUNT(*) AS total
            FROM diary_entries
            WHERE user_id=:user_id AND deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY mood_label
            ORDER BY total DESC
            LIMIT 6", [':user_id' => $userId]);

        $activeDays30 = (int) ($this->fetchOne("SELECT COUNT(DISTINCT DATE(created_at)) AS c
            FROM diary_entries
            WHERE user_id=:user_id AND deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [':user_id' => $userId])['c'] ?? 0);

        $daysSinceLastEntry = isset($latest['created_at']) ? max(0, (int) floor((time() - strtotime((string) $latest['created_at'])) / 86400)) : null;

        return [
            'latest' => $latest,
            'recent_mood' => $latest['mood'] ?? null,
            'entries_last_7_days' => $entriesLast7Days,
            'entries_last_30_days' => $entriesLast30Days,
            'total_entries' => $totalEntries,
            'streak_days_sample' => $streak,
            'days_since_last_entry' => $daysSinceLastEntry,
            'mode_snapshot' => [
                'current_intention' => $latest['intention_snapshot'] ?? null,
                'relational_pace' => $latest['relational_pace_snapshot'] ?? null,
            ],
            'mood_distribution_30_days' => $moodRows,
            'emotional_consistency_signal' => min(100, (int) round(($activeDays30 / 12) * 100)),
            'cta' => $this->buildDiaryCta($totalEntries, $daysSinceLastEntry),
        ];
    }

    public function superAdminAnalytics(): array
    {
        $totalEntries = (int) ($this->fetchOne('SELECT COUNT(*) AS c FROM diary_entries WHERE deleted_at IS NULL')['c'] ?? 0);
        $usersWithEntries = (int) ($this->fetchOne('SELECT COUNT(DISTINCT user_id) AS c FROM diary_entries WHERE deleted_at IS NULL')['c'] ?? 0);

        $retentionDiaryUsers = (float) ($this->fetchOne("SELECT COALESCE(100 * AVG(CASE WHEN u.last_activity_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS v
            FROM users u
            WHERE EXISTS (
                SELECT 1 FROM diary_entries d
                WHERE d.user_id = u.id
                  AND d.deleted_at IS NULL
                  AND d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            )")['v'] ?? 0);

        $retentionNonDiaryUsers = (float) ($this->fetchOne("SELECT COALESCE(100 * AVG(CASE WHEN u.last_activity_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS v
            FROM users u
            WHERE NOT EXISTS (
                SELECT 1 FROM diary_entries d
                WHERE d.user_id = u.id
                  AND d.deleted_at IS NULL
                  AND d.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            )")['v'] ?? 0);

        return [
            'total_entries' => $totalEntries,
            'users_with_entries' => $usersWithEntries,
            'active_users_7_days' => (int) ($this->fetchOne('SELECT COUNT(DISTINCT user_id) AS c FROM diary_entries WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')['c'] ?? 0),
            'active_users_30_days' => (int) ($this->fetchOne('SELECT COUNT(DISTINCT user_id) AS c FROM diary_entries WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')['c'] ?? 0),
            'entries_last_7_days' => (int) ($this->fetchOne('SELECT COUNT(*) AS c FROM diary_entries WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')['c'] ?? 0),
            'entries_last_30_days' => (int) ($this->fetchOne('SELECT COUNT(*) AS c FROM diary_entries WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')['c'] ?? 0),
            'avg_entries_per_user' => round((float) ($this->fetchOne('SELECT COALESCE(AVG(cnt),0) AS v FROM (SELECT COUNT(*) AS cnt FROM diary_entries WHERE deleted_at IS NULL GROUP BY user_id) t')['v'] ?? 0), 2),
            'entries_per_user_30_days' => round((float) ($this->fetchOne("SELECT COALESCE(AVG(cnt),0) AS v FROM (SELECT COUNT(*) AS cnt FROM diary_entries WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY user_id) t")['v'] ?? 0), 2),
            'mood_distribution_30_days' => $this->fetchAll("SELECT COALESCE(NULLIF(mood, ''), 'indefinido') AS mood_label, COUNT(*) AS total
                FROM diary_entries
                WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY mood_label
                ORDER BY total DESC
                LIMIT 8"),
            'consistency_signal' => round((float) ($this->fetchOne("SELECT COALESCE(AVG(active_days), 0) AS v
                FROM (
                    SELECT COUNT(DISTINCT DATE(created_at)) AS active_days
                    FROM diary_entries
                    WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY user_id
                ) t")['v'] ?? 0), 2),
            'retention_diary_users_30_days' => round($retentionDiaryUsers, 2),
            'retention_non_diary_users_30_days' => round($retentionNonDiaryUsers, 2),
            'retention_lift_points' => round($retentionDiaryUsers - $retentionNonDiaryUsers, 2),
            'longitudinal_monthly' => $this->fetchAll("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS total
                FROM diary_entries
                WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY ym
                ORDER BY ym ASC"),
        ];
    }

    private function buildDiaryCta(int $totalEntries, ?int $daysSinceLastEntry): array
    {
        if ($totalEntries <= 0) {
            return [
                'type' => 'first_entry',
                'title' => 'Começa o teu Diário do Coração hoje',
                'copy' => 'Um minuto de escrita por dia ajuda-te a dar clareza ao teu momento emocional.',
                'action_label' => 'Escrever primeira entrada',
            ];
        }

        if (($daysSinceLastEntry ?? 0) >= 7) {
            return [
                'type' => 'comeback',
                'title' => 'O teu espaço íntimo está à tua espera',
                'copy' => 'Já passaram alguns dias. Retomar agora fortalece a tua consistência emocional.',
                'action_label' => 'Voltar a escrever',
            ];
        }

        return [
            'type' => 'daily',
            'title' => 'Faz o check-in emocional de hoje',
            'copy' => 'Regista como te sentes para manteres o teu ritmo relacional consciente.',
            'action_label' => 'Escrever hoje',
        ];
    }
}
