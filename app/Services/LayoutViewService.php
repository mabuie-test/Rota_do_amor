<?php

declare(strict_types=1);

namespace App\Services;

final class LayoutViewService
{
    private ?int $cachedViewerId = null;
    private ?array $cachedPayload = null;

    public function __construct(
        private readonly NotificationService $notifications = new NotificationService()
    ) {
    }

    public function forAuthenticatedViewer(int $viewerId): array
    {
        if ($this->cachedPayload !== null && $this->cachedViewerId === $viewerId) {
            return $this->cachedPayload;
        }

        $payload = [
            'layout_unread_notifications' => $viewerId > 0
                ? $this->notifications->unreadCountForUser($viewerId)
                : 0,
        ];

        $this->cachedViewerId = $viewerId;
        $this->cachedPayload = $payload;

        return $payload;
    }
}
