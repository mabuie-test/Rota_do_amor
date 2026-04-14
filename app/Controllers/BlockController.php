<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\BlockService;

final class BlockController extends Controller
{
    public function __construct(private readonly BlockService $service = new BlockService())
    {
    }

    public function store(): void
    {
        $targetId = (int) Request::input('target_user_id', 0);
        $id = $this->service->block(Auth::id() ?? 0, $targetId, (string) Request::input('reason', ''));
        $ok = $id > 0;

        Response::json([
            'ok' => $ok,
            'message' => $ok ? 'Bloqueio registado.' : 'Não foi possível bloquear este membro.',
            'action' => $ok ? 'created' : 'error',
            'state' => ['blocked' => $ok],
            'block_id' => $id,
            'created_id' => $id,
            'target_id' => $targetId,
            'error_code' => $ok ? null : 'block_create_failed',
        ], $ok ? 200 : 422);
    }
}
