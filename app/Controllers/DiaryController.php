<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\DailyRouteEventBridge;
use App\Services\DiaryService;

final class DiaryController extends Controller
{
    public function __construct(
        private readonly DiaryService $service = new DiaryService(),
        private readonly DailyRouteEventBridge $dailyRoutes = new DailyRouteEventBridge()
    )
    {
    }

    public function index(): void
    {
        $userId = Auth::id() ?? 0;
        $filters = [
            'mood' => trim((string) Request::input('mood', '')),
            'from' => trim((string) Request::input('from', '')),
            'to' => trim((string) Request::input('to', '')),
        ];

        $this->view('diary/index', [
            'title' => 'Diário do Coração',
            'entries' => $this->service->listEntries($userId, $filters),
            'filters' => $filters,
            'summary' => $this->service->dashboardSummary($userId),
        ]);
    }

    public function new(): void
    {
        $this->view('diary/new', ['title' => 'Novo registo do Diário do Coração']);
    }

    public function create(): void
    {
        $userId = Auth::id() ?? 0;
        $content = trim((string) Request::input('content', ''));
        if ($content === '') {
            Flash::set('error', 'Conteúdo é obrigatório.');
            Response::redirect('/diary/new');
        }

        $this->service->createEntry($userId, $this->payloadFromRequest($content));
        $this->dailyRoutes->track($userId, 'diary_written', 1);
        Flash::set('success', 'Entrada registada com sucesso.');
        Response::redirect('/diary');
    }

    public function show(array $params): void
    {
        $userId = Auth::id() ?? 0;
        $entryId = (int) ($params['id'] ?? 0);
        $entry = $this->service->getEntry($entryId, $userId);
        if (!$entry) {
            Response::abort(404, 'Entrada não encontrada.');
        }

        $this->view('diary/show', ['title' => 'Diário do Coração', 'entry' => $entry]);
    }

    public function update(array $params): void
    {
        $userId = Auth::id() ?? 0;
        $entryId = (int) ($params['id'] ?? 0);
        $content = trim((string) Request::input('content', ''));
        if ($content === '') {
            Flash::set('error', 'Conteúdo é obrigatório.');
            Response::redirect('/diary/' . $entryId);
        }

        $this->service->updateEntry($entryId, $userId, $this->payloadFromRequest($content));
        Flash::set('success', 'Entrada actualizada.');
        Response::redirect('/diary/' . $entryId);
    }

    public function delete(array $params): void
    {
        $userId = Auth::id() ?? 0;
        $entryId = (int) ($params['id'] ?? 0);
        $this->service->deleteEntry($entryId, $userId);
        Flash::set('success', 'Entrada removida.');
        Response::redirect('/diary');
    }

    public function archive(array $params): void
    {
        $userId = Auth::id() ?? 0;
        $entryId = (int) ($params['id'] ?? 0);
        $this->service->archiveEntry($entryId, $userId);
        Flash::set('success', 'Entrada arquivada com sucesso.');
        Response::redirect('/diary');
    }

    private function payloadFromRequest(string $content): array
    {
        $tags = array_values(array_filter(array_map('trim', explode(',', (string) Request::input('tags', '')))));
        return [
            'title' => trim((string) Request::input('title', '')),
            'content' => $content,
            'mood' => trim((string) Request::input('mood', '')),
            'emotional_state' => trim((string) Request::input('emotional_state', '')),
            'relational_focus' => trim((string) Request::input('relational_focus', '')),
            'tags' => $tags,
        ];
    }
}
