<?php

use App\Controllers\PremiumController;
use App\Controllers\VerificationController;

$router->add('GET', '/premium', [PremiumController::class, 'show']);
$router->add('POST', '/premium/boost/pay', [PremiumController::class, 'payBoost']);
$router->add('GET', '/premium/boost/status', [PremiumController::class, 'boostStatus']);
$router->add('GET', '/verification', [VerificationController::class, 'index']);
$router->add('POST', '/verification/submit', [VerificationController::class, 'index']);
