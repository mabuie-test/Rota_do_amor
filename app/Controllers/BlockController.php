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
        $id = $this->service->block(Auth::id() ?? 0, (int) Request::input('target_user_id', 0), (string) Request::input('reason', ''));
        Response::json(['ok' => true, 'block_id' => $id]);
    }
}
