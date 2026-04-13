<?php

declare(strict_types=1);

namespace App\Services;

final class DailyRouteEventBridge
{
    public const EVENT_DISCOVER_VIEW = 'discover_view';
    public const EVENT_SWIPE_ACTION = 'swipe_action';
    public const EVENT_INVITE_SENT = 'invite_sent';
    public const EVENT_MESSAGE_SENT = 'message_sent';
    public const EVENT_DIARY_WRITTEN = 'diary_written';
    public const EVENT_FEED_LIKE = 'feed_like';
    public const EVENT_FEED_COMMENT = 'feed_comment';
    public const EVENT_FEED_POST = 'feed_post';
    public const EVENT_PROFILE_UPDATED = 'profile_updated';
    public const EVENT_PROFILE_INTERESTS_UPDATED = 'profile_interests_updated';
    public const EVENT_PROFILE_PREFERENCES_UPDATED = 'profile_preferences_updated';
    public const EVENT_PROFILE_PHOTO_UPLOADED = 'profile_photo_uploaded';
    public const EVENT_HEART_MODE_UPDATED = 'heart_mode_updated';
    public const EVENT_SAFE_DATE_PROPOSED = 'safe_date_proposed';
    public const EVENT_SAFE_DATE_ACCEPTED = 'safe_date_accepted';
    public const EVENT_SAFE_DATE_COMPLETED = 'safe_date_completed';

    /** @var array<string, bool> */
    private static array $requestSeen = [];

    public function __construct(private readonly DailyRouteService $dailyRoutes = new DailyRouteService())
    {
    }

    public function track(int $userId, string $eventType, int $increment = 1): void
    {
        $this->trackFromModule($userId, $eventType, 'unknown', $increment);
    }

    public function trackFromModule(int $userId, string $eventType, string $sourceModule, int $increment = 1): void
    {
        if ($userId <= 0 || $increment <= 0 || !$this->isSupported($eventType)) {
            return;
        }

        $key = $userId . ':' . $eventType . ':' . $sourceModule;
        if (isset(self::$requestSeen[$key]) && $this->isSingleFireEvent($eventType)) {
            return;
        }

        $this->dailyRoutes->trackAction($userId, $eventType, $increment, $sourceModule);
        if ($this->isSingleFireEvent($eventType)) {
            self::$requestSeen[$key] = true;
        }
    }

    private function isSupported(string $eventType): bool
    {
        return in_array($eventType, [
            self::EVENT_DISCOVER_VIEW,
            self::EVENT_SWIPE_ACTION,
            self::EVENT_INVITE_SENT,
            self::EVENT_MESSAGE_SENT,
            self::EVENT_DIARY_WRITTEN,
            self::EVENT_FEED_LIKE,
            self::EVENT_FEED_COMMENT,
            self::EVENT_FEED_POST,
            self::EVENT_PROFILE_UPDATED,
            self::EVENT_PROFILE_INTERESTS_UPDATED,
            self::EVENT_PROFILE_PREFERENCES_UPDATED,
            self::EVENT_PROFILE_PHOTO_UPLOADED,
            self::EVENT_HEART_MODE_UPDATED,
            self::EVENT_SAFE_DATE_PROPOSED,
            self::EVENT_SAFE_DATE_ACCEPTED,
            self::EVENT_SAFE_DATE_COMPLETED,
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
            self::EVENT_PROFILE_UPDATED,
            self::EVENT_PROFILE_INTERESTS_UPDATED,
            self::EVENT_PROFILE_PREFERENCES_UPDATED,
            self::EVENT_PROFILE_PHOTO_UPLOADED,
            self::EVENT_HEART_MODE_UPDATED,
            self::EVENT_SAFE_DATE_ACCEPTED,
            self::EVENT_SAFE_DATE_COMPLETED,
            self::EVENT_DIARY_WRITTEN,
            self::EVENT_INVITE_SENT,
        ], true);
    }
}
