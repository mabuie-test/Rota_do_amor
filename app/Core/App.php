<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\CsrfMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use Throwable;

final class App
{
    public function run(): void
    {
        try {
            Session::start();
            (new SecurityHeadersMiddleware())->handle();

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
            $reference = bin2hex(random_bytes(6));
            error_log(sprintf(
                '[RotaDoAmor][%s] %s in %s:%d',
                $reference,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));

            if (Request::expectsJson()) {
                Response::json([
                    'ok' => false,
                    'message' => $this->buildRuntimeMessage($exception, $reference),
                    'error_ref' => $reference,
                ], 500);
            }

            http_response_code(500);
            echo $this->buildRuntimeMessage($exception, $reference);
        }
    }

    private function buildRuntimeMessage(Throwable $exception, string $reference): string
    {
        if ($this->shouldExposeTechnicalDetails()) {
            return sprintf('Erro interno [%s]: %s', $reference, $exception->getMessage());
        }

        return sprintf('Erro interno do servidor. Tente novamente mais tarde. Ref: %s', $reference);
    }

    private function shouldExposeTechnicalDetails(): bool
    {
        $debug = filter_var((string) Config::env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
        $env = mb_strtolower(trim((string) Config::env('APP_ENV', 'production')));
        return $debug || in_array($env, ['local', 'development', 'dev', 'testing', 'test'], true);
    }
}
