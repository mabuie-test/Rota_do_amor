<?php

declare(strict_types=1);

namespace App\Core;

final class App
{
    public function run(): void
    {
        Session::start();

        $router = new Router();
        require dirname(__DIR__, 2) . '/routes/web.php';
        require dirname(__DIR__, 2) . '/routes/auth.php';
        require dirname(__DIR__, 2) . '/routes/user.php';
        require dirname(__DIR__, 2) . '/routes/premium.php';
        require dirname(__DIR__, 2) . '/routes/admin.php';

        $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
    }
}
