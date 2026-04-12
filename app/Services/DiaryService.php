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

        return (int) $this->db->lastInsertId();
    }

    public function updateEntry(int $entryId, int $userId, array $payload): bool
    {
        return $this->execute('UPDATE diary_entries SET title=:title,content=:content,mood=:mood,emotional_state=:emotional_state,relational_focus=:relational_focus,tags_json=:tags,updated_at=NOW() WHERE id=:id AND user_id=:user_id', [
            ':id' => $entryId,
            ':user_id' => $userId,
            ':title' => $payload['title'] ?? null,
            ':content' => $payload['content'],
            ':mood' => $payload['mood'] ?? null,
            ':emotional_state' => $payload['emotional_state'] ?? null,
            ':relational_focus' => $payload['relational_focus'] ?? null,
            ':tags' => json_encode($payload['tags'] ?? []),
        ]);
    }

    public function deleteEntry(int $entryId, int $userId): bool
    {
        return $this->execute('DELETE FROM diary_entries WHERE id=:id AND user_id=:user_id', [':id' => $entryId, ':user_id' => $userId]);
    }

    public function getEntry(int $entryId, int $userId): ?array
    {
        return $this->fetchOne('SELECT * FROM diary_entries WHERE id=:id AND user_id=:user_id LIMIT 1', [':id' => $entryId, ':user_id' => $userId]);
    }

    public function listEntries(int $userId, array $filters = []): array
    {
        $where = ['user_id = :user_id'];
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
        $latest = $this->fetchOne('SELECT id,title,mood,created_at FROM diary_entries WHERE user_id=:user_id ORDER BY id DESC LIMIT 1', [':user_id' => $userId]);
        $streak = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM (SELECT DATE(created_at) AS d FROM diary_entries WHERE user_id=:user_id GROUP BY DATE(created_at) ORDER BY d DESC LIMIT 7) t", [':user_id' => $userId])['c'] ?? 0);
        $monthCount = (int) ($this->fetchOne("SELECT COUNT(*) AS c FROM diary_entries WHERE user_id=:user_id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [':user_id' => $userId])['c'] ?? 0);

        return [
            'latest' => $latest,
            'recent_mood' => $latest['mood'] ?? null,
            'entries_last_30_days' => $monthCount,
            'streak_days_sample' => $streak,
        ];
    }

    public function superAdminAnalytics(): array
    {
        return [
            'total_entries' => (int) ($this->fetchOne('SELECT COUNT(*) AS c FROM diary_entries')['c'] ?? 0),
            'users_with_entries' => (int) ($this->fetchOne('SELECT COUNT(DISTINCT user_id) AS c FROM diary_entries')['c'] ?? 0),
            'entries_last_7_days' => (int) ($this->fetchOne('SELECT COUNT(*) AS c FROM diary_entries WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')['c'] ?? 0),
            'avg_entries_per_user' => round((float) ($this->fetchOne('SELECT COALESCE(AVG(cnt),0) AS v FROM (SELECT COUNT(*) AS cnt FROM diary_entries GROUP BY user_id) t')['v'] ?? 0), 2),
        ];
    }
}
