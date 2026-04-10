<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\RateLimiterService;

final class AdminAuthController extends Controller
{
    public function __construct(private readonly RateLimiterService $rateLimiter = new RateLimiterService())
    {
    }

    public function index(): void
    {
        if (Request::method() === 'GET') {
            $this->view('admin/login', ['title' => 'Admin Login']);
            return;
        }

        $email = (string) Request::input('email', '');
        $password = (string) Request::input('password', '');
        $key = 'admin_login:' . mb_strtolower(trim($email)) . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('admin_login', $key, 8, 10, 'failed')) {
            Response::json(['ok' => false, 'message' => 'Muitas tentativas. Aguarde alguns minutos.'], 429);
        }

        $admin = \App\Core\Database::connection()->prepare('SELECT * FROM admins WHERE email=:email LIMIT 1');
        $admin->execute([':email' => $email]);
        $row = $admin->fetch();
        if (!$row || !password_verify($password, (string) $row['password']) || (string) ($row['status'] ?? 'inactive') !== 'active') {
            $this->rateLimiter->hitFailure('admin_login', $key, null, ['email' => mb_strtolower(trim($email))]);
            Response::json(['ok' => false, 'message' => 'Credenciais inválidas'], 422);
        }

        $this->rateLimiter->hitSuccess('admin_login', $key, (int) $row['id'], ['email' => mb_strtolower(trim($email))]);
        Session::put('admin_id', (int) $row['id']);
        Session::put('admin_role', (string) ($row['role'] ?? 'moderator'));
        Response::redirect('/admin');
    }
}
