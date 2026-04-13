<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$dbConfig = require dirname(__DIR__) . '/config/database.php';
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbConfig['host'], $dbConfig['port'], $dbConfig['database']);
$pdo = new PDO($dsn, (string) $dbConfig['username'], (string) $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$requiredTables = [
    'daily_routes',
    'daily_route_tasks',
    'daily_route_streaks',
    'profile_visits',
    'anonymous_stories',
    'anonymous_story_reactions',
    'anonymous_story_comments',
    'anonymous_story_reports',
    'compatibility_duels',
    'compatibility_duel_options',
    'compatibility_duel_choices',
    'compatibility_duel_actions',
    'site_settings',
];

$requiredSettings = [
    'visitors_free_visible_profiles',
    'visitors_premium_full_history_days',
    'anonymous_story_daily_limit_free',
    'anonymous_story_daily_limit_premium',
    'compatibility_duel_free_daily_limit',
    'compatibility_duel_premium_daily_limit',
    'daily_route_enable_visitors_hub_task',
    'daily_route_enable_anonymous_stories_task',
    'daily_route_enable_compatibility_duel_task',
];

$missingTables = [];
foreach ($requiredTables as $table) {
    $exists = $pdo->prepare('SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema = :schema_name AND table_name = :table_name');
    $exists->execute([':schema_name' => $dbConfig['database'], ':table_name' => $table]);
    if ((int) ($exists->fetch()['c'] ?? 0) === 0) {
        $missingTables[] = $table;
    }
}

$missingSettings = [];
foreach ($requiredSettings as $settingKey) {
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM site_settings WHERE setting_key = :setting_key');
    $stmt->execute([':setting_key' => $settingKey]);
    if ((int) ($stmt->fetch()['c'] ?? 0) === 0) {
        $missingSettings[] = $settingKey;
    }
}

echo PHP_EOL . '== Rota do Amor · Production readiness check ==' . PHP_EOL;
echo 'Database: ' . $dbConfig['database'] . PHP_EOL;
echo 'Missing tables: ' . count($missingTables) . PHP_EOL;
echo 'Missing site_settings: ' . count($missingSettings) . PHP_EOL;

if ($missingTables !== []) {
    echo PHP_EOL . '[ERROR] Missing tables:' . PHP_EOL;
    foreach ($missingTables as $table) {
        echo ' - ' . $table . PHP_EOL;
    }
}

if ($missingSettings !== []) {
    echo PHP_EOL . '[ERROR] Missing site_settings keys:' . PHP_EOL;
    foreach ($missingSettings as $setting) {
        echo ' - ' . $setting . PHP_EOL;
    }
}

if ($missingTables !== [] || $missingSettings !== []) {
    echo PHP_EOL . 'Status: NOT READY' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Status: READY' . PHP_EOL;
exit(0);
