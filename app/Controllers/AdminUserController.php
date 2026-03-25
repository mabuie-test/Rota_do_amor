<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\EmailVerificationService;

final class AdminUserController extends Controller
{
    public function __construct(private readonly EmailVerificationService $emailVerificationService = new EmailVerificationService())
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
            $stmt = $db->prepare('UPDATE users SET status=:status,updated_at=NOW() WHERE id=:id');
            $stmt->execute([':status' => Request::input('status', 'active'), ':id' => $params['id'] ?? 0]);
            Response::json(['ok' => true]);
        }

        if (str_contains(Request::uriPath(), '/resend-verification-email')) {
            $ok = $this->emailVerificationService->resendVerification((int) ($params['id'] ?? 0));
            Response::json(['ok' => $ok]);
        }
    }
}
