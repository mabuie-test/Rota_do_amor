<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class BlockController
{
    public function index(): void
    {
        $this->json(['controller' => 'BlockController', 'status' => 'ok']);
    }
}
