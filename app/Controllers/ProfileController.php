<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class ProfileController
{
    public function index(): void
    {
        $this->json(['controller' => 'ProfileController', 'status' => 'ok']);
    }
}
