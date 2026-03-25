<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ConnectionService;

final class ConnectionController extends Controller
{
    public function __construct(private readonly ConnectionService $service = new ConnectionService())
    {
    }

    public function request(): void
    {
        $id = $this->service->request(Auth::id() ?? 0, (int) Request::input('receiver_id', 0));
        Response::json(['ok' => true, 'connection_id' => $id]);
    }

    public function accept(): void
    {
        $ok = $this->service->accept((int) Request::input('connection_id', 0));
        Response::json(['ok' => $ok]);
    }
}
