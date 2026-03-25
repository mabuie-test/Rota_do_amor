<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\EmailVerificationService;
use App\Services\UserService;
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
        $this->view('auth/register', ['title' => 'Criar Conta']);
    }

    public function register(): void
    {
        try {
            $userId = $this->userService->createUser(Request::all());
            $this->emailVerificationService->sendVerification($userId);
            Response::json(['ok' => true, 'user_id' => $userId, 'next' => '/activation']);
        } catch (RuntimeException $exception) {
            Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
