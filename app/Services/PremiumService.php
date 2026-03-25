<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class PremiumService extends Model
{
    public function activatePremiumFeature(int $userId, string $featureType, string $startsAt, string $endsAt): int
    {
        $this->execute('INSERT INTO premium_features (user_id,feature_type,status,starts_at,ends_at,created_at) VALUES (:u,:f,:s,:start,:end,NOW())', [
            ':u' => $userId,
            ':f' => $featureType,
            ':s' => 'active',
            ':start' => $startsAt,
            ':end' => $endsAt,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function deactivateExpiredPremiumFeatures(): int
    {
        $stmt = $this->db->prepare("UPDATE premium_features SET status='expired' WHERE status='active' AND ends_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function userHasPremium(int $userId): bool
    {
        return (bool) $this->fetchOne("SELECT id FROM premium_features WHERE user_id=:u AND status='active' AND ends_at >= NOW()", [':u' => $userId]);
    }

    public function getPremiumBenefits(int $userId): array
    {
        if (!$this->userHasPremium($userId)) {
            return [];
        }

        return [
            'advanced_filters' => true,
            'priority_discovery' => true,
            'premium_badge' => true,
        ];
    }
}
