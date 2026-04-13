<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\SafeDateService;

final class AdminSafeDateController extends Controller
{
    public function __construct(
        private readonly SafeDateService $service = new SafeDateService(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function index(): void
    {
        $filters = [
            'status' => trim((string) Request::input('status', '')),
            'safety_level' => trim((string) Request::input('safety_level', '')),
            'from' => trim((string) Request::input('from', '')),
            'to' => trim((string) Request::input('to', '')),
            'initiator_user_id' => (int) Request::input('initiator_user_id', 0),
            'invitee_user_id' => (int) Request::input('invitee_user_id', 0),
            'page' => (int) Request::input('page', 1),
            'per_page' => (int) Request::input('per_page', 25),
        ];

        $result = $this->service->adminList($filters);
        $this->audit->logAdminEvent((int) Session::get('admin_id', 0), 'safe_dates_admin_list_viewed', 'safe_date', null, ['module' => 'safe_dates', 'filters' => $filters]);

        if (Request::expectsJson()) {
            Response::json([
                'items' => $result['items'],
                'pagination' => $result['pagination'],
                'filters' => $result['filters'],
                'totals' => $result['totals'],
                'premium_policy' => $result['premium_policy'],
            ]);
        }

        $this->view('admin/safe-dates/index', [
            'title' => 'Admin · Encontro Seguro',
            'items' => $result['items'],
            'filters' => $result['filters'],
            'pagination' => $result['pagination'],
            'totals' => $result['totals'],
            'statuses' => $result['statuses'],
            'safety_levels' => $result['safety_levels'],
            'premium_policy' => $result['premium_policy'],
        ]);
    }

    public function show(array $params): void
    {
        $safeDateId = (int) ($params['id'] ?? 0);
        $detail = $this->service->adminDetail($safeDateId);

        if ($detail === []) {
            Response::abort(404, 'Encontro seguro não encontrado.');
        }

        $this->audit->logAdminEvent((int) Session::get('admin_id', 0), 'safe_date_admin_detail_viewed', 'safe_date', $safeDateId, ['module' => 'safe_dates']);

        if (Request::expectsJson()) {
            Response::json($detail);
        }

        $this->view('admin/safe-dates/show', [
            'title' => 'Admin · Encontro Seguro #' . $safeDateId,
            'safe_date' => $detail,
        ]);
    }
}
