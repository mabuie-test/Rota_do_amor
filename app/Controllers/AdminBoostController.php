<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminBoostController
{
    public function index(): void
    {
        $this->json(['controller' => 'AdminBoostController', 'status' => 'ok']);
    }
}
