<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\EmailVerificationService;

final class EmailVerificationController extends Controller
{
    public function __construct(private readonly EmailVerificationService $service = new EmailVerificationService())
    {
    }

    public function verify(array $params = []): void
    {
        $token = (string) ($params['token'] ?? Request::input('token', ''));
        $ok = $this->service->verifyToken($token);
        if ($ok) {
            Response::redirect('/activation');
        }

        Response::json(['ok' => false, 'message' => 'Token inválido ou expirado'], 422);
    }

    public function resend(): void
    {
        $ok = $this->service->resendVerification((int) Request::input('user_id', 0));
        Response::json(['ok' => $ok]);
    }
}
