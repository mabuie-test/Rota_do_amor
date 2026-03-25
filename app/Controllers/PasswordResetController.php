<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\PasswordResetService;

final class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $service = new PasswordResetService()) {}
    public function showForgotPassword(): void { $this->view('password/forgot', ['title' => 'Esqueci a senha']); }
    public function requestReset(): void { $this->json(['requested' => $this->service->requestReset((string) Request::input('email', ''))]); }
    public function showResetPassword(): void { $this->view('password/reset', ['title' => 'Redefinir senha']); }
    public function resetPassword(): void { $this->json(['reset' => $this->service->resetPassword((string) Request::input('token', ''), (string) Request::input('password', ''))]); }
}
