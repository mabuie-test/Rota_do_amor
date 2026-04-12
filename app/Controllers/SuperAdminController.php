<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\SuperAdminDashboardService;

final class SuperAdminController extends Controller
{
    public function __construct(private readonly SuperAdminDashboardService $service = new SuperAdminDashboardService())
    {
    }

    public function dashboard(): void
    {
        $this->view('admin/super-dashboard', ['title' => 'Super Admin · Executivo', 'metrics' => $this->service->build()]);
    }
}
