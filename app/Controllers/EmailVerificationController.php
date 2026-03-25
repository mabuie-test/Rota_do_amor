<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\EmailVerificationService;

final class EmailVerificationController extends Controller
{
    public function __construct(private readonly EmailVerificationService $service = new EmailVerificationService()) {}
    public function verify(): void { $this->json(['verified' => $this->service->verifyToken((string) ($_GET['token'] ?? ''))]); }
    public function resend(): void { $this->json(['resent' => $this->service->resendVerification((int) Request::input('user_id', 0))]); }
}
