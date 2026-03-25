<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class ConnectionController
{
    public function index(): void
    {
        $this->json(['controller' => 'ConnectionController', 'status' => 'ok']);
    }
}
