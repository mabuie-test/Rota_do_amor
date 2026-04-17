<?php

declare(strict_types=1);

namespace App\Services;

final class FeedRankingService
{
    public const TAB_FOR_YOU = 'for_you';
    public const TAB_TRENDING = 'trending';
    public const TAB_NEARBY = 'nearby';
    public const TAB_SAME_VIBE = 'same_vibe';
    public const TAB_SAME_INTENTION = 'same_intention';

    public function normalizeTab(string $tab): string
    {
        return match ($tab) {
            self::TAB_TRENDING, self::TAB_NEARBY, self::TAB_SAME_VIBE, self::TAB_SAME_INTENTION => $tab,
            default => self::TAB_FOR_YOU,
        };
    }

    public function orderByForTab(string $tab): string
    {
        return match ($tab) {
            self::TAB_TRENDING => 'engagement_score DESC, p.created_at DESC',
            self::TAB_NEARBY => 'is_same_city DESC, is_same_province DESC, p.created_at DESC',
            self::TAB_SAME_VIBE => 'vibe_score DESC, p.created_at DESC',
            self::TAB_SAME_INTENTION => 'same_intention DESC, compatibility_score DESC, p.created_at DESC',
            default => 'relational_score DESC, p.created_at DESC',
        };
    }
}
