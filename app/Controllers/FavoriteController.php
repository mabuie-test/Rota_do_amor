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
        $result = $this->service->toggle(Auth::id() ?? 0, (int) Request::input('target_user_id', 0));
        $ok = (bool) ($result['success'] ?? false);

        Response::json([
            'ok' => $ok,
            'message' => (string) ($result['message'] ?? ($ok ? 'Favorito atualizado.' : 'Falha ao atualizar favorito.')),
            'action' => (string) ($result['action'] ?? ($ok ? 'updated' : 'error')),
            'state' => ['active' => (bool) ($result['active'] ?? false)],
            'active' => (bool) ($result['active'] ?? false),
            'created_id' => (int) ($result['created_id'] ?? 0),
            'target_id' => (int) ($result['target_id'] ?? 0),
            'error_code' => $result['error_code'] ?? null,
        ], $ok ? 200 : 422);
    }
}
