<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\FavoriteService;

final class FavoriteController extends Controller
{
    public function __construct(private readonly FavoriteService $service = new FavoriteService())
    {
    }

    public function toggle(): void
    {
        $active = $this->service->toggle(Auth::id() ?? 0, (int) Request::input('target_user_id', 0));
        Response::json(['ok' => true, 'active' => $active]);
    }
}
