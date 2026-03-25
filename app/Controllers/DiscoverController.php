<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class DiscoverController
{
    public function index(): void
    {
        $this->json(['controller' => 'DiscoverController', 'status' => 'ok']);
    }
}
