<?php

declare(strict_types=1);

namespace App\Middleware;

final class AdminSupportMiddleware
{
    public function handle(callable $next): mixed
    {
        return AdminRoleMiddleware::allow(['super_admin', 'support', 'ops'], $next);
    }
}
