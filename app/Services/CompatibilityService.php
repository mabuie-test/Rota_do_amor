<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use DateTimeImmutable;

final class CompatibilityService extends Model
{
    public function calculateCompatibility(int $userId, int $targetId, ?array $targetUser = null): array
    {
        $pair = $this->loadPairData($userId, $targetId, $targetUser);
        if ($pair === null) {
            return ['score' => 0.0, 'breakdown' => []];
        }

        $user = $pair['user'];
        $target = $pair['target'];
        $overlap = (int) $pair['common_interests'];
        $pref = $pair['preferences'];

        $location = $user['city_id'] === $target['city_id'] ? 20 : ($user['province_id'] === $target['province_id'] ? 12 : 0);
        $goal = $user['relationship_goal'] === $target['relationship_goal'] ? 20 : 10;
        $interestScore = min(20, $overlap * 5);

        $prefScore = 0;
        if ($pref !== []) {
            $age = $this->ageFromBirthDate((string) ($target['birth_date'] ?? ''));
            if ($age >= (int) ($pref['age_min'] ?? 18) && $age <= (int) ($pref['age_max'] ?? 99)) {
                $prefScore += 10;
            }
            if (($pref['interested_in'] ?? 'all') === 'all' || ($pref['interested_in'] ?? '') === ($target['gender'] ?? null)) {
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
        $stmt = $this->db->prepare('SELECT id,birth_date,gender,city_id,province_id,relationship_goal,bio,profile_photo_path,profession,last_activity_at FROM users WHERE id != :id AND status=:status LIMIT 500');
        $stmt->execute([':id' => $userId, ':status' => 'active']);
        foreach ($stmt->fetchAll() as $target) {
            $this->calculateCompatibility($userId, (int) $target['id'], $target);
        }
    }

    private function loadPairData(int $userId, int $targetId, ?array $targetUser = null): ?array
    {
        $user = $this->fetchOne('SELECT id,city_id,province_id,relationship_goal FROM users WHERE id=:id LIMIT 1', [':id' => $userId]);
        if (!$user) {
            return null;
        }

        $target = $targetUser;
        if (!$target || (int) ($target['id'] ?? 0) !== $targetId) {
            $target = $this->fetchOne('SELECT id,birth_date,gender,city_id,province_id,relationship_goal,bio,profile_photo_path,profession,last_activity_at FROM users WHERE id=:id LIMIT 1', [':id' => $targetId]);
        }
        if (!$target) {
            return null;
        }

        $pref = $this->fetchOne('SELECT age_min,age_max,interested_in FROM user_preferences WHERE user_id=:id LIMIT 1', [':id' => $userId]) ?: [];
        $commonInterests = $this->fetchOne(
            'SELECT COUNT(*) AS common_count
             FROM user_interests ui
             INNER JOIN user_interests ti ON ti.interest_name = ui.interest_name
             WHERE ui.user_id = :user_id AND ti.user_id = :target_id',
            [':user_id' => $userId, ':target_id' => $targetId]
        );

        return [
            'user' => $user,
            'target' => $target,
            'preferences' => $pref,
            'common_interests' => (int) ($commonInterests['common_count'] ?? 0),
        ];
    }

    private function ageFromBirthDate(string $birthDate): int
    {
        if ($birthDate === '') {
            return 0;
        }

        try {
            return (int) (new DateTimeImmutable($birthDate))->diff(new DateTimeImmutable('today'))->y;
        } catch (\Exception) {
            return 0;
        }
    }
}
