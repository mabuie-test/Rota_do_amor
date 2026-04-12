<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\RiskCenterService;

final class AdminRiskController extends Controller
{
    public function __construct(private readonly RiskCenterService $service = new RiskCenterService())
    {
    }

    public function index(): void
    {
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
            'current_priority' => $priority,
            'invites_anomalies' => $risk['invites_anomalies'],
            'messages_anomalies' => $risk['messages_anomalies'],
        ]);
    }
}
