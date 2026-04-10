<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\CsrfMiddleware;
use Throwable;

final class App
{
    public function run(): void
    {
        try {
            Session::start();

            $router = new Router();
            require dirname(__DIR__, 2) . '/routes/web.php';
            require dirname(__DIR__, 2) . '/routes/auth.php';
            require dirname(__DIR__, 2) . '/routes/user.php';
            require dirname(__DIR__, 2) . '/routes/premium.php';
            require dirname(__DIR__, 2) . '/routes/admin.php';

            if (in_array(Request::method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                (new CsrfMiddleware())->handle(static fn () => true);
            }

            $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
        } catch (Throwable $exception) {
            error_log('[RotaDoAmor] ' . $exception->getMessage());

            if (Request::expectsJson()) {
                Response::json(['ok' => false, 'message' => 'Erro interno do servidor.'], 500);
            }

            http_response_code(500);
            echo 'Erro interno do servidor. Tente novamente mais tarde.';
        }
    }
}
