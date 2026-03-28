<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

final class AdminPaymentController extends Controller
{
    public function index(): void
    {
        $rows = \App\Core\Database::connection()->query('SELECT * FROM payments ORDER BY id DESC LIMIT 500')->fetchAll();
        if (Request::input('format') === 'json') {
            Response::json(['payments' => $rows]);
        }

        $this->view('admin/payments', ['title' => 'Admin · Pagamentos', 'payments' => $rows]);
    }
}
