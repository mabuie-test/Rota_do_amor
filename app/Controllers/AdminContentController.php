<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminContentController
{
    public function index(): void
    {
        $this->json(['controller' => 'AdminContentController', 'status' => 'ok']);
    }
}
