<?php

use App\Controllers\PremiumController;
use App\Controllers\VerificationController;
use App\Middleware\ActiveAccountMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\VerifiedEmailMiddleware;

$guard = [AuthMiddleware::class, VerifiedEmailMiddleware::class, ActiveAccountMiddleware::class];

$router->add('GET', '/premium', [PremiumController::class, 'show'], $guard);
$router->add('POST', '/premium/boost/pay', [PremiumController::class, 'payBoost'], $guard);
$router->add('GET', '/premium/boost/status', [PremiumController::class, 'boostStatus'], $guard);
$router->add('GET', '/verification', [VerificationController::class, 'index'], $guard);
$router->add('POST', '/verification/submit', [VerificationController::class, 'submit'], $guard);
