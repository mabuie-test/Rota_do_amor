<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\DailyRouteService;

final class DailyRouteController extends Controller
{
    public function __construct(private readonly DailyRouteService $service = new DailyRouteService())
    {
    }

    public function index(): void
    {
        $userId = Auth::id() ?? 0;
        $summary = $this->service->getDashboardSummary($userId);

        $this->view('daily-route/index', [
            'title' => 'Rota Diária',
            'daily_route' => $summary,
        ]);
    }

    public function claimReward(): void
    {
        $userId = Auth::id() ?? 0;
        $result = $this->service->claimReward($userId);

        if (Request::expectsJson()) {
            Response::json($result, !empty($result['ok']) ? 200 : 422);
        }

        Flash::set(!empty($result['ok']) ? 'success' : 'error', (string) ($result['message'] ?? 'Não foi possível resgatar a recompensa.'));
        Response::redirect('/daily-route');
    }
}
