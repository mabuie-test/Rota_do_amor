<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Response;
use App\Services\NotificationService;

final class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $service = new NotificationService())
    {
    }

    public function index(): void
    {
        $userId = Auth::id() ?? 0;
        $items = $this->service->listForUser($userId);
        $unread = $this->service->unreadCountForUser($userId);
        $this->view('notifications/index', ['title' => 'Notificações', 'items' => $items, 'unread_count' => $unread]);
    }

    public function go(array $params = []): void
    {
        $userId = Auth::id() ?? 0;
        $notificationId = (int) ($params['id'] ?? 0);

        $notification = $this->service->getForUser($notificationId, $userId);
        if ($notification === []) {
            Flash::set('warning', 'Notificação indisponível.');
            Response::redirect('/notifications');
        }

        $this->service->markAsRead($notificationId, $userId);
        if (!(bool) ($notification['destination_valid'] ?? true)) {
            Flash::set('warning', (string) ($notification['destination_fallback_message'] ?? 'Contexto original já não está disponível.'));
        }

        Response::redirect($notification['destination_url'] ?? '/notifications');
    }

    public function readAll(): void
    {
        $updated = $this->service->markAllAsRead(Auth::id() ?? 0);
        Flash::set('success', $updated > 0 ? 'Notificações marcadas como lidas.' : 'Não há notificações por marcar.');
        Response::redirect('/notifications');
    }
}
