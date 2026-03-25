<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminReportController
{
    public function index(): void
    {
        $this->json(['controller' => 'AdminReportController', 'status' => 'ok']);
    }
}
