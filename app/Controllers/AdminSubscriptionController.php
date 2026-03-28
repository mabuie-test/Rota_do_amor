<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class AdminSubscriptionController extends Controller
{
    public function index(): void
    {
        $rows = \App\Core\Database::connection()->query('SELECT * FROM subscriptions ORDER BY id DESC LIMIT 500')->fetchAll();
        if (Request::input('format') === 'json') {
            Response::json(['subscriptions' => $rows]);
        }

        $this->view('admin/subscriptions', ['title' => 'Admin · Subscrições', 'subscriptions' => $rows]);
    }
}
