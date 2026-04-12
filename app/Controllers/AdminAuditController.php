<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;

final class AdminAuditController extends Controller
{
    public function __construct(private readonly AuditService $audit = new AuditService())
    {
    }

    public function index(): void
    {
        $filters = [
            'actor_type' => trim((string) Request::input('actor_type', '')),
            'action' => trim((string) Request::input('action', '')),
            'target_type' => trim((string) Request::input('target_type', '')),
            'from' => trim((string) Request::input('from', '')),
            'to' => trim((string) Request::input('to', '')),
        ];
        $events = $this->audit->listEvents($filters);

        if (Request::expectsJson()) {
            Response::json(['events' => $events, 'filters' => $filters]);
        }

        $this->view('admin/audit', ['title' => 'Centro de Auditoria', 'events' => $events, 'filters' => $filters]);
    }
}
