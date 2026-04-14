<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\NotificationService;

final class View
{
    public static function render(string $view, array $data = []): void
    {
        extract(self::withSharedData($data), EXTR_SKIP);
        $file = dirname(__DIR__) . '/Views/' . $view . '.php';

        if (!is_file($file)) {
            http_response_code(404);
            echo 'View not found.';
            return;
        }

        require dirname(__DIR__) . '/Views/layouts/main.php';
    }

    private static function withSharedData(array $data): array
    {
        if (array_key_exists('layout_unread_notifications', $data)) {
            return $data;
        }

        $viewerId = Auth::id() ?? 0;
        $data['layout_unread_notifications'] = $viewerId > 0
            ? (new NotificationService())->unreadCountForUser($viewerId)
            : 0;

        return $data;
    }
}
