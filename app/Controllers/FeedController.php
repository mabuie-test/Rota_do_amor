<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\DailyRouteEventBridge;
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
        private readonly DailyRouteEventBridge $dailyRoutes = new DailyRouteEventBridge()
    )
    {
    }

    public function index(): void
    {
        $viewerId = Auth::id() ?? 0;
        $page = max(1, (int) Request::input('page', 1));
        $selectedPostId = max(0, (int) Request::input('post', 0));
        $selectedCommentId = max(0, (int) Request::input('comment', 0));
        $expandSelected = (string) Request::input('show_comments', '') === 'all' || $selectedCommentId > 0;

        $feed = $this->service->getFeedForUser($viewerId, $page, 15, $selectedPostId, $selectedCommentId, $expandSelected);
        $this->view('feed/index', [
            'title' => 'Feed',
            'feed' => $feed['items'],
            'pagination' => $feed['pagination'],
            'viewer_id' => $viewerId,
            'selected_post_id' => $selectedPostId,
            'selected_comment_id' => $selectedCommentId,
        ]);
    }

    public function post(): void
    {
        $userId = Auth::id() ?? 0;
        $key = 'feed_post:' . $userId . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('feed_post', $key, 10, 5)) {
            if (Request::expectsJson()) {
                $this->jsonOutcome(false, 'Limite de publicações temporariamente atingido.', 'feed_post_create', null, 0, 0, 'rate_limited', [], 429);
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
                $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_FEED_POST, 'feed', 1);
                if (Request::expectsJson()) {
                    $this->jsonOutcome(true, 'Publicação criada com sucesso.', 'feed_post_created', ['images_count' => count($storedImages)], $id, $id, null, ['post_id' => $id]);
                }

                Flash::set('success', 'Publicação criada com sucesso.');
                Response::redirect('/feed');
            }

            foreach ($storedImages as $file) {
                $this->uploads->deleteImageBundle($file);
            }
            $this->rateLimiter->hitFailure('feed_post', $key, $userId, ['reason' => 'invalid_post_payload']);
            if (Request::expectsJson()) {
                $this->jsonOutcome(false, 'Não foi possível publicar este conteúdo.', 'feed_post_create', null, 0, 0, 'invalid_post_payload', ['post_id' => 0], 422);
            }

            Flash::set('error', 'Não foi possível publicar este conteúdo.');
            Response::redirect('/feed');
        } catch (RuntimeException $exception) {
            foreach ($storedImages as $file) {
                $this->uploads->deleteImageBundle($file);
            }
            $this->rateLimiter->hitFailure('feed_post', $key, $userId, ['reason' => 'upload_rejected']);
            if (Request::expectsJson()) {
                $this->jsonOutcome(false, $exception->getMessage(), 'feed_post_create', null, 0, 0, 'upload_failed', [], 422);
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
                Response::json(['ok' => false, 'message' => 'Muitos likes em curto período.', 'action' => 'error', 'state' => null, 'created_id' => 0, 'target_id' => (int) Request::input('post_id', 0), 'error_code' => 'rate_limited'], 429);
            }

            Flash::set('error', 'Muitos likes em curto período.');
            Response::redirect('/feed');
        }

        $userId = Auth::id() ?? 0;
        $postId = (int) Request::input('post_id', 0);
        $result = $this->service->toggleLikePost($postId, $userId);

        if (!($result['success'] ?? false)) {
            $this->rateLimiter->hitFailure('feed_like', $key, $userId, ['reason' => 'invalid_like_request']);
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => $result['message'] ?? 'Não foi possível atualizar like.', 'action' => (string) ($result['action'] ?? 'error'), 'state' => null, 'created_id' => 0, 'target_id' => (int) ($result['post_id'] ?? $postId), 'error_code' => $result['error_code'] ?? 'feed_like_failed'], 422);
            }

            Flash::set('error', $result['message'] ?? 'Não foi possível atualizar like.');
            Response::redirect('/feed');
        }

        $this->rateLimiter->hitSuccess('feed_like', $key, $userId, ['action' => $result['action'] ?? 'liked']);
        if (($result['action'] ?? '') === 'liked') {
            $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_FEED_LIKE, 'feed', 1);
        }

        if (Request::expectsJson()) {
            Response::json(['ok' => true, 'state' => ['liked_by_viewer' => (int) ($result['liked_by_viewer'] ?? 0), 'likes_count' => (int) ($result['likes_count'] ?? 0)], 'created_id' => 0, 'target_id' => (int) ($result['post_id'] ?? 0), 'error_code' => null] + $result);
        }

        Flash::set('success', (string) ($result['message'] ?? 'Like atualizado.'));
        Response::redirect('/feed?post=' . $postId . '#post-' . $postId);
    }

    public function comment(): void
    {
        $key = 'feed_comment:' . (Auth::id() ?? 0) . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('feed_comment', $key, 25, 5)) {
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => 'Muitos comentários em curto período.', 'action' => 'error', 'state' => null, 'created_id' => 0, 'target_id' => (int) Request::input('post_id', 0), 'error_code' => 'rate_limited'], 429);
            }

            Flash::set('error', 'Muitos comentários em curto período.');
            Response::redirect('/feed');
        }

        $userId = Auth::id() ?? 0;
        $postId = (int) Request::input('post_id', 0);
        $parentCommentId = (int) Request::input('parent_comment_id', 0);
        $result = $this->service->commentPost($postId, $userId, (string) Request::input('comment', ''), $parentCommentId);

        if (!($result['success'] ?? false)) {
            $this->rateLimiter->hitFailure('feed_comment', $key, $userId, ['reason' => $result['error_code'] ?? 'comment_failed']);
            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => $result['message'] ?? 'Não foi possível enviar comentário.', 'action' => (string) ($result['action'] ?? 'error'), 'state' => null, 'created_id' => 0, 'target_id' => (int) ($result['post_id'] ?? $postId), 'error_code' => $result['error_code'] ?? 'feed_comment_failed'], 422);
            }

            Flash::set('error', $result['message'] ?? 'Não foi possível enviar comentário.');
            Response::redirect('/feed?post=' . $postId . '#post-' . $postId);
        }

        $this->rateLimiter->hitSuccess('feed_comment', $key, $userId, ['action' => $result['action'] ?? 'commented']);
        $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_FEED_COMMENT, 'feed', 1);
        if (Request::expectsJson()) {
            Response::json(['ok' => true, 'state' => ['post_id' => (int) ($result['post_id'] ?? 0)], 'created_id' => (int) ($result['created_id'] ?? 0), 'target_id' => (int) ($result['target_id'] ?? 0), 'error_code' => null] + $result);
        }

        Flash::set('success', (string) ($result['message'] ?? 'Comentário enviado.'));
        $commentTarget = (int) ($result['created_id'] ?? 0);
        $query = '/feed?post=' . $postId;
        if ($commentTarget > 0) {
            $query .= '&comment=' . $commentTarget;
        }

        Response::redirect($query . '#post-' . $postId);
    }

    public function delete(): void
    {
        $userId = Auth::id() ?? 0;
        $postId = (int) Request::input('post_id', 0);
        if ($postId <= 0) {
            if (Request::expectsJson()) {
                $this->jsonOutcome(false, 'Post inválido.', 'feed_post_delete', null, 0, $postId, 'invalid_post', [], 422);
            }

            Flash::set('error', 'Post inválido.');
            Response::redirect('/feed');
        }

        $this->service->deletePost($postId, $userId);
        if (Request::expectsJson()) {
            $this->jsonOutcome(true, 'Publicação removida.', 'feed_post_deleted', null, 0, $postId);
        }

        Flash::set('success', 'Publicação removida.');
        Response::redirect('/feed');
    }
}
