<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class VerificationController
{
    public function index(): void
    {
        $this->json(['controller' => 'VerificationController', 'status' => 'ok']);
    }
}
