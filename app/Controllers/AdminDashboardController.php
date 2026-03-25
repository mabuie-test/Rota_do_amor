<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminDashboardController
{
    public function index(): void
    {
        $this->json(['controller' => 'AdminDashboardController', 'status' => 'ok']);
    }
}
