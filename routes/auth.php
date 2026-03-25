<?php

use App\Controllers\AuthController;
use App\Controllers\EmailVerificationController;
use App\Controllers\PasswordResetController;
use App\Controllers\RegistrationController;

$router->add('GET', '/register', [RegistrationController::class, 'showRegister']);
$router->add('POST', '/register', [RegistrationController::class, 'register']);
$router->add('GET', '/login', [AuthController::class, 'showLogin']);
$router->add('POST', '/login', [AuthController::class, 'login']);
$router->add('GET', '/forgot-password', [PasswordResetController::class, 'showForgotPassword']);
$router->add('POST', '/forgot-password', [PasswordResetController::class, 'requestReset']);
$router->add('GET', '/reset-password', [PasswordResetController::class, 'showResetPassword']);
$router->add('POST', '/reset-password', [PasswordResetController::class, 'resetPassword']);
$router->add('GET', '/email/verify', [EmailVerificationController::class, 'verify']);
$router->add('POST', '/email/verify/resend', [EmailVerificationController::class, 'resend']);
