<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\CompatibilityDuelService;

try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('This script must run in CLI mode.');
    }

    $service = new CompatibilityDuelService();
    $db = Database::connection();

    $limit = (int) ($_SERVER['argv'][1] ?? 5000);
    $limit = max(100, min(20000, $limit));

    $rows = $db->query("SELECT id FROM users WHERE status='active' AND activation_paid_at IS NOT NULL LIMIT {$limit}")->fetchAll();
    $generated = 0;
    $skipped = 0;

    foreach ($rows as $row) {
        $userId = (int) ($row['id'] ?? 0);
        if ($userId <= 0) {
            $skipped++;
            continue;
        }

        $duel = $service->getOrCreateDailyDuel($userId);
        if ($duel !== []) {
            $generated++;
            continue;
        }

        $skipped++;
    }

    echo sprintf(
        "[compatibility_duels] users_checked=%d generated_or_available=%d skipped=%d limit=%d\n",
        count($rows),
        $generated,
        $skipped,
        $limit
    );
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, '[compatibility_duels] failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
