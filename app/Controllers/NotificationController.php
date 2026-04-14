<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Services\NotificationService;

final class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $service = new NotificationService())
    {
    }

    public function index(): void
    {
        $items = $this->service->listForUser(Auth::id() ?? 0);
        $this->view('notifications/index', ['title' => 'Notificações', 'items' => $items]);
    }

    public function go(array $params = []): void
    {
        $userId = Auth::id() ?? 0;
        $notificationId = (int) ($params['id'] ?? 0);

        $notification = $this->service->getForUser($notificationId, $userId);
        if ($notification === []) {
            Response::redirect('/notifications');
        }

        $this->service->markAsRead($notificationId, $userId);
        Response::redirect($notification['destination_url'] ?? '/notifications');
    }
}
