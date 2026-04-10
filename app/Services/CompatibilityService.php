<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class CompatibilityService extends Model
{
    public function calculateCompatibility(int $userId, int $targetId): array
    {
        $user = $this->fetchOne('SELECT * FROM users WHERE id=:id', [':id' => $userId]);
        $target = $this->fetchOne('SELECT * FROM users WHERE id=:id', [':id' => $targetId]);
        if (!$user || !$target) {
            return ['score' => 0.0, 'breakdown' => []];
        }

        $location = $user['city_id'] === $target['city_id'] ? 20 : ($user['province_id'] === $target['province_id'] ? 12 : 0);
        $goal = $user['relationship_goal'] === $target['relationship_goal'] ? 20 : 10;

        $interestsUser = $this->fetchAllRows('SELECT interest_name FROM user_interests WHERE user_id=:id', [':id' => $userId]);
        $interestsTarget = $this->fetchAllRows('SELECT interest_name FROM user_interests WHERE user_id=:id', [':id' => $targetId]);
        $iu = array_column($interestsUser, 'interest_name');
        $it = array_column($interestsTarget, 'interest_name');
        $common = count(array_intersect($iu, $it));
        $interestScore = min(20, $common * 5);

        $prefScore = 0;
        $pref = $this->fetchOne('SELECT * FROM user_preferences WHERE user_id=:id', [':id' => $userId]);
        if ($pref) {
            $age = (int) $this->fetchOne('SELECT TIMESTAMPDIFF(YEAR,birth_date,CURDATE()) age FROM users WHERE id=:id', [':id' => $targetId])['age'];
            if ($age >= (int) $pref['age_min'] && $age <= (int) $pref['age_max']) {
                $prefScore += 10;
            }
            if (($pref['interested_in'] === 'all') || ($pref['interested_in'] === $target['gender'])) {
                $prefScore += 10;
            }
        }

        $profileCompletion = 0;
        $profileCompletion += !empty($target['bio']) ? 5 : 0;
        $profileCompletion += !empty($target['profile_photo_path']) ? 5 : 0;
        $profileCompletion += !empty($target['profession']) ? 5 : 0;
        $profileCompletion += !empty($target['last_activity_at']) ? 5 : 0;

        $breakdown = [
            'location' => $location,
            'interests' => $interestScore,
            'relationship_goal' => $goal,
            'preferences_age' => $prefScore,
            'profile_activity' => $profileCompletion,
        ];

        $score = (float) min(100, array_sum($breakdown));
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

    public function getCompatibilityScoresForTargets(int $userId, array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
        $stmt = $this->db->prepare("SELECT target_user_id, score, calculated_at FROM compatibility_scores WHERE user_id = ? AND target_user_id IN ($placeholders)");
        $stmt->execute([$userId, ...array_values($targetIds)]);

        $scores = [];
        foreach ($stmt->fetchAll() as $row) {
            $scores[(int) $row['target_user_id']] = [
                'score' => (float) ($row['score'] ?? 0),
                'calculated_at' => (string) ($row['calculated_at'] ?? ''),
            ];
        }

        return $scores;
    }

    public function generateBreakdown(int $userId, int $targetId): array
    {
        return $this->calculateCompatibility($userId, $targetId);
    }

    public function refreshScoresForUser(int $userId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE id != :id AND status=:status LIMIT 500');
        $stmt->execute([':id' => $userId, ':status' => 'active']);
        foreach ($stmt->fetchAll() as $target) {
            $this->calculateCompatibility($userId, (int) $target['id']);
        }
    }
}
