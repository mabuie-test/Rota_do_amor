<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class AdminSystemController extends Controller
{
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
        $key = (string) Request::input('setting_key', '');
        $value = (string) Request::input('setting_value', '');
        $type = (string) Request::input('value_type', 'string');
        $stmt = \App\Core\Database::connection()->prepare('INSERT INTO site_settings (setting_key,setting_value,value_type,updated_at) VALUES (:k,:v,:t,NOW()) ON DUPLICATE KEY UPDATE setting_value=:v,value_type=:t,updated_at=NOW()');
        $stmt->execute([':k' => $key, ':v' => $value, ':t' => $type]);
        Response::json(['ok' => true]);
    }
}
