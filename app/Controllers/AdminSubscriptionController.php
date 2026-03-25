<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminSubscriptionController
{
    public function index(): void
    {
        $this->json(['controller' => 'AdminSubscriptionController', 'status' => 'ok']);
    }
}
