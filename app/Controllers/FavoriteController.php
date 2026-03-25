<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class FavoriteController
{
    public function index(): void
    {
        $this->json(['controller' => 'FavoriteController', 'status' => 'ok']);
    }
}
