<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Model;

final class SubscriptionService extends Model
{
    public function __construct(private readonly AccountStateService $accountStates = new AccountStateService())
    {
        parent::__construct();
    }

    public function activateInitialSubscription(int $userId): void
    {
        $days = (int) Config::env('SUBSCRIPTION_DURATION_DAYS', 30);
        $this->db->prepare('INSERT INTO subscriptions (user_id,status,starts_at,ends_at,created_at,updated_at) VALUES (:user_id,:status,NOW(),DATE_ADD(NOW(), INTERVAL :days DAY),NOW(),NOW())')->execute([':user_id' => $userId, ':status' => 'active', ':days' => $days]);
        $this->accountStates->syncUserStatus($userId);
    }

    public function renewSubscription(int $userId): void
    {
        $this->activateInitialSubscription($userId);
    }

    public function expireOverdueSubscriptions(): int
    {
        $stmt = $this->db->prepare("UPDATE subscriptions SET status='expired', updated_at=NOW() WHERE status='active' AND ends_at < NOW()");
        $stmt->execute();

        $users = $this->fetchAllRows("SELECT DISTINCT user_id FROM subscriptions WHERE status='expired' AND updated_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
        foreach ($users as $user) {
            $this->accountStates->syncUserStatus((int) $user['user_id']);
        }

        return $stmt->rowCount();
    }

    public function getDaysRemaining(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT DATEDIFF(MAX(ends_at), NOW()) AS days_remaining FROM subscriptions WHERE user_id = :user_id AND status = :status');
        $stmt->execute([':user_id' => $userId, ':status' => 'active']);
        return max(0, (int) ($stmt->fetch()['days_remaining'] ?? 0));
    }

    public function userHasActiveSubscription(int $userId): bool
    {
        return $this->getDaysRemaining($userId) > 0;
    }

    public function canUseSwipe(int $userId): bool
    {
        return $this->accountStates->canAccessCoreFeatures($userId) && $this->userHasActiveSubscription($userId);
    }

    public function canSendMessages(int $userId): bool
    {
        return $this->canUseSwipe($userId);
    }

    public function canSeeMatches(int $userId): bool
    {
        return $this->canUseSwipe($userId);
    }
}
