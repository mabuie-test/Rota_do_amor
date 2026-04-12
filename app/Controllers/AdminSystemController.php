<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditService;

final class AdminSystemController extends Controller
{
    public function __construct(private readonly AuditService $audit = new AuditService())
    {
    }

    public function index(): void
    {
        $rows = \App\Core\Database::connection()->query('SELECT * FROM site_settings ORDER BY setting_key')->fetchAll();
        if (Request::input('format') === 'json') {
            Response::json(['settings' => $rows]);
        }

        $this->view('admin/settings', ['title' => 'Admin · Configurações', 'settings' => $rows]);
    }

    public function update(): void
    {
        $key = trim((string) Request::input('setting_key', ''));
        $value = (string) Request::input('setting_value', '');
        $type = (string) Request::input('value_type', 'string');
        if ($key === '') {
            Response::json(['ok' => false, 'message' => 'setting_key é obrigatório'], 422);
        }

        $stmt = \App\Core\Database::connection()->prepare('INSERT INTO site_settings (setting_key,setting_value,value_type,updated_at) VALUES (:k,:v,:t,NOW()) ON DUPLICATE KEY UPDATE setting_value=:v,value_type=:t,updated_at=NOW()');
        $stmt->execute([':k' => $key, ':v' => $value, ':t' => $type]);

        $this->audit->logAdminEvent((int) Session::get('admin_id', 0), 'site_setting_updated', 'site_setting', null, ['key' => $key, 'type' => $type]);
        Response::json(['ok' => true]);
    }
}
