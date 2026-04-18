<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
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
        $document = null;
        $selfie = null;

        try {
            $documentFile = (array) ($_FILES['document_image'] ?? []);
            $documentFallbackData = (string) Request::input('document_image_data_url', '');
            $document = $this->uploads->shouldUseDataUrlFallback($documentFile, $documentFallbackData)
                ? $this->uploads->storeImageFromDataUrl($documentFallbackData, 'verification/documents')
                : $this->uploads->storeImage($documentFile, 'verification/documents');

            $selfieFile = (array) ($_FILES['selfie_image'] ?? []);
            $selfieFallbackData = (string) Request::input('selfie_image_data_url', '');
            $selfie = $this->uploads->shouldUseDataUrlFallback($selfieFile, $selfieFallbackData)
                ? $this->uploads->storeImageFromDataUrl($selfieFallbackData, 'verification/selfies')
                : $this->uploads->storeImage($selfieFile, 'verification/selfies');

            $id = $this->service->submitVerification(
                $userId,
                (string) ($document['path'] ?? ''),
                (string) ($selfie['path'] ?? '')
            );

            Flash::set('success', 'Verificação enviada com sucesso. Aguarde a análise da equipa.');
            Response::redirect('/verification');
        } catch (RuntimeException $exception) {
            if (is_array($document)) {
                $this->uploads->deleteImageBundle($document);
            }
            if (is_array($selfie)) {
                $this->uploads->deleteImageBundle($selfie);
            }
            Flash::set('error', $exception->getMessage());
            Response::redirect('/verification');
        }
    }
}
