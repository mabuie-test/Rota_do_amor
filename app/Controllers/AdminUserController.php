<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\EmailVerificationService;
use App\Services\MailService;

final class AdminUserController extends Controller
{
    public function __construct(private readonly EmailVerificationService $emailVerificationService = new EmailVerificationService(),
        private readonly MailService $mail = new MailService())
    {
    }

    public function index(array $params = []): void
    {
        $db = \App\Core\Database::connection();
        if (isset($params['id']) && Request::method() === 'GET') {
            $stmt = $db->prepare('SELECT * FROM users WHERE id=:id');
            $stmt->execute([':id' => $params['id']]);
            Response::json(['user' => $stmt->fetch() ?: null]);
        }

        if (Request::method() === 'GET') {
            Response::json(['users' => $db->query('SELECT id,first_name,last_name,email,status,premium_status,created_at FROM users ORDER BY id DESC LIMIT 500')->fetchAll()]);
        }

        if (str_contains(Request::uriPath(), '/status')) {
            $newStatus = (string) Request::input('status', 'active');
            $stmt = $db->prepare('UPDATE users SET status=:status,updated_at=NOW() WHERE id=:id');
            $stmt->execute([':status' => $newStatus, ':id' => $params['id'] ?? 0]);
            $u = $db->prepare('SELECT id,email FROM users WHERE id=:id');
            $u->execute([':id' => $params['id'] ?? 0]);
            $userRow = $u->fetch();
            if ($userRow) {
                $this->mail->sendAccountStatusChangedEmail((int) $userRow['id'], (string) $userRow['email'], $newStatus);
            }
            Response::json(['ok' => true]);
        }

        if (str_contains(Request::uriPath(), '/resend-verification-email')) {
            $ok = $this->emailVerificationService->resendVerification((int) ($params['id'] ?? 0));
            Response::json(['ok' => $ok]);
        }
    }
}
