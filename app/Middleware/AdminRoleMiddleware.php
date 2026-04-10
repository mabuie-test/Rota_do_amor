<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use App\Services\AdminAuthorizationService;

final class AdminRoleMiddleware
{
    /**
     * @param array<int, string> $allowedRoles
     */
    public static function allow(array $allowedRoles, callable $next): mixed
    {
        $authorization = new AdminAuthorizationService();
        if (!$authorization->sessionAdminHasRole($allowedRoles)) {
            $authorization->invalidateAdminSession();
            Response::abort(403, 'Permissão administrativa insuficiente.');
        }

        return $next();
    }
}
