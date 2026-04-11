<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Response;
use App\Services\IdentityVerificationService;
use App\Services\UploadService;
use RuntimeException;

final class VerificationController extends Controller
{
    public function __construct(
        private readonly IdentityVerificationService $service = new IdentityVerificationService(),
        private readonly UploadService $uploads = new UploadService()
    ) {
    }

    public function index(): void
    {
        $userId = Auth::id() ?? 0;
        $this->view('verification/index', [
            'title' => 'Verificação de Identidade',
            'latest' => $this->service->latestForUser($userId),
        ]);
    }

    public function submit(): void
    {
        $userId = Auth::id() ?? 0;

        try {
            $document = $this->uploads->storeImage($_FILES['document_image'] ?? [], 'verification/documents');
            $selfie = $this->uploads->storeImage($_FILES['selfie_image'] ?? [], 'verification/selfies');

            $id = $this->service->submitVerification(
                $userId,
                (string) ($document['path'] ?? ''),
                (string) ($selfie['path'] ?? '')
            );

            Flash::set('success', 'Verificação enviada com sucesso. Aguarde a análise da equipa.');
            Response::redirect('/verification');
        } catch (RuntimeException $exception) {
            Flash::set('error', $exception->getMessage());
            Response::redirect('/verification');
        }
    }
}
