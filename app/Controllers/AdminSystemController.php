<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminSystemController
{
    public function index(): void
    {
        $this->json(['controller' => 'AdminSystemController', 'status' => 'ok']);
    }
}
