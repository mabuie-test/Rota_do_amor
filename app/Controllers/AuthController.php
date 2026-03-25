<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;

final class AuthController extends Controller
{
    public function showLogin(): void { $this->view('auth/login', ['title' => 'Entrar']); }
    public function login(): void { Session::put('user_id', 1); Session::regenerate(); Response::redirect('/dashboard'); }
    public function dashboard(): void { $this->view('home/dashboard', ['title' => 'Dashboard']); }
}
