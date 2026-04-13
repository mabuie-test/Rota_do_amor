<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\AnonymousStoryService;
use App\Services\DailyRouteEventBridge;
use App\Services\RateLimiterService;

final class AnonymousStoryController extends Controller
{
    public function __construct(
        private readonly AnonymousStoryService $service = new AnonymousStoryService(),
        private readonly RateLimiterService $rateLimiter = new RateLimiterService(),
        private readonly DailyRouteEventBridge $dailyRoutes = new DailyRouteEventBridge()
    ) {
    }

    public function index(): void
    {
        $userId = Auth::id() ?? 0;
        $page = max(1, (int) Request::input('page', 1));
        $stories = $this->service->listStories($userId, $page, 15);
        $this->view('stories/anonymous/index', ['title' => 'Histórias Anónimas', 'stories' => $stories['items'], 'pagination' => $stories['pagination']]);
    }

    public function store(): void
    {
        $userId = Auth::id() ?? 0;
        $key = 'anonymous_story_publish:' . $userId . ':' . Request::ip();
        if ($this->rateLimiter->tooManyAttempts('anonymous_story_publish', $key, 4, 10)) {
            Flash::set('error', 'Limite de publicação temporariamente atingido.');
            Response::redirect('/stories/anonymous');
        }

        $id = $this->service->publish($userId, Request::all());
        if ($id > 0) {
            $this->rateLimiter->hitSuccess('anonymous_story_publish', $key, $userId);
            $this->dailyRoutes->trackFromModule($userId, 'anonymous_story_published', 'anonymous_stories', 1);
            Flash::set('success', 'História anónima publicada.');
        } else {
            $this->rateLimiter->hitFailure('anonymous_story_publish', $key, $userId);
            Flash::set('error', 'Não foi possível publicar a história.');
        }

        Response::redirect('/stories/anonymous');
    }

    public function react(): void
    {
        $userId = Auth::id() ?? 0;
        $ok = $this->service->react((int) Request::input('story_id', 0), $userId, (string) Request::input('reaction_type', ''));
        if ($ok) {
            $this->dailyRoutes->trackFromModule($userId, 'anonymous_story_interacted', 'anonymous_stories', 1);
        }

        if (Request::expectsJson()) {
            Response::json(['ok' => $ok], $ok ? 200 : 422);
        }

        Flash::set($ok ? 'success' : 'error', $ok ? 'Reação registada.' : 'Reação inválida.');
        Response::redirect('/stories/anonymous');
    }

    public function comment(): void
    {
        $userId = Auth::id() ?? 0;
        $ok = $this->service->comment((int) Request::input('story_id', 0), $userId, (string) Request::input('comment', ''));
        if ($ok) {
            $this->dailyRoutes->trackFromModule($userId, 'anonymous_story_interacted', 'anonymous_stories', 1);
        }
        Flash::set($ok ? 'success' : 'error', $ok ? 'Comentário enviado.' : 'Comentário inválido.');
        Response::redirect('/stories/anonymous');
    }

    public function report(): void
    {
        $userId = Auth::id() ?? 0;
        $id = $this->service->report((int) Request::input('story_id', 0), $userId, (string) Request::input('reason', 'conteudo_inadequado'), (string) Request::input('details', ''));
        Flash::set($id > 0 ? 'success' : 'error', $id > 0 ? 'Denúncia enviada para moderação.' : 'Não foi possível enviar denúncia.');
        Response::redirect('/stories/anonymous');
    }
}
