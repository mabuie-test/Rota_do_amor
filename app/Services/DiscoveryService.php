<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use DateTimeImmutable;

final class DiscoveryService extends Model
{
    public function __construct(
        private readonly CompatibilityService $compatibility = new CompatibilityService()
    ) {
        parent::__construct();
    }

    public function getSuggestedProfiles(int $userId): array
    {
        return $this->searchProfiles(['exclude_user_id' => $userId]);
    }

    public function searchProfiles(array $filters): array
    {
        $userId = (int) ($filters['exclude_user_id'] ?? 0);
        $currentUser = $this->fetchOne('SELECT * FROM users WHERE id = :id', [':id' => $userId]);
        if (!$currentUser) {
            return [];
        }

        $params = [':id' => $userId];
        $sql = "SELECT u.*, 
                       IFNULL(iv.is_verified, 0) AS is_verified,
                       IFNULL(ub.boost_active, 0) AS boost_active,
                       IFNULL(pf.has_premium, 0) AS has_premium,
                       TIMESTAMPDIFF(MINUTE, COALESCE(u.last_activity_at, u.created_at), NOW()) AS minutes_since_activity
                FROM users u
                LEFT JOIN (
                    SELECT user_id, MAX(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS is_verified
                    FROM identity_verifications
                    GROUP BY user_id
                ) iv ON iv.user_id = u.id
                LEFT JOIN (
                    SELECT user_id, MAX(CASE WHEN status='active' AND ends_at > NOW() THEN 1 ELSE 0 END) AS boost_active
                    FROM user_boosts
                    GROUP BY user_id
                ) ub ON ub.user_id = u.id
                LEFT JOIN (
                    SELECT user_id, MAX(CASE WHEN status='active' AND ends_at >= NOW() THEN 1 ELSE 0 END) AS has_premium
                    FROM premium_features
                    GROUP BY user_id
                ) pf ON pf.user_id = u.id
                WHERE u.id != :id
                  AND u.status = 'active'
                  AND NOT EXISTS (SELECT 1 FROM blocks b WHERE (b.actor_user_id=:id AND b.target_user_id=u.id) OR (b.actor_user_id=u.id AND b.target_user_id=:id))";

        if (!empty($filters['age_min']) || !empty($filters['age_max'])) {
            $ageMin = max(18, (int) ($filters['age_min'] ?? 18));
            $ageMax = min(99, (int) ($filters['age_max'] ?? 99));
            $sql .= ' AND TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) BETWEEN :age_min AND :age_max';
            $params[':age_min'] = $ageMin;
            $params[':age_max'] = max($ageMin, $ageMax);
        }

        if (!empty($filters['province_id'])) {
            $sql .= ' AND u.province_id = :province_id';
            $params[':province_id'] = (int) $filters['province_id'];
        }

        if (!empty($filters['city_id'])) {
            $sql .= ' AND u.city_id = :city_id';
            $params[':city_id'] = (int) $filters['city_id'];
        }

        if (!empty($filters['relationship_goal']) && $filters['relationship_goal'] !== 'any') {
            $sql .= ' AND u.relationship_goal = :relationship_goal';
            $params[':relationship_goal'] = (string) $filters['relationship_goal'];
        }

        if (!empty($filters['verified_only'])) {
            $sql .= ' AND IFNULL(iv.is_verified, 0) = 1';
        }

        $sql .= ' ORDER BY u.last_activity_at DESC LIMIT 200';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $profiles = $stmt->fetchAll();
        $profiles = $this->excludeSeenBlockedSuspended($profiles, $userId, $currentUser, $filters);

        return $this->rankProfilesByCompatibilityAndPriority($userId, $profiles);
    }

    public function rankProfilesByCompatibilityAndPriority(int $userId, array $profiles): array
    {
        if ($profiles === []) {
            return [];
        }

        $targetIds = array_map(static fn(array $profile): int => (int) $profile['id'], $profiles);
        $scores = $this->compatibility->getCompatibilityScoresForTargets($userId, $targetIds);
        $now = new DateTimeImmutable('now');

        foreach ($profiles as $index => &$profile) {
            $targetId = (int) $profile['id'];
            $scoreMeta = $scores[$targetId] ?? null;
            $score = (float) ($scoreMeta['score'] ?? 0);
            $calculatedAt = isset($scoreMeta['calculated_at']) ? new DateTimeImmutable((string) $scoreMeta['calculated_at']) : null;
            $isStale = $calculatedAt === null || $calculatedAt < $now->modify('-14 days');

            if (($score <= 0 || $isStale) && $index < 40) {
                $score = (float) ($this->compatibility->calculateCompatibility($userId, $targetId)['score'] ?? 0);
            }

            $profile['_compatibility'] = $score;
            $profile['_boost_weight'] = ((int) ($profile['boost_active'] ?? 0) === 1) ? 1.5 : 1.0;
            $profile['_premium_weight'] = ((int) ($profile['has_premium'] ?? 0) === 1) ? 1.2 : 1.0;
            $profile['_verification_weight'] = ((int) ($profile['is_verified'] ?? 0) === 1) ? 1.1 : 1.0;
            $minutes = (int) ($profile['minutes_since_activity'] ?? 1440);
            $activityWeight = $minutes <= 60 ? 1.15 : ($minutes <= 360 ? 1.08 : ($minutes <= 1440 ? 1.03 : 1.0));
            $profile['_activity_weight'] = $activityWeight;
            $profile['_minutes_since_activity'] = $minutes;
            $profile['_rank'] = $score
                * $profile['_boost_weight']
                * $profile['_premium_weight']
                * $profile['_verification_weight']
                * $activityWeight;
        }
        unset($profile);

        usort($profiles, static fn(array $a, array $b): int => $b['_rank'] <=> $a['_rank']);

        return $profiles;
    }

    public function excludeSeenBlockedSuspended(array $profiles, int $userId, array $currentUser = [], array $filters = []): array
    {
        $seen = $this->fetchAllRows('SELECT target_user_id FROM swipe_actions WHERE actor_user_id=:id', [':id' => $userId]);
        $seenIds = array_flip(array_map(static fn(array $row): int => (int) $row['target_user_id'], $seen));

        $pref = $this->fetchOne('SELECT * FROM user_preferences WHERE user_id = :id', [':id' => $userId]) ?: [];
        $interestedIn = (string) ($pref['interested_in'] ?? 'all');
        $goal = (string) ($pref['preferred_goal'] ?? 'any');
        $ageMin = (int) ($pref['age_min'] ?? 18);
        $ageMax = (int) ($pref['age_max'] ?? 99);

        return array_values(array_filter($profiles, static function (array $profile) use ($seenIds, $interestedIn, $goal, $ageMin, $ageMax, $filters): bool {
            $id = (int) ($profile['id'] ?? 0);
            if ($id <= 0 || isset($seenIds[$id])) {
                return false;
            }

            if (in_array((string) ($profile['status'] ?? ''), ['suspended', 'banned', 'pending_verification'], true)) {
                return false;
            }

            if ($interestedIn !== 'all' && $interestedIn !== (string) ($profile['gender'] ?? '')) {
                return false;
            }

            if ($goal !== 'any' && $goal !== (string) ($profile['relationship_goal'] ?? '')) {
                return false;
            }

            $age = isset($profile['birth_date']) ? (int) date_diff(new \DateTimeImmutable((string) $profile['birth_date']), new \DateTimeImmutable('today'))->y : 0;
            if ($age > 0 && ($age < $ageMin || $age > $ageMax)) {
                return false;
            }

            if (!empty($filters['only_online']) && !(int) ($profile['online_status'] ?? 0)) {
                return false;
            }

            return true;
        }));
    }
}
