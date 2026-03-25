<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\UserService;

final class SettingsController extends Controller
{
    public function __construct(private readonly UserService $users = new UserService())
    {
    }

    public function index(): void
    {
        $this->view('settings/index', ['title' => 'Definições']);
    }

    public function update(): void
    {
        $ok = $this->users->updateProfile(Auth::id() ?? 0, Request::all());
        Response::json(['ok' => $ok]);
    }
}
