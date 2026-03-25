<?php

declare(strict_types=1);

namespace App\Middleware;

final class AuthMiddleware
{
    public function handle(callable $next): mixed
    {
        return $next();
    }
}
