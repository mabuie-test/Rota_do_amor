<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminModerationController
{
    public function index(): void
    {
        $this->json(['controller' => 'AdminModerationController', 'status' => 'ok']);
    }
}
