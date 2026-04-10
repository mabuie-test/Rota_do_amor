<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class DiscoveryService extends Model
{
    public function __construct(
        private readonly CompatibilityService $compatibility = new CompatibilityService(),
        private readonly BoostService $boost = new BoostService(),
        private readonly PremiumService $premium = new PremiumService()
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
        $sql = "SELECT u.* FROM users u
                WHERE u.id != :id
                  AND u.status = 'active'
                  AND NOT EXISTS (SELECT 1 FROM blocks b WHERE (b.actor_user_id=:id AND b.target_user_id=u.id) OR (b.actor_user_id=u.id AND b.target_user_id=:id))";

        if (!empty($filters['age_min']) || !empty($filters['age_max'])) {
            $ageMin = max(18, (int) ($filters['age_min'] ?? 18));
            $ageMax = min(99, (int) ($filters['age_max'] ?? 99));
            $sql .= " AND TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) BETWEEN :age_min AND :age_max";
            $params[':age_min'] = $ageMin;
            $params[':age_max'] = max($ageMin, $ageMax);
        }

        if (!empty($filters['province_id'])) {
            $sql .= " AND u.province_id = :province_id";
            $params[':province_id'] = (int) $filters['province_id'];
        }

        if (!empty($filters['city_id'])) {
            $sql .= " AND u.city_id = :city_id";
            $params[':city_id'] = (int) $filters['city_id'];
        }

        if (!empty($filters['relationship_goal']) && $filters['relationship_goal'] !== 'any') {
            $sql .= " AND u.relationship_goal = :relationship_goal";
            $params[':relationship_goal'] = (string) $filters['relationship_goal'];
        }

        if (!empty($filters['verified_only'])) {
            $sql .= " AND EXISTS (SELECT 1 FROM identity_verifications iv WHERE iv.user_id = u.id AND iv.status='approved')";
        }

        $sql .= " ORDER BY u.last_activity_at DESC
                LIMIT 200";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $profiles = $stmt->fetchAll();
        $profiles = $this->excludeSeenBlockedSuspended($profiles, $userId, $currentUser, $filters);
        $profiles = $this->applyBoostWeight($profiles);
        $profiles = $this->applyPremiumWeight($profiles);
        $profiles = $this->applyVerificationWeight($profiles);

        return $this->rankProfilesByCompatibilityAndPriority($userId, $profiles);
    }

    public function rankProfilesByCompatibilityAndPriority(int $userId, array $profiles): array
    {
        foreach ($profiles as &$profile) {
            $targetId = (int) $profile['id'];
            $score = $this->compatibility->getCompatibilityScore($userId, $targetId);
            if ($score <= 0) {
                $score = (float) ($this->compatibility->calculateCompatibility($userId, $targetId)['score'] ?? 0);
            }

            $profile['_compatibility'] = $score;
            $profile['_boost_weight'] = (float) ($profile['_boost_weight'] ?? 1.0);
            $profile['_premium_weight'] = (float) ($profile['_premium_weight'] ?? 1.0);
            $profile['_verification_weight'] = (float) ($profile['_verification_weight'] ?? 1.0);
            $minutes = (int) ($profile['_minutes_since_activity'] ?? 1440);
            $activityWeight = $minutes <= 60 ? 1.15 : ($minutes <= 360 ? 1.08 : ($minutes <= 1440 ? 1.03 : 1.0));
            $profile['_activity_weight'] = $activityWeight;
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

    public function applyBoostWeight(array $profiles): array
    {
        foreach ($profiles as &$profile) {
            $profile['_boost_weight'] = $this->boost->getBoostPriorityMultiplier((int) $profile['id']);
        }
        unset($profile);
        return $profiles;
    }

    public function applyPremiumWeight(array $profiles): array
    {
        foreach ($profiles as &$profile) {
            $profile['_premium_weight'] = $this->premium->userHasPremium((int) $profile['id']) ? 1.2 : 1.0;
        }
        unset($profile);
        return $profiles;
    }

    public function applyVerificationWeight(array $profiles): array
    {
        foreach ($profiles as &$profile) {
            $isVerified = (bool) $this->fetchOne("SELECT id FROM identity_verifications WHERE user_id=:id AND status='approved' LIMIT 1", [':id' => (int) $profile['id']]);
            $profile['_verification_weight'] = $isVerified ? 1.1 : 1.0;
            $profile['_minutes_since_activity'] = (int) ($this->fetchOne('SELECT TIMESTAMPDIFF(MINUTE, COALESCE(last_activity_at, created_at), NOW()) AS m FROM users WHERE id=:id', [':id' => (int) $profile['id']])['m'] ?? 9999);
        }
        unset($profile);
        return $profiles;
    }
}
