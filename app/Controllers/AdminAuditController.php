<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;

final class AdminAuditController extends Controller
{
    public function __construct(private readonly AuditService $audit = new AuditService())
    {
    }

    public function index(): void
    {
        $this->audit->logAdminEvent((int) Session::get('admin_id', 0), 'audit_center_viewed', 'audit_center', null, ['module' => 'audit_center']);
        $filters = [
            'actor_type' => trim((string) Request::input('actor_type', '')),
            'actor_id' => (int) Request::input('actor_id', 0),
            'admin_id' => (int) Request::input('admin_id', 0),
            'action' => trim((string) Request::input('action', '')),
            'target_type' => trim((string) Request::input('target_type', '')),
            'target_id' => (int) Request::input('target_id', 0),
            'q' => trim((string) Request::input('q', '')),
            'from' => trim((string) Request::input('from', '')),
            'to' => trim((string) Request::input('to', '')),
            'page' => (int) Request::input('page', 1),
            'per_page' => (int) Request::input('per_page', 50),
        ];
        $result = $this->audit->listEvents($filters);

        if (Request::expectsJson()) {
            Response::json(['events' => $result['items'], 'pagination' => $result, 'filters' => $filters]);
        }

        $this->view('admin/audit', [
            'title' => 'Centro de Auditoria',
            'events' => $result['items'],
            'filters' => $filters,
            'pagination' => $result,
            'actions' => $result['actions'] ?? [],
        ]);
    }
}
