<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\PasswordResetService;
use App\Services\RateLimiterService;

final class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $service = new PasswordResetService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService()
    )
    {
    }

    public function showForgotPassword(): void
    {
        $this->view('password/forgot', ['title' => 'Esqueci a senha']);
    }

    public function requestReset(): void
    {
        $key = 'password_reset_request:' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('password_reset_request', $key, 8, 10)) {
            Response::json(['ok' => false, 'message' => 'Muitas tentativas de reset.'], 429);
        }
        $this->service->requestReset((string) Request::input('email', ''));
        $this->rateLimiter->hit('password_reset_request', $key);

        if (Request::expectsJson()) {
            Response::json(['ok' => true, 'message' => 'Se o email existir, um link foi enviado.']);
        }

        Flash::set('success', 'Se o email existir, um link foi enviado.');
        Response::redirect('/forgot-password');
    }

    public function showResetPassword(array $params = []): void
    {
        $this->view('password/reset', ['title' => 'Redefinir senha', 'token' => $params['token'] ?? Request::input('token', '')]);
    }

    public function resetPassword(): void
    {
        $ok = $this->service->resetPassword((string) Request::input('token', ''), (string) Request::input('password', ''));
        if (!$ok) {
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => 'Token inválido ou senha fraca'], 422);
            }

            Flash::set('error', 'Token inválido ou senha fraca.');
            Response::redirect('/reset-password');
        }

        if (Request::expectsJson()) {
            Response::json(['ok' => true]);
        }

        Flash::set('success', 'Senha atualizada com sucesso.');
        Response::redirect('/login');
    }
}
