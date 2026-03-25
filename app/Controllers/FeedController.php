<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class FeedController
{
    public function index(): void
    {
        $this->json(['controller' => 'FeedController', 'status' => 'ok']);
    }
}
