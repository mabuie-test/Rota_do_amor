<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\SwipeService;

final class SwipeController extends Controller
{
    public function __construct(private readonly SwipeService $service = new SwipeService())
    {
    }

    public function index(): void
    {
        $candidate = $this->service->getNextSwipeCandidate(Auth::id() ?? 0);
        $this->view('swipe/index', ['title' => 'Swipe', 'candidate' => $candidate]);
    }

    public function action(): void
    {
        $id = $this->service->recordSwipe(
            Auth::id() ?? 0,
            (int) Request::input('target_id', 0),
            (string) Request::input('action_type', 'pass')
        );

        Response::json(['ok' => true, 'swipe_id' => $id]);
    }
}
