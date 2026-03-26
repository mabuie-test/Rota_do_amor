<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\EmailVerificationService;
use App\Services\UserService;
use Throwable;
use RuntimeException;

final class RegistrationController extends Controller
{
    public function __construct(
        private readonly UserService $userService = new UserService(),
        private readonly EmailVerificationService $emailVerificationService = new EmailVerificationService()
    ) {
    }

    public function showRegister(): void
    {
        $this->view('auth/register', [
            'title' => 'Criar Conta',
            'provinces' => $this->userService->listProvinces(),
            'cities' => $this->userService->listCities(),
        ]);
    }

    public function register(): void
    {
        try {
            $userId = $this->userService->createUser(Request::all());
            $this->emailVerificationService->sendVerification($userId);

            if (Request::expectsJson()) {
                Response::json(['ok' => true, 'user_id' => $userId, 'next' => '/activation']);
            }

            Flash::set('success', 'Conta criada com sucesso. Verifique o email e conclua a ativação.');
            Response::redirect('/activation');
        } catch (Throwable $exception) {
            $message = $this->friendlyRegistrationError($exception);

            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => $message], 422);
            }

            Flash::set('error', $message);
            Response::redirect('/register');
        }
    }

    private function friendlyRegistrationError(Throwable $exception): string
    {
        $message = $exception->getMessage();
        $normalized = mb_strtolower($message);

        if (str_contains($normalized, 'fk_users_province') || str_contains($normalized, 'foreign key')) {
            return 'Província ou cidade inválida. Escolha novamente os campos de localização.';
        }

        if (str_contains($normalized, 'duplicate') || str_contains($normalized, 'unique')) {
            return 'Já existe uma conta com os dados informados (email ou telefone).';
        }

        if ($exception instanceof RuntimeException) {
            return $message;
        }

        return 'Não foi possível concluir o registo agora. Tente novamente.';
    }
}
