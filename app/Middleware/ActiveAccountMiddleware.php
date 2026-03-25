<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Response;
use App\Services\AccountStateService;

final class ActiveAccountMiddleware
{
    public function handle(callable $next): mixed
    {
        $userId = Auth::id();
        if (!$userId) {
            Response::redirect('/login');
        }

        $states = new AccountStateService();
        $states->syncUserStatus($userId);
        $user = $states->getUserState($userId);

        if (!$user || in_array($user['status'], ['suspended', 'banned', 'pending_activation'], true)) {
            Response::redirect('/activation');
        }

        return $next();
    }
}
