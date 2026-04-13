<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Services\CompatibilityDuelService;

$service = new CompatibilityDuelService();
$db = Database::connection();
$rows = $db->query("SELECT id FROM users WHERE status='active' AND activation_paid_at IS NOT NULL LIMIT 5000")->fetchAll();
$generated = 0;

foreach ($rows as $row) {
    $userId = (int) ($row['id'] ?? 0);
    if ($userId <= 0) {
        continue;
    }

    $duel = $service->getOrCreateDailyDuel($userId);
    if ($duel !== []) {
        $generated++;
    }
}

echo sprintf("[compatibility_duels] users_checked=%d generated_or_available=%d\n", count($rows), $generated);
