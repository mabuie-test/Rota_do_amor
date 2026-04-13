<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Services\DailyRouteEventBridge;
use App\Services\DiscoveryService;
use App\Services\ProfileVisitService;

final class DiscoverController extends Controller
{
    public function __construct(
        private readonly DiscoveryService $service = new DiscoveryService(),
        private readonly DailyRouteEventBridge $dailyRoutes = new DailyRouteEventBridge(),
        private readonly ProfileVisitService $visits = new ProfileVisitService()
    )
    {
    }

    public function index(): void
    {
        $filters = [
            'exclude_user_id' => Auth::id() ?? 0,
            'age_min' => Request::input('age_min'),
            'age_max' => Request::input('age_max'),
            'province_id' => Request::input('province_id'),
            'city_id' => Request::input('city_id'),
            'relationship_goal' => Request::input('relationship_goal'),
            'verified_only' => Request::input('verified_only'),
            'only_online' => Request::input('only_online'),
        ];
        $profiles = $this->service->searchProfiles($filters);
        $this->dailyRoutes->trackFromModule(Auth::id() ?? 0, DailyRouteEventBridge::EVENT_DISCOVER_VIEW, 'discover', min(3, count($profiles)));
        $this->view('discover/index', ['title' => 'Descobrir', 'profiles' => $profiles, 'filters' => $filters]);
    }

    public function show(array $params = []): void
    {
        $viewerId = Auth::id() ?? 0;
        $targetId = (int) ($params['id'] ?? 0);
        $profile = $this->service->getProfileForViewer($viewerId, $targetId);
        if ($profile === []) {
            $this->view('discover/show', ['title' => 'Perfil indisponível', 'profile' => []]);
            return;
        }

        $registered = $this->visits->registerVisit($viewerId, $targetId, 'discover_profile');
        if ($registered) {
            $this->dailyRoutes->trackFromModule($viewerId, 'visitor_profile_engaged', 'discover', 1);
        }

        $this->view('discover/show', ['title' => 'Perfil', 'profile' => $profile]);
    }

}
