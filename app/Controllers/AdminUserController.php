<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\EmailVerificationService;
use App\Services\UserStatusTransitionService;

final class AdminUserController extends Controller
{
    public function __construct(
        private readonly EmailVerificationService $emailVerificationService = new EmailVerificationService(),
        private readonly UserStatusTransitionService $statusTransitions = new UserStatusTransitionService()
    ) {
    }

    public function index(): void
    {
        $db = \App\Core\Database::connection();
        $status = trim((string) Request::input('status', ''));

        $where = '';
        $params = [];
        if ($status !== '') {
            if (!in_array($status, $this->statusTransitions->allowedStatuses(), true)) {
                $this->respondAction(false, 'Filtro de status inválido.', '/admin/users', 422);
            }
            $where = ' WHERE status = :status ';
            $params[':status'] = $status;
        }

        $stmt = $db->prepare('SELECT id,first_name,last_name,email,status,premium_status,email_verified_at,created_at FROM users ' . $where . ' ORDER BY id DESC LIMIT 500');
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if (Request::expectsJson()) {
            Response::json(['users' => $rows, 'filters' => ['status' => $status], 'allowed_statuses' => $this->statusTransitions->allowedStatuses()]);
        }

        $this->view('admin/users', [
            'title' => 'Admin · Utilizadores',
            'users' => $rows,
            'currentStatusFilter' => $status,
            'allowedStatuses' => $this->statusTransitions->allowedStatuses(),
        ]);
    }

    public function show(array $params): void
    {
        $userId = (int) ($params['id'] ?? 0);
        if ($userId <= 0) {
            $this->respondAction(false, 'ID de utilizador inválido.', '/admin/users', 422);
        }

        $db = \App\Core\Database::connection();
        $stmt = $db->prepare('SELECT * FROM users WHERE id=:id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->respondAction(false, 'Utilizador não encontrado.', '/admin/users', 404);
        }

        $verification = $db->prepare('SELECT iv.*, a.name AS reviewed_by_admin_name FROM identity_verifications iv LEFT JOIN admins a ON a.id = iv.reviewed_by_admin_id WHERE iv.user_id=:id ORDER BY iv.id DESC LIMIT 1');
        $verification->execute([':id' => $userId]);

        $moderation = $db->prepare('SELECT ma.*, a.name AS admin_name FROM moderation_actions ma INNER JOIN admins a ON a.id = ma.admin_id WHERE ma.user_id=:id ORDER BY ma.id DESC LIMIT 20');
        $moderation->execute([':id' => $userId]);

        $this->view('admin/user-show', [
            'title' => 'Admin · Utilizador #' . $userId,
            'user' => $user,
            'latestVerification' => $verification->fetch() ?: null,
            'moderationActions' => $moderation->fetchAll(),
            'allowedStatuses' => $this->statusTransitions->allowedStatuses(),
            'photos' => $this->loadPhotos($db, $userId),
        ]);
    }

    public function updateStatus(array $params): void
    {
        $userId = (int) ($params['id'] ?? 0);
        $newStatus = trim((string) Request::input('status', ''));
        $reason = trim((string) Request::input('reason', 'Ajuste operacional no painel de utilizadores'));

        if ($userId <= 0 || !in_array($newStatus, $this->statusTransitions->allowedStatuses(), true)) {
            $this->respondAction(false, 'Dados de alteração de estado inválidos.', '/admin/users', 422);
        }

        $result = $this->statusTransitions->transition($userId, $newStatus, (int) Session::get('admin_id', 0), $reason, 'admin_user');
        $this->respondAction((bool) ($result['ok'] ?? false), (string) ($result['message'] ?? 'Falha ao actualizar estado.'), '/admin/users', ($result['ok'] ?? false) ? 200 : 422);
    }

    public function resendVerificationEmail(array $params): void
    {
        $userId = (int) ($params['id'] ?? 0);
        if ($userId <= 0) {
            $this->respondAction(false, 'ID de utilizador inválido.', '/admin/users', 422);
        }

        $ok = $this->emailVerificationService->resendVerification($userId);
        $this->respondAction($ok, $ok ? 'Email de verificação reenviado.' : 'Não foi possível reenviar o email de verificação.', '/admin/users', $ok ? 200 : 422);
    }


    private function loadPhotos(\PDO $db, int $userId): array
    {
        $photos = $db->prepare('SELECT id,image_path,is_primary,created_at FROM user_photos WHERE user_id=:id ORDER BY is_primary DESC,id DESC LIMIT 10');
        $photos->execute([':id' => $userId]);
        return $photos->fetchAll() ?: [];
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
