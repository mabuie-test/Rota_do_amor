<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Services\SafeDateService;

$service = new SafeDateService();
$result = $service->dispatchDueReminders(300);

echo sprintf(
    "Safe-date reminders processed=%d total_sent=%d (24h=%d,2h=%d,same_day=%d)\n",
    (int) ($result['processed'] ?? 0),
    (int) ($result['total_sent'] ?? 0),
    (int) (($result['sent']['24h'] ?? 0)),
    (int) (($result['sent']['2h'] ?? 0)),
    (int) (($result['sent']['same_day'] ?? 0))
);
