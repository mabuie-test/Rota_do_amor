<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class DiscoveryService extends Model
{
    public function getSuggestedProfiles(int $userId): array
    {
        return $this->searchProfiles(['exclude_user_id' => $userId]);
    }

    public function searchProfiles(array $filters): array
    {
        $userId = (int) ($filters['exclude_user_id'] ?? 0);
        $stmt = $this->db->prepare("SELECT u.* FROM users u WHERE u.status='active' AND u.id != :id ORDER BY u.last_activity_at DESC LIMIT 100");
        $stmt->execute([':id' => $userId]);
        return $this->rankProfilesByCompatibilityAndPriority($stmt->fetchAll());
    }

    public function rankProfilesByCompatibilityAndPriority(array $profiles): array
    {
        return $profiles;
    }

    public function excludeSeenBlockedSuspended(array $profiles): array { return $profiles; }
    public function applyBoostWeight(array $profiles): array { return $profiles; }
    public function applyPremiumWeight(array $profiles): array { return $profiles; }
    public function applyVerificationWeight(array $profiles): array { return $profiles; }
}
