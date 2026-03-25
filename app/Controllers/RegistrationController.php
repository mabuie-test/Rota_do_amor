<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;

final class RegistrationController extends Controller
{
    public function showRegister(): void { $this->view('auth/register', ['title' => 'Criar Conta']); }
    public function register(): void { Response::redirect('/activation'); }
}
