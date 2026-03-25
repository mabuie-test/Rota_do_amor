<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Response;

final class GuestMiddleware
{
    public function handle(callable $next): mixed
    {
        if (Auth::check()) {
            Response::redirect('/dashboard');
        }

        return $next();
    }
}
