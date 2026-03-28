<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class AccountStateService extends Model
{
    public function getUserState(int $userId): ?array
    {
        return $this->fetchOne('SELECT id,status,email_verified_at,activation_paid_at,premium_status FROM users WHERE id=:id', [':id' => $userId]);
    }

    public function isEmailVerified(int $userId): bool
    {
        $user = $this->getUserState($userId);
        return $user !== null && $user['email_verified_at'] !== null;
    }

    public function isActivationPaid(int $userId): bool
    {
        $user = $this->getUserState($userId);
        return $user !== null && $user['activation_paid_at'] !== null;
    }

    public function hasActiveSubscription(int $userId): bool
    {
        $row = $this->fetchOne("SELECT id FROM subscriptions WHERE user_id=:u AND status='active' AND ends_at > NOW() ORDER BY ends_at DESC LIMIT 1", [':u' => $userId]);
        return $row !== null;
    }

    public function userIsIdentityVerified(int $userId): bool
    {
        return (bool) $this->fetchOne("SELECT id FROM identity_verifications WHERE user_id=:u AND status='approved' LIMIT 1", [':u' => $userId]);
    }

    public function canAccessCoreFeatures(int $userId): bool
    {
        $user = $this->getUserState($userId);
        if (!$user) {
            return false;
        }

        if (in_array($user['status'], ['suspended', 'banned'], true)) {
            return false;
        }

        return $this->isEmailVerified($userId) && $this->isActivationPaid($userId) && $this->hasActiveSubscription($userId);
    }

    public function syncUserStatus(int $userId): void
    {
        $user = $this->getUserState($userId);
        if (!$user || in_array($user['status'], ['suspended', 'banned'], true)) {
            return;
        }

        $newStatus = 'pending_activation';
        if ($this->isEmailVerified($userId) && $this->isActivationPaid($userId)) {
            $newStatus = $this->hasActiveSubscription($userId) ? 'active' : 'expired';
        }

        $this->execute('UPDATE users SET status=:status, updated_at=NOW() WHERE id=:id', [':status' => $newStatus, ':id' => $userId]);
    }
}
