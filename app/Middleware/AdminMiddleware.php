<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Response;
use App\Core\Session;

final class AdminMiddleware
{
    public function handle(callable $next): mixed
    {
        if (!Session::get('admin_id')) {
            Response::redirect('/admin/login');
        }

        return $next();
    }
}
