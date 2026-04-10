<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\FeedService;
use App\Services\RateLimiterService;

final class FeedController extends Controller
{
    public function __construct(
        private readonly FeedService $service = new FeedService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService()
    )
    {
    }

    public function index(): void
    {
        $feed = $this->service->getFeedForUser(Auth::id() ?? 0);
        $this->view('feed/index', ['title' => 'Feed', 'feed' => $feed]);
    }

    public function post(): void
    {
        $key = 'feed_post:' . (Auth::id() ?? 0) . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('feed_post', $key, 10, 5)) {
            Response::json(['ok' => false, 'message' => 'Limite de publicações temporariamente atingido.'], 429);
        }
        $id = $this->service->createPost(Auth::id() ?? 0, (string) Request::input('content', ''));
        $this->rateLimiter->hit('feed_post', $key, Auth::id() ?? 0);
        Response::json(['ok' => true, 'post_id' => $id]);
    }

    public function like(): void
    {
        $key = 'feed_like:' . (Auth::id() ?? 0) . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('feed_like', $key, 60, 5)) {
            Response::json(['ok' => false, 'message' => 'Muitos likes em curto período.'], 429);
        }
        $this->service->likePost((int) Request::input('post_id', 0), Auth::id() ?? 0);
        $this->rateLimiter->hit('feed_like', $key, Auth::id() ?? 0);
        Response::json(['ok' => true]);
    }

    public function comment(): void
    {
        $key = 'feed_comment:' . (Auth::id() ?? 0) . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('feed_comment', $key, 25, 5)) {
            Response::json(['ok' => false, 'message' => 'Muitos comentários em curto período.'], 429);
        }
        $this->service->commentPost((int) Request::input('post_id', 0), Auth::id() ?? 0, (string) Request::input('comment', ''));
        $this->rateLimiter->hit('feed_comment', $key, Auth::id() ?? 0);
        Response::json(['ok' => true]);
    }
}
