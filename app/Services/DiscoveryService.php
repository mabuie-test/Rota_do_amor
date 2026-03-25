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
        $sql = "SELECT u.* FROM users u
                WHERE u.id != :id
                  AND u.status = 'active'
                  AND NOT EXISTS (SELECT 1 FROM blocks b WHERE (b.actor_user_id=:id AND b.target_user_id=u.id) OR (b.actor_user_id=u.id AND b.target_user_id=:id))
                ORDER BY u.last_activity_at DESC
                LIMIT 200";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $profiles = $stmt->fetchAll();

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
            $profile['_boost_weight'] = $this->boost->getBoostPriorityMultiplier($targetId);
            $profile['_premium_weight'] = $this->premium->userHasPremium($targetId) ? 1.2 : 1.0;
            $profile['_rank'] = $score * $profile['_boost_weight'] * $profile['_premium_weight'];
        }
        unset($profile);

        usort($profiles, static fn(array $a, array $b): int => $b['_rank'] <=> $a['_rank']);

        return $profiles;
    }

    public function excludeSeenBlockedSuspended(array $profiles): array { return $profiles; }
    public function applyBoostWeight(array $profiles): array { return $profiles; }
    public function applyPremiumWeight(array $profiles): array { return $profiles; }
    public function applyVerificationWeight(array $profiles): array { return $profiles; }
}
