<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class CompatibilityService extends Model
{
    public function calculateCompatibility(int $userId, int $targetId): array
    {
        $score = 0.0;
        $breakdown = [
            'location' => 20,
            'interests' => 20,
            'relationship_goal' => 20,
            'preferences_age' => 20,
            'profile_activity' => 20,
        ];

        foreach ($breakdown as $value) {
            $score += $value;
        }

        $score = min(100.0, $score);
        $this->db->prepare('INSERT INTO compatibility_scores (user_id,target_user_id,score,breakdown_json,calculated_at) VALUES (:user_id,:target_user_id,:score,:breakdown,NOW()) ON DUPLICATE KEY UPDATE score=VALUES(score),breakdown_json=VALUES(breakdown_json),calculated_at=NOW()')->execute([
            ':user_id' => $userId,
            ':target_user_id' => $targetId,
            ':score' => $score,
            ':breakdown' => json_encode($breakdown, JSON_THROW_ON_ERROR),
        ]);

        return ['score' => $score, 'breakdown' => $breakdown];
    }

    public function getCompatibilityScore(int $userId, int $targetId): float
    {
        $stmt = $this->db->prepare('SELECT score FROM compatibility_scores WHERE user_id=:user_id AND target_user_id=:target_user_id');
        $stmt->execute([':user_id' => $userId, ':target_user_id' => $targetId]);
        return (float) ($stmt->fetch()['score'] ?? 0.0);
    }

    public function generateBreakdown(int $userId, int $targetId): array
    {
        return $this->calculateCompatibility($userId, $targetId);
    }

    public function refreshScoresForUser(int $userId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE id != :id LIMIT 200');
        $stmt->execute([':id' => $userId]);
        foreach ($stmt->fetchAll() as $target) {
            $this->calculateCompatibility($userId, (int) $target['id']);
        }
    }
}
