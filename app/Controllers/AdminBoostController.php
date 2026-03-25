<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class AdminBoostController extends Controller
{
    public function index(): void
    {
        $rows = \App\Core\Database::connection()->query('SELECT * FROM user_boosts ORDER BY id DESC LIMIT 500')->fetchAll();
        if (Request::input('format') === 'json') {
            Response::json(['boosts' => $rows]);
        }

        $this->view('admin/boosts', ['title' => 'Admin · Boosts', 'boosts' => $rows]);
    }
}
