<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;

final class NotificationService extends Model
{
    public function create(int $userId, string $type, string $title, string $body, array $payload = []): int
    {
        $this->execute('INSERT INTO notifications (user_id,type,title,body,payload_json,is_read,sent_at,created_at) VALUES (:user_id,:type,:title,:body,:payload,0,NOW(),NOW())', [
            ':user_id' => $userId,
            ':type' => $type,
            ':title' => $title,
            ':body' => $body,
            ':payload' => $payload ? json_encode($payload, JSON_THROW_ON_ERROR) : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function listForUser(int $userId): array
    {
        $rows = $this->fetchAllRows('SELECT * FROM notifications WHERE user_id=:id ORDER BY created_at DESC LIMIT 100', [':id' => $userId]);

        return array_map(fn(array $row): array => $this->hydrateNotification($row), $rows);
    }

    public function unreadCountForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $row = $this->fetchOne('SELECT COUNT(*) AS total FROM notifications WHERE user_id=:id AND is_read=0', [':id' => $userId]);
        return (int) ($row['total'] ?? 0);
    }

    public function markAllAsRead(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $this->db->prepare('UPDATE notifications SET is_read=1, read_at=COALESCE(read_at, NOW()) WHERE user_id=:user_id AND is_read=0');
        $stmt->execute([':user_id' => $userId]);
        return $stmt->rowCount();
    }

    public function getForUser(int $notificationId, int $userId): array
    {
        $row = $this->fetchOne('SELECT * FROM notifications WHERE id=:id AND user_id=:user_id LIMIT 1', [
            ':id' => $notificationId,
            ':user_id' => $userId,
        ]) ?: [];

        if ($row === []) {
            return [];
        }

        return $this->hydrateNotification($row);
    }

    public function markAsRead(int $notificationId, int $userId): void
    {
        $this->execute('UPDATE notifications SET is_read=1, read_at=COALESCE(read_at, NOW()) WHERE id=:id AND user_id=:user_id', [
            ':id' => $notificationId,
            ':user_id' => $userId,
        ]);
    }

    public function resolveDestinationContext(array $notification): array
    {
        $type = (string) ($notification['type'] ?? '');
        $payload = is_array($notification['payload'] ?? null) ? $notification['payload'] : [];

        $postId = (int) ($payload['post_id'] ?? 0);
        $commentId = (int) ($payload['comment_id'] ?? 0);
        $profileId = (int) ($payload['profile_id'] ?? ($payload['sender_id'] ?? ($payload['visitor_user_id'] ?? 0)));
        $conversationId = (int) ($payload['conversation_id'] ?? 0);
        $safeDateId = (int) ($payload['safe_date_id'] ?? 0);
        $storyId = (int) ($payload['story_id'] ?? 0);
        $duelId = (int) ($payload['duel_id'] ?? 0);
        $inviteId = (int) ($payload['invite_id'] ?? 0);

        $viewerId = (int) ($notification['user_id'] ?? 0);

        if ($safeDateId > 0) {
            if ($this->existsSafeDateForUser($viewerId, $safeDateId)) {
                return ['url' => '/dates/' . $safeDateId, 'is_valid' => true, 'fallback_message' => null];
            }

            return ['url' => '/dates', 'is_valid' => false, 'fallback_message' => 'O Encontro Seguro desta notificação já não está disponível para ti.'];
        }

        if ($postId > 0) {
            if ($this->existsPost($postId)) {
                if ($commentId > 0 && !$this->existsPostComment($postId, $commentId)) {
                    return ['url' => '/feed?post=' . $postId . '#post-' . $postId, 'is_valid' => false, 'fallback_message' => 'O comentário indicado já não está disponível.'];
                }

                return ['url' => $this->buildFeedPostUrl($postId, $commentId), 'is_valid' => true, 'fallback_message' => null];
            }

            return ['url' => '/feed', 'is_valid' => false, 'fallback_message' => 'A publicação desta notificação já não está disponível.'];
        }

        if ($conversationId > 0) {
            if ($this->existsConversationForUser((int) ($notification['user_id'] ?? 0), $conversationId)) {
                return ['url' => '/messages?conversation=' . $conversationId, 'is_valid' => true, 'fallback_message' => null];
            }

            return ['url' => '/messages', 'is_valid' => false, 'fallback_message' => 'A conversa desta notificação já não está disponível para ti.'];
        }

        if ($storyId > 0) {
            if ($this->existsStory($storyId)) {
                return ['url' => '/stories/anonymous?story=' . $storyId . '#story-' . $storyId, 'is_valid' => true, 'fallback_message' => null];
            }

            return ['url' => '/stories/anonymous', 'is_valid' => false, 'fallback_message' => 'A história associada já não está acessível.'];
        }

        if ($duelId > 0) {
            if ($this->existsDuelForOwner((int) ($notification['user_id'] ?? 0), $duelId)) {
                return ['url' => '/compatibility-duel?duel=' . $duelId, 'is_valid' => true, 'fallback_message' => null];
            }

            return ['url' => '/compatibility-duel', 'is_valid' => false, 'fallback_message' => 'Este duelo não está mais disponível no teu contexto atual.'];
        }

        if ($inviteId > 0 && in_array($type, ['invite_accepted', 'invite_declined'], true)) {
            if ($this->existsSentInviteForUser((int) ($notification['user_id'] ?? 0), $inviteId)) {
                return ['url' => '/invites/sent?invite=' . $inviteId, 'is_valid' => true, 'fallback_message' => null];
            }

            return ['url' => '/invites/sent', 'is_valid' => false, 'fallback_message' => 'O convite associado já não está disponível.'];
        }

        if ($inviteId > 0 && in_array($type, ['invite_received', 'invite_priority_received'], true)) {
            if ($this->existsReceivedInviteForUser((int) ($notification['user_id'] ?? 0), $inviteId)) {
                return ['url' => '/invites/received?invite=' . $inviteId, 'is_valid' => true, 'fallback_message' => null];
            }

            return ['url' => '/invites/received', 'is_valid' => false, 'fallback_message' => 'O convite recebido já não está disponível.'];
        }

        if ($profileId > 0 && in_array($type, ['profile_view', 'visitor_profile', 'profile_visit'], true)) {
            if ($this->isProfileAccessible((int) ($notification['user_id'] ?? 0), $profileId)) {
                return ['url' => '/member/' . $profileId, 'is_valid' => true, 'fallback_message' => null];
            }

            return ['url' => '/discover', 'is_valid' => false, 'fallback_message' => 'Este perfil já não pode ser visualizado.'];
        }

        $map = [
            'invite_received' => '/invites/received',
            'invite_priority_received' => '/invites/received',
            'invite_accepted' => '/invites/sent',
            'invite_declined' => '/invites/sent',
            'new_message' => '/messages',
            'message_received' => '/messages',
            'feed_like_received' => '/feed',
            'feed_comment_received' => '/feed',
            'visitors_hub_update' => '/visitors',
            'visitor_profile' => '/visitors',
            'profile_visit' => '/visitors',
            'compatibility_duel_action_taken' => '/compatibility-duel',
            'daily_route_ready' => '/daily-route',
            'daily_route_almost_done' => '/daily-route',
            'daily_route_completed' => '/daily-route',
        ];

        if (str_starts_with($type, 'daily_route_')) {
            $dailyRouteId = (int) ($payload['daily_route_id'] ?? 0);
            if ($dailyRouteId <= 0 || $this->existsDailyRouteForUser((int) ($notification['user_id'] ?? 0), $dailyRouteId)) {
                return ['url' => '/daily-route', 'is_valid' => true, 'fallback_message' => null];
            }

            return ['url' => '/daily-route', 'is_valid' => false, 'fallback_message' => 'A rota diária associada já não está disponível.'];
        }

        if (str_starts_with($type, 'safe_date_') || str_starts_with($type, 'safe_date_reminder_')) {
            return ['url' => '/dates', 'is_valid' => true, 'fallback_message' => null];
        }

        if (str_starts_with($type, 'anonymous_story_') || str_starts_with($type, 'story_')) {
            return ['url' => '/stories/anonymous', 'is_valid' => true, 'fallback_message' => null];
        }

        return ['url' => $map[$type] ?? '/notifications', 'is_valid' => true, 'fallback_message' => null];
    }

    public function resolveDestination(array $notification): string
    {
        return (string) ($this->resolveDestinationContext($notification)['url'] ?? '/notifications');
    }

    private function buildFeedPostUrl(int $postId, int $commentId = 0): string
    {
        $base = '/feed?post=' . $postId;
        if ($commentId > 0) {
            return $base . '&comment=' . $commentId . '#post-' . $postId;
        }

        return $base . '#post-' . $postId;
    }

    private function existsPost(int $postId): bool
    {
        return $this->fetchOne('SELECT id FROM posts WHERE id=:id AND status=:status LIMIT 1', [':id' => $postId, ':status' => 'active']) !== null;
    }

    private function existsPostComment(int $postId, int $commentId): bool
    {
        return $this->fetchOne('SELECT id FROM post_comments WHERE id=:id AND post_id=:post_id LIMIT 1', [':id' => $commentId, ':post_id' => $postId]) !== null;
    }

    private function existsStory(int $storyId): bool
    {
        return $this->fetchOne("SELECT id FROM anonymous_stories WHERE id=:id AND status IN ('published','featured') LIMIT 1", [':id' => $storyId]) !== null;
    }

    private function existsDuelForOwner(int $userId, int $duelId): bool
    {
        return $this->fetchOne('SELECT id FROM compatibility_duels WHERE id=:id AND user_id=:user_id LIMIT 1', [':id' => $duelId, ':user_id' => $userId]) !== null;
    }

    private function existsSentInviteForUser(int $userId, int $inviteId): bool
    {
        return $this->fetchOne('SELECT id FROM connection_invites WHERE id=:id AND sender_user_id=:user_id LIMIT 1', [':id' => $inviteId, ':user_id' => $userId]) !== null;
    }

    private function existsReceivedInviteForUser(int $userId, int $inviteId): bool
    {
        return $this->fetchOne('SELECT id FROM connection_invites WHERE id=:id AND receiver_user_id=:user_id LIMIT 1', [':id' => $inviteId, ':user_id' => $userId]) !== null;
    }

    private function existsConversationForUser(int $userId, int $conversationId): bool
    {
        return $this->fetchOne('SELECT id FROM conversations WHERE id=:id AND (user_one_id=:user_id_1 OR user_two_id=:user_id_2) LIMIT 1', [
            ':id' => $conversationId,
            ':user_id_1' => $userId,
            ':user_id_2' => $userId,
        ]) !== null;
    }

    private function existsDailyRouteForUser(int $userId, int $routeId): bool
    {
        return $this->fetchOne('SELECT id FROM daily_routes WHERE id=:id AND user_id=:user_id LIMIT 1', [':id' => $routeId, ':user_id' => $userId]) !== null;
    }

    private function existsSafeDateForUser(int $userId, int $safeDateId): bool
    {
        if ($userId <= 0 || $safeDateId <= 0) {
            return false;
        }

        return $this->fetchOne(
            'SELECT id FROM safe_dates WHERE id=:id AND (initiator_user_id=:user_id_initiator OR invitee_user_id=:user_id_invitee) LIMIT 1',
            [':id' => $safeDateId, ':user_id_initiator' => $userId, ':user_id_invitee' => $userId]
        ) !== null;
    }

    private function isProfileAccessible(int $viewerId, int $targetId): bool
    {
        if ($viewerId <= 0 || $targetId <= 0 || $viewerId === $targetId) {
            return false;
        }

        return $this->fetchOne(
            "SELECT u.id FROM users u
             WHERE u.id=:target_id
               AND u.status='active'
               AND NOT EXISTS (
                    SELECT 1 FROM blocks b
                    WHERE (b.actor_user_id=:viewer_1 AND b.target_user_id=u.id)
                       OR (b.actor_user_id=u.id AND b.target_user_id=:viewer_2)
               )
             LIMIT 1",
            [':target_id' => $targetId, ':viewer_1' => $viewerId, ':viewer_2' => $viewerId]
        ) !== null;
    }

    private function hydrateNotification(array $row): array
    {
        $payload = [];
        $rawPayload = $row['payload_json'] ?? null;
        if (is_string($rawPayload) && $rawPayload !== '') {
            $decoded = json_decode($rawPayload, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $row['payload'] = $payload;
        $destination = $this->resolveDestinationContext($row);
        $row['destination_url'] = $destination['url'] ?? '/notifications';
        $row['destination_valid'] = (bool) ($destination['is_valid'] ?? true);
        $row['destination_fallback_message'] = $destination['fallback_message'] ?? null;

        return $row;
    }
}
