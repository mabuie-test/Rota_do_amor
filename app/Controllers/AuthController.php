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
use Throwable;

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
        $emailKey = mb_strtolower(trim($email));
        $key = 'user_login:' . $emailKey . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('user_login', $key, 10, 10, 'failed')) {
            $this->respondLoginBlocked('Muitas tentativas. Tente novamente em alguns minutos.');
        }

        $result = $this->authService->attemptLogin($email, (string) Request::input('password', ''));
        if (!$result['ok']) {
            $this->rateLimiter->hitFailure('user_login', $key, null, ['email' => $emailKey]);
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => $result['message']], 422);
            }

            Flash::set('error', $result['message']);
            Response::redirect('/login');
        }

        $this->rateLimiter->hitSuccess('user_login', $key, (int) ($result['user']['id'] ?? 0), ['email' => $emailKey]);
        if (Request::expectsJson()) {
            Response::json(['ok' => true, 'next' => '/dashboard']);
        }

        Response::redirect('/dashboard');
    }

    private function respondLoginBlocked(string $message): never
    {
        if (Request::expectsJson()) {
            Response::json(['ok' => false, 'message' => $message], 429);
        }

        Flash::set('error', $message);
        Response::redirect('/login');
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
        try {
            $dashboard = $this->dashboardService->build($userId);
        } catch (Throwable $exception) {
            error_log(sprintf('[dashboard.render_fallback] user_id=%d reason=%s', $userId, $exception->getMessage()));
            $dashboard = $this->dashboardService->fallbackDashboardData();
            Flash::set('warning', 'Alguns blocos do dashboard estão temporariamente indisponíveis.');
        }
        $this->view('home/dashboard', ['title' => 'Dashboard', 'dashboard' => $dashboard]);
    }
}
