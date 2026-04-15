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
        $safeDateContext = $this->safeDates->eligibleProfileContextForUser($userId);
        $this->view('matches/index', [
            'title' => 'Matches',
            'matches' => $matches,
            'safe_date_eligible_map' => $safeDateContext['eligible_map'] ?? [],
            'safe_date_capabilities_map' => $safeDateContext['capabilities_map'] ?? [],
        ]);
    }
}
