<?php

use App\Controllers\HomeController;

$router->add('GET', '/', [HomeController::class, 'index']);
$router->add('GET', '/plans', [HomeController::class, 'index']);
$router->add('GET', '/about', [HomeController::class, 'index']);
$router->add('GET', '/safety', [HomeController::class, 'index']);
