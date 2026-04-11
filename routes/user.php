<?php

use App\Controllers\ActivationController;
use App\Controllers\AuthController;
use App\Controllers\BlockController;
use App\Controllers\ConnectionController;
use App\Controllers\ConnectionInviteController;
use App\Controllers\DiscoverController;
use App\Controllers\FavoriteController;
use App\Controllers\FeedController;
use App\Controllers\MatchController;
use App\Controllers\MessageController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\ReportController;
use App\Controllers\SettingsController;
use App\Controllers\SubscriptionController;
use App\Controllers\SwipeController;
use App\Middleware\ActiveAccountMiddleware;
use App\Middleware\ActiveSubscriptionMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\VerifiedEmailMiddleware;

$router->add('GET', '/activation', [ActivationController::class, 'show'], [AuthMiddleware::class]);
$router->add('POST', '/activation/pay', [ActivationController::class, 'pay'], [AuthMiddleware::class]);
$router->add('GET', '/activation/status', [ActivationController::class, 'status'], [AuthMiddleware::class]);
$router->add('GET', '/subscription/status', [SubscriptionController::class, 'status'], [AuthMiddleware::class]);
$router->add('POST', '/subscription/renew', [SubscriptionController::class, 'renew'], [AuthMiddleware::class]);
$router->add('GET', '/dashboard', [AuthController::class, 'dashboard'], [AuthMiddleware::class]);

$coreAccess = [AuthMiddleware::class, VerifiedEmailMiddleware::class, ActiveAccountMiddleware::class, ActiveSubscriptionMiddleware::class];

$router->add('GET', '/discover', [DiscoverController::class, 'index'], $coreAccess);
$router->add('GET', '/swipe', [SwipeController::class, 'index'], $coreAccess);
$router->add('POST', '/swipe/action', [SwipeController::class, 'action'], $coreAccess);
$router->add('GET', '/matches', [MatchController::class, 'index'], $coreAccess);
$router->add('GET', '/profile', [ProfileController::class, 'index'], $coreAccess);
$router->add('POST', '/profile/update', [ProfileController::class, 'update'], $coreAccess);
$router->add('POST', '/profile/interests', [ProfileController::class, 'updateInterests'], $coreAccess);
$router->add('POST', '/profile/preferences', [ProfileController::class, 'updatePreferences'], $coreAccess);
$router->add('POST', '/profile/connection-mode', [ProfileController::class, 'updateConnectionMode'], $coreAccess);
$router->add('POST', '/profile/photo', [ProfileController::class, 'photo'], $coreAccess);
$router->add('POST', '/profile/gallery', [ProfileController::class, 'gallery'], $coreAccess);
$router->add('POST', '/profile/photo/primary', [ProfileController::class, 'setPrimaryPhoto'], $coreAccess);
$router->add('POST', '/profile/photo/delete', [ProfileController::class, 'deletePhoto'], $coreAccess);
$router->add('POST', '/profile/gallery/reorder', [ProfileController::class, 'reorderGallery'], $coreAccess);
$router->add('GET', '/messages', [MessageController::class, 'index'], $coreAccess);
$router->add('GET', '/messages/stream', [MessageController::class, 'stream'], $coreAccess);
$router->add('POST', '/messages/typing', [MessageController::class, 'typing'], $coreAccess);
$router->add('POST', '/messages/send', [MessageController::class, 'send'], $coreAccess);
$router->add('GET', '/feed', [FeedController::class, 'index'], $coreAccess);
$router->add('POST', '/feed/post', [FeedController::class, 'post'], $coreAccess);
$router->add('POST', '/feed/delete', [FeedController::class, 'delete'], $coreAccess);
$router->add('POST', '/feed/like', [FeedController::class, 'like'], $coreAccess);
$router->add('POST', '/feed/comment', [FeedController::class, 'comment'], $coreAccess);
$router->add('GET', '/notifications', [NotificationController::class, 'index'], $coreAccess);
$router->add('POST', '/favorite/toggle', [FavoriteController::class, 'toggle'], $coreAccess);
$router->add('POST', '/block', [BlockController::class, 'store'], $coreAccess);
$router->add('POST', '/report', [ReportController::class, 'store'], $coreAccess);
$router->add('POST', '/connection/request', [ConnectionController::class, 'request'], $coreAccess);
$router->add('POST', '/connection/accept', [ConnectionController::class, 'accept'], $coreAccess);


$router->add('GET', '/invites/received', [ConnectionInviteController::class, 'received'], $coreAccess);
$router->add('GET', '/invites/sent', [ConnectionInviteController::class, 'sent'], $coreAccess);
$router->add('POST', '/invites/send', [ConnectionInviteController::class, 'send'], $coreAccess);
$router->add('POST', '/invites/accept', [ConnectionInviteController::class, 'accept'], $coreAccess);
$router->add('POST', '/invites/decline', [ConnectionInviteController::class, 'decline'], $coreAccess);

$router->add('GET', '/settings', [SettingsController::class, 'index'], $coreAccess);
$router->add('POST', '/settings/update', [SettingsController::class, 'update'], $coreAccess);
