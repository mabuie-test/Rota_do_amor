<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService = new AuthService())
    {
    }

    public function showLogin(): void
    {
        $this->view('auth/login', ['title' => 'Entrar']);
    }

    public function login(): void
    {
        $result = $this->authService->attemptLogin((string) Request::input('email', ''), (string) Request::input('password', ''));
        if (!$result['ok']) {
            Response::json(['ok' => false, 'message' => $result['message']], 422);
        }

        Response::redirect('/dashboard');
    }

    public function logout(): void
    {
        $id = Auth::id();
        if ($id) {
            $this->authService->logout($id);
        }
        Response::redirect('/login');
    }

    public function dashboard(): void
    {
        $this->view('home/dashboard', ['title' => 'Dashboard']);
    }
}
