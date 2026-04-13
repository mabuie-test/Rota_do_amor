<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\DailyRouteService;

try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('This script must run in CLI mode.');
    }

    $service = new DailyRouteService();
    $stats = $service->sendRetentionNudges();

    echo sprintf(
        "[daily_route_nudges] checked=%d sent=%d\n",
        (int) ($stats['checked'] ?? 0),
        (int) ($stats['sent'] ?? 0)
    );
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[daily_route_nudges] failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
