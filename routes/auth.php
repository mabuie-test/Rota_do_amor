<?php

use App\Controllers\AuthController;
use App\Controllers\EmailVerificationController;
use App\Controllers\PasswordResetController;
use App\Controllers\RegistrationController;
use App\Middleware\GuestMiddleware;

$guest = [GuestMiddleware::class];

$router->add('GET', '/register', [RegistrationController::class, 'showRegister'], $guest);
$router->add('POST', '/register', [RegistrationController::class, 'register'], $guest);
$router->add('GET', '/login', [AuthController::class, 'showLogin'], $guest);
$router->add('POST', '/login', [AuthController::class, 'login'], $guest);
$router->add('POST', '/logout', [AuthController::class, 'logout']);
$router->add('GET', '/forgot-password', [PasswordResetController::class, 'showForgotPassword'], $guest);
$router->add('POST', '/forgot-password', [PasswordResetController::class, 'requestReset'], $guest);
$router->add('GET', '/reset-password/{token}', [PasswordResetController::class, 'showResetPassword'], $guest);
$router->add('POST', '/reset-password', [PasswordResetController::class, 'resetPassword'], $guest);
$router->add('GET', '/email/verify', [EmailVerificationController::class, 'verify']);
$router->add('GET', '/email/verify/{token}', [EmailVerificationController::class, 'verify']);
$router->add('POST', '/email/verify/resend', [EmailVerificationController::class, 'resend']);
