<?php

declare(strict_types=1);

namespace App\Middleware;

final class CsrfMiddleware
{
    public function handle(callable $next): mixed
    {
        return $next();
    }
}
