<?php

declare(strict_types=1);

namespace App\Middleware;

final class AdminOpsMiddleware
{
    public function handle(callable $next): mixed
    {
        return AdminRoleMiddleware::allow(['super_admin', 'ops'], $next);
    }
}
