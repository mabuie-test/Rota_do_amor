<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminEmailController
{
    public function index(): void
    {
        $this->json(['controller' => 'AdminEmailController', 'status' => 'ok']);
    }
}
