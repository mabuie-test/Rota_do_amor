<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\SuperAdminDashboardService;
use Throwable;

final class SuperAdminController extends Controller
{
    public function __construct(
        private readonly SuperAdminDashboardService $service = new SuperAdminDashboardService(),
        private readonly AuditService $audit = new AuditService()
    )
    {
    }

    public function dashboard(): void
    {
        try {
            $this->audit->logAdminEvent((int) Session::get('admin_id', 0), 'super_dashboard_viewed', 'dashboard', null, ['origin' => 'super_admin', 'module' => 'super_dashboard']);
        } catch (Throwable $exception) {
            error_log('[admin.super_dashboard.audit_failed] error=' . $exception->getMessage());
        }

        try {
            $metrics = $this->service->build();
        } catch (Throwable $exception) {
            error_log('[admin.super_dashboard.render_fallback] error=' . $exception->getMessage());
            $metrics = [
                'product' => [],
                'operations' => [],
                'finance' => [],
                'diary' => [],
                'risk' => [],
                'trend' => [],
                'executive_blocks' => [],
                'critical_alerts' => [],
                'action_required' => [],
                'warnings' => ['Dashboard carregado em modo degradado por limitação de infraestrutura.'],
            ];
        }

        $this->view('admin/super-dashboard', ['title' => 'Super Admin · Executivo', 'metrics' => $metrics]);
    }
}
