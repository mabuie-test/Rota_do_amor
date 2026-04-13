<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Services\DailyRouteEventBridge;
use App\Services\PremiumService;
use App\Services\ProfileVisitService;

final class VisitorsController extends Controller
{
    public function __construct(
        private readonly ProfileVisitService $service = new ProfileVisitService(),
        private readonly PremiumService $premium = new PremiumService(),
        private readonly DailyRouteEventBridge $dailyRoutes = new DailyRouteEventBridge()
    ) {
    }

    public function index(): void
    {
        $userId = Auth::id() ?? 0;
        $isPremium = $this->premium->userHasPremium($userId);
        $summary = $this->service->getSummaryForUser($userId, $isPremium);
        $policy = $this->service->premiumPolicy();
        $this->dailyRoutes->trackFromModule($userId, 'visitor_profile_viewed', 'visitors', 1);

        $this->view('visitors/index', ['title' => 'Radar de Visitantes', 'summary' => $summary, 'is_premium' => $isPremium, 'premium_policy' => $policy]);
    }

    public function summary(): void
    {
        $userId = Auth::id() ?? 0;
        $isPremium = $this->premium->userHasPremium($userId);
        Response::json(['ok' => true, 'summary' => $this->service->getSummaryForUser($userId, $isPremium)]);
    }
}
