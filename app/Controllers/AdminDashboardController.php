<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AdminDashboardService;

final class AdminDashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardService $service = new AdminDashboardService()) {}

    public function index(): void
    {
        $this->view('admin/dashboard', ['title' => 'Admin Dashboard', 'metrics' => $this->service->getMetrics()]);
    }
}
