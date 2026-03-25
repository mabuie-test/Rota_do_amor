<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class MatchController
{
    public function index(): void
    {
        $this->json(['controller' => 'MatchController', 'status' => 'ok']);
    }
}
