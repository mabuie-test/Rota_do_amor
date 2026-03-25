<?php

use App\Controllers\HomeController;

$router->add('GET', '/', [HomeController::class, 'index']);
$router->add('GET', '/plans', [HomeController::class, 'plans']);
$router->add('GET', '/about', [HomeController::class, 'about']);
$router->add('GET', '/safety', [HomeController::class, 'safety']);
