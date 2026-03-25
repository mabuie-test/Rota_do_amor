<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class BadgeService extends Model
{
    public function assignBadge(int $userId, string $badgeType, string $source, ?string $startsAt = null, ?string $endsAt = null): int
    {
        $this->execute('INSERT INTO user_badges (user_id,badge_type,source,is_active,starts_at,ends_at,created_at) VALUES (:u,:b,:s,1,:st,:en,NOW())', [
            ':u' => $userId,
            ':b' => $badgeType,
            ':s' => $source,
            ':st' => $startsAt,
            ':en' => $endsAt,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function revokeBadge(int $userId, string $badgeType): void
    {
        $this->execute('UPDATE user_badges SET is_active=0, ends_at=COALESCE(ends_at,NOW()) WHERE user_id=:u AND badge_type=:b AND is_active=1', [':u' => $userId, ':b' => $badgeType]);
    }

    public function getUserBadges(int $userId): array
    {
        return $this->fetchAllRows('SELECT * FROM user_badges WHERE user_id=:u AND is_active=1 ORDER BY created_at DESC', [':u' => $userId]);
    }

    public function syncSystemBadges(int $userId): void
    {
        $boosted = (bool) $this->fetchOne("SELECT id FROM user_boosts WHERE user_id=:u AND status='active' AND ends_at > NOW()", [':u' => $userId]);
        $premium = (bool) $this->fetchOne("SELECT id FROM premium_features WHERE user_id=:u AND status='active' AND ends_at > NOW()", [':u' => $userId]);
        $verified = (bool) $this->fetchOne("SELECT id FROM identity_verifications WHERE user_id=:u AND status='approved'", [':u' => $userId]);

        $boosted ? $this->assignIfMissing($userId, 'boosted_now', 'system') : $this->revokeBadge($userId, 'boosted_now');
        $premium ? $this->assignIfMissing($userId, 'premium', 'system') : $this->revokeBadge($userId, 'premium');
        $verified ? $this->assignIfMissing($userId, 'verified', 'system') : $this->revokeBadge($userId, 'verified');
    }

    private function assignIfMissing(int $userId, string $badgeType, string $source): void
    {
        $exists = $this->fetchOne('SELECT id FROM user_badges WHERE user_id=:u AND badge_type=:b AND is_active=1', [':u' => $userId, ':b' => $badgeType]);
        if ($exists) {
            return;
        }
        $this->assignBadge($userId, $badgeType, $source, date('Y-m-d H:i:s'));
    }
}
