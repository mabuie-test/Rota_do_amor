<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use App\Services\AdminAuthorizationService;

final class AdminMiddleware
{
    public function __construct(private readonly AdminAuthorizationService $authorization = new AdminAuthorizationService())
    {
    }

    public function handle(callable $next): mixed
    {
        $admin = $this->authorization->resolveActiveAdminFromSession();
        if ($admin === []) {
            Response::redirect('/admin/login');
        }

        return $next();
    }
}
