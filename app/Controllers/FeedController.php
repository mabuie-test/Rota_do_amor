<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\DailyRouteEventBridge;
use App\Services\FeedReactionService;
use App\Services\FeedService;
use App\Services\PostPrivateInterestService;
use App\Services\RateLimiterService;
use App\Services\UploadService;
use App\Services\UserSocialAvailabilityService;
use RuntimeException;

final class FeedController extends Controller
{
    public function __construct(
        private readonly FeedService $service = new FeedService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService(),
        private readonly UploadService $uploads = new UploadService(),
        private readonly DailyRouteEventBridge $dailyRoutes = new DailyRouteEventBridge()
    ) {
    }

    public function index(): void
    {
        $viewerId = Auth::id() ?? 0;
        $page = max(1, (int) Request::input('page', 1));
        $selectedPostId = max(0, (int) Request::input('post', 0));
        $selectedCommentId = max(0, (int) Request::input('comment', 0));
        $expandSelected = (string) Request::input('show_comments', '') === 'all' || $selectedCommentId > 0;
        $tab = (string) Request::input('tab', 'for_you');

        $feed = $this->service->getFeedForUser($viewerId, $page, 15, $selectedPostId, $selectedCommentId, $expandSelected, $tab);
        $this->view('feed/index', [
            'title' => 'Feed',
            'feed' => $feed['items'],
            'pagination' => $feed['pagination'],
            'viewer_id' => $viewerId,
            'selected_post_id' => $selectedPostId,
            'selected_comment_id' => $selectedCommentId,
            'feed_tabs' => $feed['tabs'] ?? [],
            'selected_tab' => $feed['selected_tab'] ?? 'for_you',
            'prompts' => $this->service->getFeedPrompts(),
            'reaction_types' => FeedReactionService::TYPES,
            'interest_types' => PostPrivateInterestService::TYPES,
            'availability_types' => UserSocialAvailabilityService::TYPES,
            'shareable_diary_entries' => $this->service->listShareableDiaryEntries($viewerId, 24),
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
                foreach ($storedImages as $index => $storedImage) {
                    $path = trim((string) ($storedImage['path'] ?? ''));
                    if ($path === '') {
                        throw new RuntimeException(sprintf('Falha no upload da imagem #%d.', $index + 1));
                    }
                }
            }

            $meta = [
                'post_mood' => (string) Request::input('post_mood', ''),
                'relational_phase' => (string) Request::input('relational_phase', ''),
                'origin_type' => (string) Request::input('origin_type', 'normal'),
                'prompt_id' => (int) Request::input('prompt_id', 0),
                'prompt_answer_text' => (string) Request::input('prompt_answer_text', ''),
                'diary_entry_id' => (int) Request::input('diary_entry_id', 0),
                'diary_share_mode' => (string) Request::input('diary_share_mode', 'publico'),
                'diary_is_anonymous' => (int) Request::input('diary_is_anonymous', 0) === 1,
            ];

            if ((int) Request::input('has_poll', 0) === 1) {
                $meta['poll'] = [
                    'question' => (string) Request::input('poll_question', ''),
                    'options' => [
                        (string) Request::input('poll_option_1', ''),
                        (string) Request::input('poll_option_2', ''),
                        (string) Request::input('poll_option_3', ''),
                        (string) Request::input('poll_option_4', ''),
                    ],
                    'ends_at' => Request::input('poll_ends_at') ?: null,
                ];
            }

            $id = $this->service->createPost($userId, (string) Request::input('content', ''), $storedImages, $meta);
            if ($id > 0) {
                $this->rateLimiter->hitSuccess('feed_post', $key, $userId, ['images_count' => count($storedImages)]);
                $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_FEED_POST, 'feed', 1);
                if ((int) ($meta['prompt_id'] ?? 0) > 0) {
                    $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_FEED_PROMPT_POST, 'feed', 1);
                }
                if (isset($meta['poll'])) {
                    $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_FEED_POLL_CREATED, 'feed', 1);
                }
                if ((int) ($meta['diary_entry_id'] ?? 0) > 0) {
                    $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_FEED_DIARY_SHARED, 'feed', 1);
                }

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

    public function react(): void
    {
        $userId = Auth::id() ?? 0;
        $postId = (int) Request::input('post_id', 0);
        $reactionType = (string) Request::input('reaction_type', '');
        $result = $this->service->toggleReaction($postId, $userId, $reactionType);

        if (!($result['success'] ?? false)) {
            Response::json(['ok' => false] + $result, 422);
        }

        if (($result['action'] ?? '') !== 'reaction_removed') {
            $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_FEED_REACTION, 'feed', 1);
        }

        Response::json(['ok' => true] + $result);
    }

    public function votePoll(): void
    {
        $userId = Auth::id() ?? 0;
        $pollId = (int) Request::input('poll_id', 0);
        $optionId = (int) Request::input('option_id', 0);
        $result = $this->service->votePoll($pollId, $optionId, $userId);

        if (!($result['success'] ?? false)) {
            Response::json(['ok' => false] + $result, 422);
        }

        $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_FEED_POLL_VOTED, 'feed', 1);
        Response::json(['ok' => true] + $result);
    }


    public function pollState(): void
    {
        $pollId = (int) Request::input('poll_id', 0);
        $viewerId = Auth::id() ?? 0;
        $state = $this->service->pollState($pollId, $viewerId);
        if ($state === []) {
            Response::json(['ok' => false, 'message' => 'Enquete não encontrada.'], 404);
        }

        Response::json(['ok' => true, 'poll' => $state]);
    }

    public function privateInterest(): void
    {
        $userId = Auth::id() ?? 0;
        $result = $this->service->sendPrivateInterest((int) Request::input('post_id', 0), $userId, (string) Request::input('interest_type', ''), (string) Request::input('message_optional', ''));

        if (!($result['success'] ?? false)) {
            Response::json(['ok' => false] + $result, 422);
        }

        $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_FEED_PRIVATE_INTEREST, 'feed', 1);
        Response::json(['ok' => true] + $result);
    }

    public function activateAvailability(): void
    {
        $userId = Auth::id() ?? 0;
        $ok = $this->service->activateSocialAvailability($userId, (string) Request::input('availability_type', ''), (int) Request::input('duration_minutes', 180));

        if (!$ok) {
            Response::json(['ok' => false, 'message' => 'Não foi possível ativar disponibilidade.'], 422);
        }

        $this->dailyRoutes->trackFromModule($userId, DailyRouteEventBridge::EVENT_FEED_SOCIAL_AVAILABILITY, 'feed', 1);
        $activeState = $this->service->activeAvailabilityForUser($userId);
        Response::json(['ok' => true, 'message' => 'Estado social ativado por tempo limitado.', 'state' => [
            'availability_type' => (string) ($activeState['availability_type'] ?? Request::input('availability_type', '')),
            'duration_minutes' => (int) Request::input('duration_minutes', 180),
            'ends_at' => $activeState['ends_at'] ?? null,
        ]]);
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
