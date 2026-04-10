<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Services\EmailVerificationService;
use App\Services\MailService;

final class AdminUserController extends Controller
{
    public function __construct(private readonly EmailVerificationService $emailVerificationService = new EmailVerificationService(),
        private readonly MailService $mail = new MailService())
    {
    }

    public function index(): void
    {
        $db = \App\Core\Database::connection();
        $rows = $db->query('SELECT id,first_name,last_name,email,status,premium_status,created_at FROM users ORDER BY id DESC LIMIT 500')->fetchAll();
        if (\App\Core\Request::input('format') === 'json') {
            Response::json(['users' => $rows]);
        }
        $this->view('admin/users', ['title' => 'Admin · Utilizadores', 'users' => $rows]);
    }

    public function show(array $params): void
    {
        $stmt = \App\Core\Database::connection()->prepare('SELECT * FROM users WHERE id=:id');
        $stmt->execute([':id' => (int) ($params['id'] ?? 0)]);
        Response::json(['user' => $stmt->fetch() ?: null]);
    }

    public function updateStatus(array $params): void
    {
        $newStatus = (string) \App\Core\Request::input('status', 'active');
        $db = \App\Core\Database::connection();
        $stmt = $db->prepare('UPDATE users SET status=:status,updated_at=NOW() WHERE id=:id');
        $stmt->execute([':status' => $newStatus, ':id' => (int) ($params['id'] ?? 0)]);
        $u = $db->prepare('SELECT id,email FROM users WHERE id=:id');
        $u->execute([':id' => (int) ($params['id'] ?? 0)]);
        $userRow = $u->fetch();
        if ($userRow) {
            $this->mail->sendAccountStatusChangedEmail((int) $userRow['id'], (string) $userRow['email'], $newStatus);
        }
        Response::json(['ok' => true]);
    }

    public function resendVerificationEmail(array $params): void
    {
        $ok = $this->emailVerificationService->resendVerification((int) ($params['id'] ?? 0));
        Response::json(['ok' => $ok]);
    }
}
