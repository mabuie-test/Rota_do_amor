<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;

final class CsrfMiddleware
{
    public function handle(callable $next): mixed
    {
        if (in_array(Request::method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = (string) Request::input('_token', Request::header('X-CSRF-TOKEN', ''));
            if (!Csrf::validate($token)) {
                Response::abort(419, 'Invalid CSRF token');
            }
        }

        return $next();
    }
}
