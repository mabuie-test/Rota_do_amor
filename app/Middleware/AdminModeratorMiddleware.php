<?php

declare(strict_types=1);

namespace App\Middleware;

final class AdminModeratorMiddleware
{
    public function handle(callable $next): mixed
    {
        return AdminRoleMiddleware::allow(['super_admin', 'moderator'], $next);
    }
}

