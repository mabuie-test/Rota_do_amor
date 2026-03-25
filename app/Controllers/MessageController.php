<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class MessageController
{
    public function index(): void
    {
        $this->json(['controller' => 'MessageController', 'status' => 'ok']);
    }
}
