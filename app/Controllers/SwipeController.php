<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class SwipeController
{
    public function index(): void
    {
        $this->json(['controller' => 'SwipeController', 'status' => 'ok']);
    }
}
