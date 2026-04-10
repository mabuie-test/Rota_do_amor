<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
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
        $token = trim((string) ($params['token'] ?? Request::input('token', '')));
        if ($token === '') {
            Flash::set('error', 'Link de verificação inválido: token ausente.');
            Response::redirect('/login');
        }

        $ok = $this->service->verifyToken($token);
        if ($ok) {
            Flash::set('success', 'Email verificado com sucesso.');
            Response::redirect('/activation');
        }

        Flash::set('error', 'Token inválido ou expirado. Solicite um novo link de verificação.');
        Response::redirect('/login');
    }

    public function resend(): void
    {
        $ok = $this->service->resendVerification((int) Request::input('user_id', 0));
        Response::json(['ok' => $ok]);
    }
}
