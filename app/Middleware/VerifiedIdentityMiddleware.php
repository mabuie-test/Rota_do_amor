<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Response;
use App\Services\AccountStateService;

final class VerifiedIdentityMiddleware
{
    public function handle(callable $next): mixed
    {
        $userId = Auth::id();
        if (!$userId) {
            Response::redirect('/login');
        }

        $states = new AccountStateService();
        if (!$states->userIsIdentityVerified($userId)) {
            Response::redirect('/verification');
        }

        return $next();
    }
}
