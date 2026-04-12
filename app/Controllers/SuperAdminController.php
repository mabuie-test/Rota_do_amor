<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\SuperAdminDashboardService;

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
        $this->audit->logAdminEvent((int) Session::get('admin_id', 0), 'super_dashboard_viewed', 'dashboard', null, ['origin' => 'super_admin', 'module' => 'super_dashboard']);
        $this->view('admin/super-dashboard', ['title' => 'Super Admin · Executivo', 'metrics' => $this->service->build()]);
    }
}
