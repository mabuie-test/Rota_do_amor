<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AdminAuthController extends Controller
{
    public function index(): void
    {
        if (Request::method() === 'GET') {
            $this->view('admin/login', ['title' => 'Admin Login']);
            return;
        }

        $email = (string) Request::input('email', '');
        $password = (string) Request::input('password', '');
        $admin = \App\Core\Database::connection()->prepare('SELECT * FROM admins WHERE email=:email LIMIT 1');
        $admin->execute([':email' => $email]);
        $row = $admin->fetch();
        if (!$row || !password_verify($password, (string) $row['password'])) {
            Response::json(['ok' => false, 'message' => 'Credenciais inválidas'], 422);
        }

        Session::put('admin_id', (int) $row['id']);
        Response::redirect('/admin');
    }
}
