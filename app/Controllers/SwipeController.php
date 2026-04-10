<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\RateLimiterService;
use App\Services\SwipeService;

final class SwipeController extends Controller
{
    public function __construct(
        private readonly SwipeService $service = new SwipeService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService()
    )
    {
    }

    public function index(): void
    {
        $candidate = $this->service->getNextSwipeCandidate(Auth::id() ?? 0);
        $this->view('swipe/index', ['title' => 'Swipe', 'candidate' => $candidate]);
    }

    public function action(): void
    {
        $userId = Auth::id() ?? 0;
        $key = 'swipe_action:' . $userId . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('swipe_action', $key, 120, 10)) {
            Response::json(['ok' => false, 'message' => 'Muitas acções de swipe.'], 429);
        }

        $id = $this->service->recordSwipe(
            $userId,
            (int) Request::input('target_id', 0),
            (string) Request::input('action_type', 'pass')
        );
        $this->rateLimiter->hit('swipe_action', $key, $userId);

        Response::json(['ok' => true, 'swipe_id' => $id]);
    }
}
