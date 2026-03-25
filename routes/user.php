<?php

use App\Controllers\ActivationController;
use App\Controllers\AuthController;
use App\Controllers\DiscoverController;
use App\Controllers\FeedController;
use App\Controllers\MatchController;
use App\Controllers\MessageController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\SubscriptionController;
use App\Controllers\SwipeController;

$router->add('GET', '/activation', [ActivationController::class, 'show']);
$router->add('POST', '/activation/pay', [ActivationController::class, 'pay']);
$router->add('GET', '/activation/status', [ActivationController::class, 'status']);
$router->add('GET', '/subscription/status', [SubscriptionController::class, 'status']);
$router->add('POST', '/subscription/renew', [SubscriptionController::class, 'renew']);
$router->add('GET', '/dashboard', [AuthController::class, 'dashboard']);
$router->add('GET', '/discover', [DiscoverController::class, 'index']);
$router->add('GET', '/swipe', [SwipeController::class, 'index']);
$router->add('POST', '/swipe/action', [SwipeController::class, 'index']);
$router->add('GET', '/matches', [MatchController::class, 'index']);
$router->add('GET', '/profile', [ProfileController::class, 'index']);
$router->add('POST', '/profile/update', [ProfileController::class, 'index']);
$router->add('POST', '/profile/photo', [ProfileController::class, 'index']);
$router->add('POST', '/profile/gallery', [ProfileController::class, 'index']);
$router->add('GET', '/messages', [MessageController::class, 'index']);
$router->add('POST', '/messages/send', [MessageController::class, 'index']);
$router->add('GET', '/feed', [FeedController::class, 'index']);
$router->add('POST', '/feed/post', [FeedController::class, 'index']);
$router->add('POST', '/feed/like', [FeedController::class, 'index']);
$router->add('POST', '/feed/comment', [FeedController::class, 'index']);
$router->add('GET', '/notifications', [NotificationController::class, 'index']);
$router->add('POST', '/favorite/toggle', [\App\Controllers\FavoriteController::class, 'index']);
$router->add('POST', '/block', [\App\Controllers\BlockController::class, 'index']);
$router->add('POST', '/report', [\App\Controllers\ReportController::class, 'index']);
