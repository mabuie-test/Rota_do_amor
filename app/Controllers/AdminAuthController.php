<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminAuthController
{
    public function index(): void
    {
        $this->json(['controller' => 'AdminAuthController', 'status' => 'ok']);
    }
}
