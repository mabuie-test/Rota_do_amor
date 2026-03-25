<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AdminPaymentController
{
    public function index(): void
    {
        $this->json(['controller' => 'AdminPaymentController', 'status' => 'ok']);
    }
}
