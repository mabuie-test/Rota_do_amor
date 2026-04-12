<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\RiskCenterService;

final class AdminRiskController extends Controller
{
    public function __construct(private readonly RiskCenterService $service = new RiskCenterService())
    {
    }

    public function index(): void
    {
        $this->view('admin/risk', [
            'title' => 'Super Admin · Risco & Abuso',
            'users' => $this->service->suspiciousUsers(),
        ]);
    }
}
