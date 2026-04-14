<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;

final class HomeController extends Controller
{
    public function index(): void
    {
        if (Auth::check()) {
            Response::redirect('/feed');
        }

        $this->view('home/index', ['title' => 'Rota do Amor']);
    }

    public function plans(): void
    {
        $this->view('home/plans', ['title' => 'Planos']);
    }

    public function about(): void
    {
        $this->view('home/about', ['title' => 'Sobre']);
    }

    public function safety(): void
    {
        $this->view('home/safety', ['title' => 'Segurança']);
    }
}
