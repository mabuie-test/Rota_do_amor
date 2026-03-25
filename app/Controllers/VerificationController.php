<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\IdentityVerificationService;

final class VerificationController extends Controller
{
    public function __construct(private readonly IdentityVerificationService $service = new IdentityVerificationService())
    {
    }

    public function index(): void
    {
        $this->view('verification/index', ['title' => 'Verificação de Identidade']);
    }

    public function submit(): void
    {
        $id = $this->service->submitVerification(
            Auth::id() ?? 0,
            (string) Request::input('document_image_path', ''),
            (string) Request::input('selfie_image_path', '')
        );

        Response::json(['ok' => true, 'verification_id' => $id]);
    }
}
