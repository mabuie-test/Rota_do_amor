<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\DailyRouteService;

$service = new DailyRouteService();
$stats = $service->sendRetentionNudges();

echo sprintf(
    "Daily-route nudges checked=%d sent=%d\n",
    (int) ($stats['checked'] ?? 0),
    (int) ($stats['sent'] ?? 0)
);
