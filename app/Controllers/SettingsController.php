<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class SettingsController
{
    public function index(): void
    {
        $this->json(['controller' => 'SettingsController', 'status' => 'ok']);
    }
}
