<?php

declare(strict_types=1);

namespace App\Middleware;

final class AdminContentModeratorMiddleware
{
    public function handle(callable $next): mixed
    {
        return AdminRoleMiddleware::allow(['super_admin', 'moderator', 'content_moderator'], $next);
    }
}
