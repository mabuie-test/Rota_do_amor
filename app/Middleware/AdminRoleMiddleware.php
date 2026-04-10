<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use App\Core\Session;

final class AdminRoleMiddleware
{
    /**
     * @param array<int, string> $allowedRoles
     */
    public static function allow(array $allowedRoles, callable $next): mixed
    {
        $role = (string) Session::get('admin_role', '');
        if ($role === '' || !in_array($role, $allowedRoles, true)) {
            Response::abort(403, 'Permissão administrativa insuficiente.');
        }

        return $next();
    }
}

