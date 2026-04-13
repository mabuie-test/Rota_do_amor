<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$dbConfig = require dirname(__DIR__) . '/config/database.php';

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbConfig['host'], $dbConfig['port'], $dbConfig['database']);
    $pdo = new PDO($dsn, (string) $dbConfig['username'], (string) $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $exception) {
    fwrite(STDERR, '[readiness] database connection failed: ' . $exception->getMessage() . PHP_EOL);
    exit(2);
}

$requiredTables = [
    'users',
    'site_settings',
    'daily_routes',
    'daily_route_tasks',
    'daily_route_rewards',
    'daily_route_streaks',
    'daily_route_nudge_logs',
    'daily_route_event_logs',
    'profile_visits',
    'anonymous_stories',
    'anonymous_story_reactions',
    'anonymous_story_comments',
    'anonymous_story_reports',
    'compatibility_duels',
    'compatibility_duel_options',
    'compatibility_duel_choices',
    'compatibility_duel_actions',
    'subscriptions',
    'admin_audit_logs',
];

$requiredColumns = [
    'profile_visits' => ['visitor_user_id', 'visited_user_id', 'source_context', 'created_at'],
    'anonymous_stories' => ['author_user_id', 'status', 'is_featured', 'is_story_of_day', 'moderation_note', 'last_moderated_by_admin_id', 'last_moderated_at'],
    'anonymous_story_reports' => ['story_id', 'reporter_user_id', 'status', 'reason', 'created_at'],
    'compatibility_duels' => ['user_id', 'duel_date', 'status', 'selected_option_id', 'created_at'],
    'compatibility_duel_options' => ['duel_id', 'candidate_user_id', 'compatibility_score_snapshot', 'sort_order'],
    'compatibility_duel_choices' => ['duel_id', 'user_id', 'selected_option_id', 'created_at'],
    'daily_routes' => ['user_id', 'route_date', 'status', 'reward_status', 'streak_snapshot'],
    'daily_route_tasks' => ['daily_route_id', 'task_type', 'target_value', 'current_value', 'status'],
    'daily_route_event_logs' => ['user_id', 'daily_route_id', 'event_type', 'source_module', 'increment_value'],
    'admin_audit_logs' => ['admin_user_id', 'action', 'target_type', 'target_id', 'metadata_json', 'created_at'],
];

$requiredSettings = [
    'visitors_free_visible_visitors',
    'visitors_free_history_hours',
    'visitors_premium_history_days',
    'visitors_track_limit_per_hour',
    'compatibility_duel_free_daily_limit',
    'compatibility_duel_premium_daily_limit',
    'compatibility_duel_extra_enabled',
    'compatibility_duel_premium_insights_enabled',
    'daily_route_enable_visitors_hub_task',
    'daily_route_enable_anonymous_stories_task',
    'daily_route_enable_compatibility_duel_task',
    'daily_route_nudge_end_of_day_hour',
    'daily_route_nudge_inactive_days',
    'daily_route_nudge_streak_risk_min_streak',
    'daily_route_nudge_new_user_window_days',
    'daily_route_reward_boost_hours',
    'daily_route_reward_badge_type',
];

$criticalQueries = [
    'dashboard_daily_route_summary' => "SELECT COUNT(*) AS c FROM daily_routes WHERE route_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
    'dashboard_visitors_summary' => "SELECT COUNT(*) AS c FROM profile_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'dashboard_stories_summary' => "SELECT COUNT(*) AS c FROM anonymous_stories WHERE status IN ('published','featured')",
    'dashboard_duels_summary' => "SELECT COUNT(*) AS c FROM compatibility_duels WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'admin_visitors_join' => "SELECT COUNT(*) AS c FROM profile_visits pv JOIN users vu ON vu.id = pv.visitor_user_id JOIN users tu ON tu.id = pv.visited_user_id",
    'admin_stories_join' => "SELECT COUNT(*) AS c FROM anonymous_stories s JOIN users u ON u.id = s.author_user_id",
    'admin_duels_join' => "SELECT COUNT(*) AS c FROM compatibility_duels d JOIN users u ON u.id = d.user_id",
];

$missingTables = [];
foreach ($requiredTables as $table) {
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema = :schema_name AND table_name = :table_name');
    $stmt->execute([':schema_name' => $dbConfig['database'], ':table_name' => $table]);
    if ((int) ($stmt->fetch()['c'] ?? 0) === 0) {
        $missingTables[] = $table;
    }
}

$missingColumns = [];
foreach ($requiredColumns as $table => $columns) {
    foreach ($columns as $column) {
        $stmt = $pdo->prepare('SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema = :schema_name AND table_name = :table_name AND column_name = :column_name');
        $stmt->execute([':schema_name' => $dbConfig['database'], ':table_name' => $table, ':column_name' => $column]);
        if ((int) ($stmt->fetch()['c'] ?? 0) === 0) {
            $missingColumns[] = $table . '.' . $column;
        }
    }
}

$missingSettings = [];
foreach ($requiredSettings as $settingKey) {
    $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = :setting_key LIMIT 1');
    $stmt->execute([':setting_key' => $settingKey]);
    $row = $stmt->fetch();
    if ($row === false || ($row['setting_value'] ?? '') === '') {
        $missingSettings[] = $settingKey;
    }
}

$queryFailures = [];
foreach ($criticalQueries as $queryName => $query) {
    try {
        $pdo->query($query)->fetch();
    } catch (Throwable $exception) {
        $queryFailures[] = $queryName . ' => ' . $exception->getMessage();
    }
}

echo PHP_EOL . '== Rota do Amor · Production readiness check ==' . PHP_EOL;
echo 'Database: ' . $dbConfig['database'] . PHP_EOL;
echo 'Missing tables: ' . count($missingTables) . PHP_EOL;
echo 'Missing columns: ' . count($missingColumns) . PHP_EOL;
echo 'Missing/empty site_settings: ' . count($missingSettings) . PHP_EOL;
echo 'Failed critical queries: ' . count($queryFailures) . PHP_EOL;

if ($missingTables !== []) {
    echo PHP_EOL . '[ERROR] Missing tables:' . PHP_EOL;
    foreach ($missingTables as $table) {
        echo ' - ' . $table . PHP_EOL;
    }
}

if ($missingColumns !== []) {
    echo PHP_EOL . '[ERROR] Missing columns:' . PHP_EOL;
    foreach ($missingColumns as $column) {
        echo ' - ' . $column . PHP_EOL;
    }
}

if ($missingSettings !== []) {
    echo PHP_EOL . '[ERROR] Missing or empty site_settings keys:' . PHP_EOL;
    foreach ($missingSettings as $setting) {
        echo ' - ' . $setting . PHP_EOL;
    }
}

if ($queryFailures !== []) {
    echo PHP_EOL . '[ERROR] Failed critical checks:' . PHP_EOL;
    foreach ($queryFailures as $failure) {
        echo ' - ' . $failure . PHP_EOL;
    }
}

if ($missingTables !== [] || $missingColumns !== [] || $missingSettings !== [] || $queryFailures !== []) {
    echo PHP_EOL . 'Status: NOT READY' . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'Status: READY' . PHP_EOL;
exit(0);
