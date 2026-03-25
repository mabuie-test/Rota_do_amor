<?php
require __DIR__ . '/bootstrap.php';

use App\Services\SubscriptionService;

$service = new SubscriptionService();
echo 'Expired subscriptions: ' . $service->expireOverdueSubscriptions() . PHP_EOL;
