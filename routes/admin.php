<?php

use App\Controllers\AdminAuthController;
use App\Controllers\AdminBoostController;
use App\Controllers\AdminDashboardController;
use App\Controllers\AdminModerationController;
use App\Controllers\AdminPaymentController;
use App\Controllers\AdminReportController;
use App\Controllers\AdminSubscriptionController;
use App\Controllers\AdminSystemController;
use App\Controllers\AdminUserController;
use App\Controllers\AdminVerificationController;

$router->add('GET', '/admin/login', [AdminAuthController::class, 'index']);
$router->add('POST', '/admin/login', [AdminAuthController::class, 'index']);
$router->add('GET', '/admin', [AdminDashboardController::class, 'index']);
$router->add('GET', '/admin/users', [AdminUserController::class, 'index']);
$router->add('GET', '/admin/users/{id}', [AdminUserController::class, 'index']);
$router->add('POST', '/admin/users/{id}/status', [AdminUserController::class, 'index']);
$router->add('GET', '/admin/payments', [AdminPaymentController::class, 'index']);
$router->add('GET', '/admin/subscriptions', [AdminSubscriptionController::class, 'index']);
$router->add('GET', '/admin/boosts', [AdminBoostController::class, 'index']);
$router->add('GET', '/admin/verifications', [AdminVerificationController::class, 'index']);
$router->add('POST', '/admin/verifications/{id}/approve', [AdminVerificationController::class, 'index']);
$router->add('POST', '/admin/verifications/{id}/reject', [AdminVerificationController::class, 'index']);
$router->add('GET', '/admin/reports', [AdminReportController::class, 'index']);
$router->add('POST', '/admin/reports/{id}/resolve', [AdminReportController::class, 'index']);
$router->add('GET', '/admin/moderation', [AdminModerationController::class, 'index']);
$router->add('POST', '/admin/moderation/suspend', [AdminModerationController::class, 'index']);
$router->add('POST', '/admin/moderation/ban', [AdminModerationController::class, 'index']);
$router->add('GET', '/admin/settings', [AdminSystemController::class, 'index']);
$router->add('POST', '/admin/settings/update', [AdminSystemController::class, 'index']);
$router->add('POST', '/admin/users/{id}/resend-verification-email', [AdminUserController::class, 'index']);
