<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\FeedService;

final class FeedController extends Controller
{
    public function __construct(private readonly FeedService $service = new FeedService())
    {
    }

    public function index(): void
    {
        $feed = $this->service->getFeedForUser(Auth::id() ?? 0);
        $this->view('feed/index', ['title' => 'Feed', 'feed' => $feed]);
    }

    public function post(): void
    {
        $id = $this->service->createPost(Auth::id() ?? 0, (string) Request::input('content', ''));
        Response::json(['ok' => true, 'post_id' => $id]);
    }

    public function like(): void
    {
        $this->service->likePost((int) Request::input('post_id', 0), Auth::id() ?? 0);
        Response::json(['ok' => true]);
    }

    public function comment(): void
    {
        $this->service->commentPost((int) Request::input('post_id', 0), Auth::id() ?? 0, (string) Request::input('comment', ''));
        Response::json(['ok' => true]);
    }
}
