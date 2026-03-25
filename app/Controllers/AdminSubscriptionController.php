<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;

final class AdminSubscriptionController extends Controller
{
    public function index(): void
    {
        $rows = \App\Core\Database::connection()->query('SELECT * FROM subscriptions ORDER BY id DESC LIMIT 500')->fetchAll();
        Response::json(['subscriptions' => $rows]);
    }
}
