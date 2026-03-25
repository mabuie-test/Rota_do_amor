<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminVerificationController
{
    public function index(): void
    {
        $this->json(['controller' => 'AdminVerificationController', 'status' => 'ok']);
    }
}
