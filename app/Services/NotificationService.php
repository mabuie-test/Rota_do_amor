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

    public function resolveDestination(array $notification): string
    {
        $type = (string) ($notification['type'] ?? '');
        $payload = is_array($notification['payload'] ?? null) ? $notification['payload'] : [];

        $postId = (int) ($payload['post_id'] ?? 0);
        $profileId = (int) ($payload['profile_id'] ?? ($payload['sender_id'] ?? ($payload['visitor_user_id'] ?? 0)));
        $conversationId = (int) ($payload['conversation_id'] ?? 0);
        $safeDateId = (int) ($payload['safe_date_id'] ?? 0);

        if ($safeDateId > 0) {
            return '/dates/' . $safeDateId;
        }

        if ($postId > 0) {
            return '/feed?post=' . $postId . '#post-' . $postId;
        }

        if ($conversationId > 0) {
            return '/messages?conversation=' . $conversationId;
        }

        if ($profileId > 0 && in_array($type, ['profile_view', 'visitor_profile', 'profile_visit'], true)) {
            return '/discover/profile/' . $profileId;
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
            'compatibility_duel_action_taken' => '/compatibility-duel',
            'daily_route_ready' => '/daily-route',
            'daily_route_almost_done' => '/daily-route',
            'daily_route_completed' => '/daily-route',
            'safe_date_proposed' => '/dates',
            'safe_date_accepted' => '/dates',
            'safe_date_declined' => '/dates',
            'safe_date_cancelled' => '/dates',
            'safe_date_reschedule_requested' => '/dates',
            'safe_date_rescheduled' => '/dates',
            'safe_date_reschedule_declined' => '/dates',
            'safe_date_completed' => '/dates',
            'safe_date_arrived' => '/dates',
            'safe_date_finished_well' => '/dates',
            'safe_date_expired' => '/dates',
        ];

        if (str_starts_with($type, 'daily_route_')) {
            return '/daily-route';
        }

        if (str_starts_with($type, 'safe_date_') || str_starts_with($type, 'safe_date_reminder_')) {
            return '/dates';
        }

        return $map[$type] ?? '/notifications';
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
        $row['destination_url'] = $this->resolveDestination($row);

        return $row;
    }
}
