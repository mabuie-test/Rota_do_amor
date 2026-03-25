<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Response;
use App\Services\AccountStateService;

final class ActiveSubscriptionMiddleware
{
    public function handle(callable $next): mixed
    {
        $userId = Auth::id();
        if (!$userId) {
            Response::redirect('/login');
        }

        $states = new AccountStateService();
        if (!$states->hasActiveSubscription($userId)) {
            Response::redirect('/subscription/status');
        }

        return $next();
    }
}
