<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\MatchService;
use App\Services\SafeDateService;

final class MatchController extends Controller
{
    public function __construct(
        private readonly MatchService $service = new MatchService(),
        private readonly SafeDateService $safeDates = new SafeDateService()
    )
    {
    }

    public function index(): void
    {
        $userId = Auth::id() ?? 0;
        $matches = $this->service->getUserMatches($userId);
        $safeDateCapabilitiesMap = $this->safeDates->eligibleProfileCapabilitiesMapForUser($userId);
        $safeDateEligibleMap = [];
        foreach ($safeDateCapabilitiesMap as $counterpartId => $capabilities) {
            if (!empty($capabilities['can_standard'])) {
                $safeDateEligibleMap[(int) $counterpartId] = true;
            }
        }
        $this->view('matches/index', [
            'title' => 'Matches',
            'matches' => $matches,
            'safe_date_eligible_map' => $safeDateEligibleMap,
            'safe_date_capabilities_map' => $safeDateCapabilitiesMap,
        ]);
    }
}
