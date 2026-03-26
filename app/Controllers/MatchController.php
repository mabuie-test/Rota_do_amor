<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\MatchService;

final class MatchController extends Controller
{
    public function __construct(private readonly MatchService $service = new MatchService())
    {
    }

    public function index(): void
    {
        $matches = $this->service->getUserMatches(Auth::id() ?? 0);
        $this->view('matches/index', ['title' => 'Matches', 'matches' => $matches]);
    }
}
