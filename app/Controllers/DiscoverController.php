<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Services\DailyRouteService;
use App\Services\DiscoveryService;

final class DiscoverController extends Controller
{
    public function __construct(
        private readonly DiscoveryService $service = new DiscoveryService(),
        private readonly DailyRouteService $dailyRoutes = new DailyRouteService()
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
        $this->dailyRoutes->trackAction(Auth::id() ?? 0, 'discover_view', min(3, count($profiles)));
        $this->view('discover/index', ['title' => 'Descobrir', 'profiles' => $profiles, 'filters' => $filters]);
    }
}
