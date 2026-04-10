<?php

use App\Controllers\AdminAuthController;
use App\Controllers\AdminBoostController;
use App\Controllers\AdminDashboardController;
use App\Controllers\AdminEmailController;
use App\Controllers\AdminModerationController;
use App\Controllers\AdminPaymentController;
use App\Controllers\AdminReportController;
use App\Controllers\AdminSubscriptionController;
use App\Controllers\AdminSystemController;
use App\Controllers\AdminUserController;
use App\Controllers\AdminVerificationController;
use App\Middleware\AdminMiddleware;

$router->add('GET', '/admin/login', [AdminAuthController::class, 'index']);
$router->add('POST', '/admin/login', [AdminAuthController::class, 'index']);

$adminGuard = [AdminMiddleware::class];
$router->add('GET', '/admin', [AdminDashboardController::class, 'index'], $adminGuard);
$router->add('GET', '/admin/users', [AdminUserController::class, 'index'], $adminGuard);
$router->add('GET', '/admin/users/{id}', [AdminUserController::class, 'show'], $adminGuard);
$router->add('POST', '/admin/users/{id}/status', [AdminUserController::class, 'updateStatus'], $adminGuard);
$router->add('POST', '/admin/users/{id}/resend-verification-email', [AdminUserController::class, 'resendVerificationEmail'], $adminGuard);
$router->add('GET', '/admin/payments', [AdminPaymentController::class, 'index'], $adminGuard);
$router->add('GET', '/admin/subscriptions', [AdminSubscriptionController::class, 'index'], $adminGuard);
$router->add('GET', '/admin/boosts', [AdminBoostController::class, 'index'], $adminGuard);
$router->add('GET', '/admin/verifications', [AdminVerificationController::class, 'index'], $adminGuard);
$router->add('POST', '/admin/verifications/{id}/approve', [AdminVerificationController::class, 'approve'], $adminGuard);
$router->add('POST', '/admin/verifications/{id}/reject', [AdminVerificationController::class, 'reject'], $adminGuard);
$router->add('GET', '/admin/reports', [AdminReportController::class, 'index'], $adminGuard);
$router->add('POST', '/admin/reports/{id}/resolve', [AdminReportController::class, 'resolve'], $adminGuard);
$router->add('GET', '/admin/moderation', [AdminModerationController::class, 'index'], $adminGuard);
$router->add('POST', '/admin/moderation/suspend', [AdminModerationController::class, 'suspend'], $adminGuard);
$router->add('POST', '/admin/moderation/ban', [AdminModerationController::class, 'ban'], $adminGuard);
$router->add('GET', '/admin/settings', [AdminSystemController::class, 'index'], $adminGuard);
$router->add('POST', '/admin/settings/update', [AdminSystemController::class, 'update'], $adminGuard);
$router->add('POST', '/admin/email/send', [AdminEmailController::class, 'index'], $adminGuard);
