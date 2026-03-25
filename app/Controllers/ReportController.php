<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class ReportController
{
    public function index(): void
    {
        $this->json(['controller' => 'ReportController', 'status' => 'ok']);
    }
}
