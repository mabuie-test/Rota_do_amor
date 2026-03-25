<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class HomeController extends Controller
{
    public function index(): void
    {
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
