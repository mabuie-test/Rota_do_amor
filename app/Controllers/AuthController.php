<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\RateLimiterService;
use App\Services\UserDashboardService;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService = new AuthService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService(),
        private readonly UserDashboardService $dashboardService = new UserDashboardService()
    )
    {
    }

    public function showLogin(): void
    {
        $this->view('auth/login', ['title' => 'Entrar']);
    }

    public function login(): void
    {
        $email = (string) Request::input('email', '');
        $key = 'user_login:' . mb_strtolower(trim($email)) . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('user_login', $key, 10, 10)) {
            Response::json(['ok' => false, 'message' => 'Muitas tentativas. Tente novamente em alguns minutos.'], 429);
        }

        $result = $this->authService->attemptLogin($email, (string) Request::input('password', ''));
        $this->rateLimiter->hit('user_login', $key, (int) ($result['user']['id'] ?? 0));
        if (!$result['ok']) {
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => $result['message']], 422);
            }

            Flash::set('error', $result['message']);
            Response::redirect('/login');
        }

        if (Request::expectsJson()) {
            Response::json(['ok' => true, 'next' => '/dashboard']);
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
        $userId = Auth::id() ?? 0;
        $dashboard = $this->dashboardService->build($userId);
        $this->view('home/dashboard', ['title' => 'Dashboard', 'dashboard' => $dashboard]);
    }
}
