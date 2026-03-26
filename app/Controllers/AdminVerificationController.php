<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\IdentityVerificationService;

final class AdminVerificationController extends Controller
{
    public function __construct(private readonly IdentityVerificationService $service = new IdentityVerificationService()) {}

    public function index(array $params = []): void
    {
        if (Request::method() === 'GET') {
            $rows = \App\Core\Database::connection()->query('SELECT * FROM identity_verifications ORDER BY id DESC LIMIT 500')->fetchAll();
            if (Request::input('format') === 'json') {
                Response::json(['verifications' => $rows]);
            }
            $this->view('admin/verifications', ['title' => 'Admin · Verificações', 'verifications' => $rows]);
            return;
        }

        if (str_contains(Request::uriPath(), '/approve')) {
            $ok = $this->service->approveVerification((int) ($params['id'] ?? 0), (int) Session::get('admin_id', 0));
            Response::json(['ok' => $ok]);
        }

        if (str_contains(Request::uriPath(), '/reject')) {
            $ok = $this->service->rejectVerification((int) ($params['id'] ?? 0), (int) Session::get('admin_id', 0), (string) Request::input('reason', 'Não informado'));
            Response::json(['ok' => $ok]);
        }
    }
}
