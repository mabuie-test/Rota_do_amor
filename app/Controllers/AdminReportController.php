<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;

final class AdminReportController extends Controller
{
    public function index(): void
    {
        $db = \App\Core\Database::connection();
        $rows = $db->query('SELECT * FROM reports ORDER BY id DESC LIMIT 500')->fetchAll();
        if (\App\Core\Request::input('format') === 'json') {
            Response::json(['reports' => $rows]);
        }
        $this->view('admin/reports', ['title' => 'Admin · Denúncias', 'reports' => $rows]);
    }

    public function resolve(array $params): void
    {
        $stmt = \App\Core\Database::connection()->prepare("UPDATE reports SET status='resolved',resolved_by_admin_id=:admin,resolved_at=NOW() WHERE id=:id");
        $stmt->execute([':admin' => Session::get('admin_id'), ':id' => (int) ($params['id'] ?? 0)]);
        Response::json(['ok' => true]);
    }
}
