<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\DailyRouteService;
use App\Services\FeedService;
use App\Services\RateLimiterService;
use App\Services\UploadService;
use RuntimeException;

final class FeedController extends Controller
{
    public function __construct(
        private readonly FeedService $service = new FeedService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService(),
        private readonly UploadService $uploads = new UploadService(),
        private readonly DailyRouteService $dailyRoutes = new DailyRouteService()
    )
    {
    }

    public function index(): void
    {
        $viewerId = Auth::id() ?? 0;
        $page = max(1, (int) Request::input('page', 1));
        $feed = $this->service->getFeedForUser($viewerId, $page, 15);
        $this->view('feed/index', ['title' => 'Feed', 'feed' => $feed['items'], 'pagination' => $feed['pagination'], 'viewer_id' => $viewerId]);
    }

    public function post(): void
    {
        $userId = Auth::id() ?? 0;
        $key = 'feed_post:' . $userId . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('feed_post', $key, 10, 5)) {
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => 'Limite de publicações temporariamente atingido.'], 429);
            }

            Flash::set('error', 'Limite de publicações temporariamente atingido.');
            Response::redirect('/feed');
        }

        $storedImages = [];
        try {
            if (isset($_FILES['images'])) {
                $storedImages = $this->uploads->storeManyImages($_FILES['images'], 'feed/posts', 4);
            }

            $id = $this->service->createPost($userId, (string) Request::input('content', ''), $storedImages);
            if ($id > 0) {
                $this->rateLimiter->hitSuccess('feed_post', $key, $userId, ['images_count' => count($storedImages)]);
                $this->dailyRoutes->trackAction($userId, 'feed_post', 1);
                if (Request::expectsJson()) {
                    Response::json(['ok' => true, 'post_id' => $id, 'images_count' => count($storedImages)]);
                }

                Flash::set('success', 'Publicação criada com sucesso.');
                Response::redirect('/feed');
            }

            foreach ($storedImages as $file) {
                $this->uploads->deleteImageBundle($file);
            }
            $this->rateLimiter->hitFailure('feed_post', $key, $userId, ['reason' => 'invalid_post_payload']);
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'post_id' => 0], 422);
            }

            Flash::set('error', 'Não foi possível publicar este conteúdo.');
            Response::redirect('/feed');
        } catch (RuntimeException $exception) {
            foreach ($storedImages as $file) {
                $this->uploads->deleteImageBundle($file);
            }
            $this->rateLimiter->hitFailure('feed_post', $key, $userId, ['reason' => 'upload_rejected']);
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => $exception->getMessage()], 422);
            }

            Flash::set('error', $exception->getMessage());
            Response::redirect('/feed');
        }
    }

    public function like(): void
    {
        $key = 'feed_like:' . (Auth::id() ?? 0) . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('feed_like', $key, 60, 5)) {
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => 'Muitos likes em curto período.'], 429);
            }

            Flash::set('error', 'Muitos likes em curto período.');
            Response::redirect('/feed');
        }
        $userId = Auth::id() ?? 0;
        $this->service->likePost((int) Request::input('post_id', 0), $userId);
        $this->rateLimiter->hitSuccess('feed_like', $key, $userId);
        $this->dailyRoutes->trackAction($userId, 'feed_like', 1);
        if (Request::expectsJson()) {
            Response::json(['ok' => true]);
        }

        Response::redirect('/feed');
    }

    public function comment(): void
    {
        $key = 'feed_comment:' . (Auth::id() ?? 0) . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('feed_comment', $key, 25, 5)) {
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => 'Muitos comentários em curto período.'], 429);
            }

            Flash::set('error', 'Muitos comentários em curto período.');
            Response::redirect('/feed');
        }
        $userId = Auth::id() ?? 0;
        $this->service->commentPost((int) Request::input('post_id', 0), $userId, (string) Request::input('comment', ''));
        $this->rateLimiter->hitSuccess('feed_comment', $key, $userId);
        $this->dailyRoutes->trackAction($userId, 'feed_comment', 1);
        if (Request::expectsJson()) {
            Response::json(['ok' => true]);
        }

        Response::redirect('/feed');
    }

    public function delete(): void
    {
        $userId = Auth::id() ?? 0;
        $postId = (int) Request::input('post_id', 0);
        if ($postId <= 0) {
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => 'Post inválido.'], 422);
            }

            Flash::set('error', 'Post inválido.');
            Response::redirect('/feed');
        }

        $this->service->deletePost($postId, $userId);
        if (Request::expectsJson()) {
            Response::json(['ok' => true]);
        }

        Flash::set('success', 'Publicação removida.');
        Response::redirect('/feed');
    }
}
