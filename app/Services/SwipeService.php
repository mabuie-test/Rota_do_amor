<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class SwipeService extends Model
{
    public function __construct(private readonly MatchService $matchService = new MatchService(), private readonly SubscriptionService $subscriptions = new SubscriptionService())
    {
        parent::__construct();
    }

    public function getNextSwipeCandidate(int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT u.* FROM users u
            WHERE u.id != :user_id
              AND u.status = 'active'
              AND NOT EXISTS (SELECT 1 FROM swipe_actions s WHERE s.actor_user_id = :user_id AND s.target_user_id = u.id)
              AND NOT EXISTS (SELECT 1 FROM blocks b WHERE (b.actor_user_id = :user_id AND b.target_user_id = u.id) OR (b.actor_user_id = u.id AND b.target_user_id = :user_id))
            ORDER BY u.last_activity_at DESC LIMIT 1");
        $stmt->execute([':user_id' => $userId]);
        $candidate = $stmt->fetch();

        return $candidate ?: null;
    }

    public function recordSwipe(int $userId, int $targetId, string $actionType): int
    {
        if (!$this->subscriptions->canUseSwipe($userId)) {
            return 0;
        }

        $limit = (int) \App\Core\Config::env('SWIPE_DAILY_LIMIT', 100);
        $countStmt = $this->db->prepare('SELECT COUNT(*) c FROM swipe_actions WHERE actor_user_id=:actor AND DATE(created_at)=CURDATE()');
        $countStmt->execute([':actor' => $userId]);
        if ((int) ($countStmt->fetch()['c'] ?? 0) >= $limit) {
            return 0;
        }

        $stmt = $this->db->prepare('INSERT INTO swipe_actions (actor_user_id,target_user_id,action_type,created_at) VALUES (:actor,:target,:action,NOW())');
        $stmt->execute([':actor' => $userId, ':target' => $targetId, ':action' => $actionType]);
        if (in_array($actionType, ['like', 'super_like'], true)) {
            $this->createMatchIfMutual($userId, $targetId);
        }

        return (int) $this->db->lastInsertId();
    }

    public function hasMutualLike(int $userId, int $targetId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) total FROM swipe_actions WHERE ((actor_user_id=:user_id AND target_user_id=:target_id) OR (actor_user_id=:target_id AND target_user_id=:user_id)) AND action_type IN ('like','super_like')");
        $stmt->execute([':user_id' => $userId, ':target_id' => $targetId]);
        return (int) ($stmt->fetch()['total'] ?? 0) >= 2;
    }

    public function createMatchIfMutual(int $userId, int $targetId): ?int
    {
        if (!$this->hasMutualLike($userId, $targetId)) {
            return null;
        }

        return $this->matchService->createMatch($userId, $targetId, 'swipe');
    }

    public function getSwipeQueue(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT u.id,u.first_name,u.last_name FROM users u
            WHERE u.id != :id
              AND u.status = 'active'
              AND NOT EXISTS (SELECT 1 FROM swipe_actions s WHERE s.actor_user_id = :id AND s.target_user_id = u.id)
            LIMIT 50");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetchAll();
    }
}
