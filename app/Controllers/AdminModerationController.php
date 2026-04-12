<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\UserStatusTransitionService;

final class AdminModerationController extends Controller
{
    private const ACTION_STATUS_MAP = [
        'suspend' => 'suspended',
        'ban' => 'banned',
        'unsuspend' => 'active',
        'unban' => 'active',
        'activate' => 'active',
        'deactivate' => 'expired',
    ];

    public function __construct(private readonly UserStatusTransitionService $statusTransitions = new UserStatusTransitionService())
    {
    }

    public function index(): void
    {
        $rows = \App\Core\Database::connection()->query('SELECT ma.id, ma.admin_id, ma.user_id, ma.action_type, ma.reason, ma.created_at, a.name AS admin_name, CONCAT(u.first_name, " ", u.last_name) AS user_name, u.email AS user_email FROM moderation_actions ma INNER JOIN admins a ON a.id = ma.admin_id INNER JOIN users u ON u.id = ma.user_id ORDER BY ma.id DESC LIMIT 500')->fetchAll();

        if (Request::expectsJson()) {
            Response::json(['moderation_actions' => $rows]);
        }

        $this->view('admin/moderation', ['title' => 'Admin · Moderação', 'actions' => $rows, 'allowedActions' => array_keys(self::ACTION_STATUS_MAP)]);
    }

    public function suspend(): void { $this->apply('suspend'); }
    public function ban(): void { $this->apply('ban'); }
    public function unsuspend(): void { $this->apply('unsuspend'); }
    public function unban(): void { $this->apply('unban'); }
    public function activate(): void { $this->apply('activate'); }
    public function deactivate(): void { $this->apply('deactivate'); }

    private function apply(string $action): void
    {
        $userId = (int) Request::input('user_id', 0);
        $reason = trim((string) Request::input('reason', ''));

        if (!isset(self::ACTION_STATUS_MAP[$action]) || $userId <= 0 || $reason === '') {
            $this->respondAction(false, 'Dados de moderação inválidos.', '/admin/moderation', 422);
        }

        $result = $this->statusTransitions->transition(
            $userId,
            self::ACTION_STATUS_MAP[$action],
            (int) Session::get('admin_id', 0),
            $reason,
            'moderation'
        );

        $messages = [
            'suspend' => 'Utilizador suspenso com sucesso.',
            'ban' => 'Utilizador banido com sucesso.',
            'unsuspend' => 'Suspensão revertida com sucesso.',
            'unban' => 'Banimento revertido com sucesso.',
            'activate' => 'Utilizador activado com sucesso.',
            'deactivate' => 'Utilizador desactivado com sucesso.',
        ];

        $this->respondAction((bool) ($result['ok'] ?? false), (string) ($result['ok'] ?? false ? ($messages[$action] ?? 'Ação executada.') : ($result['message'] ?? 'Falha na moderação.')), '/admin/moderation', ($result['ok'] ?? false) ? 200 : 422);
    }

    private function respondAction(bool $ok, string $message, string $redirectTo, int $status = 200): never
    {
        if (Request::expectsJson()) {
            Response::json(['ok' => $ok, 'message' => $message], $status);
        }

        Flash::set($ok ? 'success' : 'error', $message);
        Response::redirect($redirectTo);
    }
}
