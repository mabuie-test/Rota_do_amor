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
use App\Middleware\AdminFinanceMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\AdminModeratorMiddleware;
use App\Middleware\AdminSuperMiddleware;

$router->add('GET', '/admin/login', [AdminAuthController::class, 'index']);
$router->add('POST', '/admin/login', [AdminAuthController::class, 'index']);

$adminGuard = [AdminMiddleware::class];
$moderationGuard = [AdminMiddleware::class, AdminModeratorMiddleware::class];
$financeGuard = [AdminMiddleware::class, AdminFinanceMiddleware::class];
$superGuard = [AdminMiddleware::class, AdminSuperMiddleware::class];
$router->add('GET', '/admin', [AdminDashboardController::class, 'index'], $adminGuard);
$router->add('GET', '/admin/users', [AdminUserController::class, 'index'], $moderationGuard);
$router->add('GET', '/admin/users/{id}', [AdminUserController::class, 'show'], $moderationGuard);
$router->add('POST', '/admin/users/{id}/status', [AdminUserController::class, 'updateStatus'], $moderationGuard);
$router->add('POST', '/admin/users/{id}/resend-verification-email', [AdminUserController::class, 'resendVerificationEmail'], $moderationGuard);
$router->add('GET', '/admin/payments', [AdminPaymentController::class, 'index'], $financeGuard);
$router->add('GET', '/admin/subscriptions', [AdminSubscriptionController::class, 'index'], $financeGuard);
$router->add('GET', '/admin/boosts', [AdminBoostController::class, 'index'], $financeGuard);
$router->add('GET', '/admin/verifications', [AdminVerificationController::class, 'index'], $moderationGuard);
$router->add('POST', '/admin/verifications/{id}/approve', [AdminVerificationController::class, 'approve'], $moderationGuard);
$router->add('POST', '/admin/verifications/{id}/reject', [AdminVerificationController::class, 'reject'], $moderationGuard);
$router->add('GET', '/admin/reports', [AdminReportController::class, 'index'], $moderationGuard);
$router->add('POST', '/admin/reports/{id}/resolve', [AdminReportController::class, 'resolve'], $moderationGuard);
$router->add('GET', '/admin/moderation', [AdminModerationController::class, 'index'], $moderationGuard);
$router->add('POST', '/admin/moderation/suspend', [AdminModerationController::class, 'suspend'], $moderationGuard);
$router->add('POST', '/admin/moderation/ban', [AdminModerationController::class, 'ban'], $moderationGuard);
$router->add('GET', '/admin/settings', [AdminSystemController::class, 'index'], $superGuard);
$router->add('POST', '/admin/settings/update', [AdminSystemController::class, 'update'], $superGuard);
$router->add('POST', '/admin/email/send', [AdminEmailController::class, 'index'], $superGuard);
