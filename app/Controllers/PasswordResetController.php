<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\PasswordResetService;

final class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $service = new PasswordResetService())
    {
    }

    public function showForgotPassword(): void
    {
        $this->view('password/forgot', ['title' => 'Esqueci a senha']);
    }

    public function requestReset(): void
    {
        $this->service->requestReset((string) Request::input('email', ''));
        Response::json(['ok' => true, 'message' => 'Se o email existir, um link foi enviado.']);
    }

    public function showResetPassword(array $params = []): void
    {
        $this->view('password/reset', ['title' => 'Redefinir senha', 'token' => $params['token'] ?? Request::input('token', '')]);
    }

    public function resetPassword(): void
    {
        $ok = $this->service->resetPassword((string) Request::input('token', ''), (string) Request::input('password', ''));
        if (!$ok) {
            Response::json(['ok' => false, 'message' => 'Token inválido ou senha fraca'], 422);
        }

        Response::json(['ok' => true]);
    }
}
