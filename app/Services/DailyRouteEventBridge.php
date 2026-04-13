<?php

declare(strict_types=1);

namespace App\Services;

final class DailyRouteEventBridge
{
    /** @var array<string, bool> */
    private static array $requestSeen = [];

    public function __construct(private readonly DailyRouteService $dailyRoutes = new DailyRouteService())
    {
    }

    public function track(int $userId, string $eventType, int $increment = 1): void
    {
        if ($userId <= 0 || $increment <= 0 || !$this->isSupported($eventType)) {
            return;
        }

        $key = $userId . ':' . $eventType;
        if (isset(self::$requestSeen[$key]) && $this->isSingleFireEvent($eventType)) {
            return;
        }

        $this->dailyRoutes->trackAction($userId, $eventType, $increment);
        if ($this->isSingleFireEvent($eventType)) {
            self::$requestSeen[$key] = true;
        }
    }

    private function isSupported(string $eventType): bool
    {
        return in_array($eventType, [
            'discover_view',
            'swipe_action',
            'invite_sent',
            'message_sent',
            'diary_written',
            'feed_like',
            'feed_comment',
            'feed_post',
            'profile_updated',
            'profile_interests_updated',
            'profile_preferences_updated',
            'profile_photo_uploaded',
            'heart_mode_updated',
            'safe_date_proposed',
            'safe_date_accepted',
            'safe_date_completed',
            'visitor_profile_viewed',
            'visitor_profile_engaged',
            'anonymous_story_published',
            'anonymous_story_interacted',
            'compatibility_duel_joined',
            'compatibility_duel_voted',
            'compatibility_duel_action_taken',
        ], true);
    }

    private function isSingleFireEvent(string $eventType): bool
    {
        return in_array($eventType, [
            'profile_updated',
            'profile_interests_updated',
            'profile_preferences_updated',
            'profile_photo_uploaded',
            'heart_mode_updated',
            'safe_date_accepted',
            'safe_date_completed',
            'diary_written',
            'invite_sent',
        ], true);
    }
}
