<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\DiscoveryService;

final class DiscoverController extends Controller
{
    public function __construct(private readonly DiscoveryService $service = new DiscoveryService())
    {
    }

    public function index(): void
    {
        $profiles = $this->service->getSuggestedProfiles(Auth::id() ?? 0);
        $this->view('discover/index', ['title' => 'Descobrir', 'profiles' => $profiles]);
    }
}
