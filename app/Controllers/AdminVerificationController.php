<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\IdentityVerificationService;

final class AdminVerificationController extends Controller
{
    public function __construct(private readonly IdentityVerificationService $service = new IdentityVerificationService()) {}

    public function index(): void
    {
        $rows = $this->service->listForAdminPanel();

        if (Request::expectsJson()) {
            Response::json(['verifications' => $rows]);
        }

        $this->view('admin/verifications', [
            'title' => 'Admin · Verificações',
            'verifications' => $rows,
        ]);
    }

    public function approve(array $params): void
    {
        $verificationId = (int) ($params['id'] ?? 0);

        if ($verificationId <= 0) {
            $this->respondAction(false, 'ID de verificação inválido.', '/admin/verifications', 422);
        }

        $ok = $this->service->approveVerification($verificationId, (int) Session::get('admin_id', 0));

        if (!$ok) {
            $this->respondAction(false, 'Verificação não encontrada.', '/admin/verifications', 404);
        }

        $this->respondAction(true, 'Verificação aprovada com sucesso.', '/admin/verifications');
    }

    public function reject(array $params): void
    {
        $verificationId = (int) ($params['id'] ?? 0);
        $reason = trim((string) Request::input('reason', ''));

        if ($verificationId <= 0) {
            $this->respondAction(false, 'ID de verificação inválido.', '/admin/verifications', 422);
        }

        if ($reason === '') {
            $this->respondAction(false, 'Motivo é obrigatório.', '/admin/verifications', 422);
        }

        $ok = $this->service->rejectVerification($verificationId, (int) Session::get('admin_id', 0), $reason);
        if (!$ok) {
            $this->respondAction(false, 'Verificação não encontrada.', '/admin/verifications', 404);
        }

        $this->respondAction(true, 'Verificação rejeitada com sucesso.', '/admin/verifications');
    }

    private function respondAction(bool $ok, string $message, string $redirectTo, int $status = 200): never
    {
        if (Request::expectsJson()) {
            Response::json(['ok' => $ok, 'message' => $message], $status);
        }

        Flash::set($ok ? 'success' : 'error', $message);
        Response::redirect($redirectTo);
    }
}
