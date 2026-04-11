<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\EmailVerificationService;
use App\Services\MailService;

final class AdminUserController extends Controller
{
    private const ALLOWED_STATUSES = [
        'active',
        'suspended',
        'banned',
        'pending_activation',
        'pending_verification',
        'expired',
    ];

    public function __construct(
        private readonly EmailVerificationService $emailVerificationService = new EmailVerificationService(),
        private readonly MailService $mail = new MailService()
    ) {
    }

    public function index(): void
    {
        $db = \App\Core\Database::connection();
        $status = trim((string) Request::input('status', ''));

        $where = '';
        $params = [];
        if ($status !== '') {
            if (!in_array($status, self::ALLOWED_STATUSES, true)) {
                if (Request::expectsJson()) {
                    Response::json(['ok' => false, 'message' => 'Filtro de status inválido.'], 422);
                }
                Flash::set('error', 'Filtro de status inválido.');
                Response::redirect('/admin/users');
            }
            $where = ' WHERE status = :status ';
            $params[':status'] = $status;
        }

        $stmt = $db->prepare('SELECT id,first_name,last_name,email,status,premium_status,email_verified_at,created_at FROM users' . $where . 'ORDER BY id DESC LIMIT 500');
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if (Request::expectsJson()) {
            Response::json([
                'users' => $rows,
                'filters' => ['status' => $status],
                'allowed_statuses' => self::ALLOWED_STATUSES,
            ]);
        }

        $this->view('admin/users', [
            'title' => 'Admin · Utilizadores',
            'users' => $rows,
            'currentStatusFilter' => $status,
            'allowedStatuses' => self::ALLOWED_STATUSES,
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

        $verification = $db->prepare(
            'SELECT iv.*, a.name AS reviewed_by_admin_name
             FROM identity_verifications iv
             LEFT JOIN admins a ON a.id = iv.reviewed_by_admin_id
             WHERE iv.user_id=:id
             ORDER BY iv.id DESC
             LIMIT 1'
        );
        $verification->execute([':id' => $userId]);

        $moderation = $db->prepare(
            'SELECT ma.*, a.name AS admin_name
             FROM moderation_actions ma
             INNER JOIN admins a ON a.id = ma.admin_id
             WHERE ma.user_id=:id
             ORDER BY ma.id DESC
             LIMIT 20'
        );
        $moderation->execute([':id' => $userId]);

        $photos = $db->prepare('SELECT id,image_path,is_primary,created_at FROM user_photos WHERE user_id=:id ORDER BY is_primary DESC,id DESC LIMIT 10');
        $photos->execute([':id' => $userId]);

        if (Request::expectsJson()) {
            Response::json([
                'user' => $user,
                'latest_verification' => $verification->fetch() ?: null,
                'moderation_actions' => $moderation->fetchAll(),
                'photos' => $photos->fetchAll(),
                'allowed_statuses' => self::ALLOWED_STATUSES,
            ]);
        }

        $this->view('admin/user-show', [
            'title' => 'Admin · Utilizador #' . $userId,
            'user' => $user,
            'latestVerification' => $verification->fetch() ?: null,
            'moderationActions' => $moderation->fetchAll(),
            'photos' => $photos->fetchAll(),
            'allowedStatuses' => self::ALLOWED_STATUSES,
        ]);
    }

    public function updateStatus(array $params): void
    {
        $userId = (int) ($params['id'] ?? 0);
        $newStatus = trim((string) Request::input('status', ''));

        if ($userId <= 0) {
            $this->respondAction(false, 'ID de utilizador inválido.', '/admin/users', 422);
        }

        if (!in_array($newStatus, self::ALLOWED_STATUSES, true)) {
            $this->respondAction(false, 'Estado inválido. Use um estado permitido.', '/admin/users', 422);
        }

        $db = \App\Core\Database::connection();
        $u = $db->prepare('SELECT id,email FROM users WHERE id=:id LIMIT 1');
        $u->execute([':id' => $userId]);
        $userRow = $u->fetch();

        if (!$userRow) {
            $this->respondAction(false, 'Utilizador não encontrado.', '/admin/users', 404);
        }

        $stmt = $db->prepare('UPDATE users SET status=:status,updated_at=NOW() WHERE id=:id');
        $stmt->execute([':status' => $newStatus, ':id' => $userId]);

        $this->mail->sendAccountStatusChangedEmail((int) $userRow['id'], (string) $userRow['email'], $newStatus);

        $this->respondAction(true, 'Estado actualizado.', '/admin/users');
    }

    public function resendVerificationEmail(array $params): void
    {
        $userId = (int) ($params['id'] ?? 0);
        if ($userId <= 0) {
            $this->respondAction(false, 'ID de utilizador inválido.', '/admin/users', 422);
        }

        $ok = $this->emailVerificationService->resendVerification($userId);

        if (!$ok) {
            $this->respondAction(false, 'Não foi possível reenviar o email de verificação.', '/admin/users', 422);
        }

        $this->respondAction(true, 'Email de verificação reenviado.', '/admin/users');
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
