<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class NotificationController
{
    public function index(): void
    {
        $this->json(['controller' => 'NotificationController', 'status' => 'ok']);
    }
}
