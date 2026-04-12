<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\RiskCenterService;

final class AdminRiskController extends Controller
{
    public function __construct(
        private readonly RiskCenterService $service = new RiskCenterService(),
        private readonly AuditService $audit = new AuditService()
    )
    {
    }

    public function index(): void
    {
        $this->audit->logAdminEvent((int) Session::get('admin_id', 0), 'risk_center_viewed', 'risk_center', null, ['module' => 'risk_center']);
        $risk = $this->service->build();
        $priority = trim((string) Request::input('priority', ''));
        $users = $risk['users'];

        if (in_array($priority, ['alta', 'média', 'baixa'], true)) {
            $users = array_values(array_filter($users, static fn(array $u): bool => (string) ($u['risk_priority'] ?? '') === $priority));
        }

        $this->view('admin/risk', [
            'title' => 'Super Admin · Risco & Abuso',
            'overview' => $risk['overview'],
            'users' => $users,
            'priority_queue' => $risk['priority_queue'],
            'explainability' => $risk['explainability'] ?? [],
            'current_priority' => $priority,
            'invites_anomalies' => $risk['invites_anomalies'],
            'messages_anomalies' => $risk['messages_anomalies'],
        ]);
    }
}
