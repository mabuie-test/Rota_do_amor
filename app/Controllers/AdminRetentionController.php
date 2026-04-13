<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AnonymousStoryService;
use App\Services\AuditService;
use App\Services\CompatibilityDuelService;
use App\Services\ProfileVisitService;

final class AdminRetentionController extends Controller
{
    public function __construct(
        private readonly ProfileVisitService $visitors = new ProfileVisitService(),
        private readonly AnonymousStoryService $stories = new AnonymousStoryService(),
        private readonly CompatibilityDuelService $duels = new CompatibilityDuelService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function visitors(): void
    {
        $filters = [
            'from' => trim((string) Request::input('from', '')),
            'to' => trim((string) Request::input('to', '')),
            'source_context' => trim((string) Request::input('source_context', '')),
            'visitor_user_id' => (int) Request::input('visitor_user_id', 0),
            'visited_user_id' => (int) Request::input('visited_user_id', 0),
            'only_suspicious' => (int) Request::input('only_suspicious', 0),
            'page' => (int) Request::input('page', 1),
            'per_page' => (int) Request::input('per_page', 25),
            'days' => (int) Request::input('days', 30),
        ];

        $result = $this->visitors->adminList($filters);
        $this->audit->logAdminEvent((int) Session::get('admin_id', 0), 'visitors_admin_list_viewed', 'profile_visit', null, ['module' => 'visitors', 'filters' => $filters]);

        if (Request::expectsJson()) {
            Response::json($result);
        }

        $this->view('admin/visitors/index', [
            'title' => 'Admin · Radar de Visitantes',
            'items' => $result['items'],
            'filters' => $result['filters'],
            'pagination' => $result['pagination'],
            'overview' => $result['overview'],
            'leaders' => $result['leaders'],
            'premium_policy' => $result['premium_policy'],
            'source_contexts' => $result['source_contexts'],
        ]);
    }

    public function anonymousStories(): void
    {
        $filters = [
            'status' => trim((string) Request::input('status', '')),
            'category' => trim((string) Request::input('category', '')),
            'report_status' => trim((string) Request::input('report_status', '')),
            'is_featured' => (int) Request::input('is_featured', -1),
            'from' => trim((string) Request::input('from', '')),
            'to' => trim((string) Request::input('to', '')),
            'author_user_id' => (int) Request::input('author_user_id', 0),
            'only_reported' => (int) Request::input('only_reported', 0),
            'page' => (int) Request::input('page', 1),
            'per_page' => (int) Request::input('per_page', 25),
            'days' => (int) Request::input('days', 30),
        ];

        $result = $this->stories->adminList($filters);
        $this->audit->logAdminEvent((int) Session::get('admin_id', 0), 'anonymous_stories_admin_list_viewed', 'anonymous_story', null, ['module' => 'anonymous_stories', 'filters' => $filters]);

        if (Request::expectsJson()) {
            Response::json($result);
        }

        $this->view('admin/anonymous-stories/index', [
            'title' => 'Admin · Histórias Anónimas',
            'items' => $result['items'],
            'filters' => $result['filters'],
            'pagination' => $result['pagination'],
            'overview' => $result['overview'],
            'statuses' => $result['statuses'],
            'categories' => $result['categories'],
            'report_statuses' => $result['report_statuses'],
        ]);
    }

    public function anonymousStoryShow(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $detail = $this->stories->adminDetail($id);

        if ($detail === []) {
            Response::abort(404, 'História não encontrada.');
        }

        $this->audit->logAdminEvent((int) Session::get('admin_id', 0), 'anonymous_story_admin_detail_viewed', 'anonymous_story', $id, ['module' => 'anonymous_stories']);

        if (Request::expectsJson()) {
            Response::json($detail);
        }

        $this->view('admin/anonymous-stories/show', [
            'title' => 'Admin · História Anónima #' . $id,
            'story' => $detail,
        ]);
    }

    public function anonymousStoryAction(array $params): void
    {
        $storyId = (int) ($params['id'] ?? 0);
        $action = trim((string) Request::input('action', ''));
        $note = trim((string) Request::input('note', ''));
        $adminId = (int) Session::get('admin_id', 0);

        $result = $this->stories->applyAdminAction($storyId, $adminId, $action, $note);
        if (Request::expectsJson()) {
            Response::json($result, !empty($result['ok']) ? 200 : 422);
        }

        Flash::set(!empty($result['ok']) ? 'success' : 'error', !empty($result['ok']) ? 'Ação aplicada com sucesso.' : (string) ($result['message'] ?? 'Falha ao aplicar ação.'));
        Response::redirect('/admin/anonymous-stories/' . $storyId);
    }

    public function compatibilityDuels(): void
    {
        $filters = [
            'status' => trim((string) Request::input('status', '')),
            'from' => trim((string) Request::input('from', '')),
            'to' => trim((string) Request::input('to', '')),
            'user_id' => (int) Request::input('user_id', 0),
            'only_with_action' => (int) Request::input('only_with_action', 0),
            'page' => (int) Request::input('page', 1),
            'per_page' => (int) Request::input('per_page', 25),
            'days' => (int) Request::input('days', 30),
        ];

        $result = $this->duels->adminList($filters);
        $this->audit->logAdminEvent((int) Session::get('admin_id', 0), 'compatibility_duels_admin_list_viewed', 'compatibility_duel', null, ['module' => 'compatibility_duel', 'filters' => $filters]);

        if (Request::expectsJson()) {
            Response::json($result);
        }

        $this->view('admin/compatibility-duels/index', [
            'title' => 'Admin · Duelo de Compatibilidade',
            'items' => $result['items'],
            'filters' => $result['filters'],
            'pagination' => $result['pagination'],
            'overview' => $result['overview'],
            'statuses' => $result['statuses'],
            'premium_policy' => $result['premium_policy'],
        ]);
    }
}
