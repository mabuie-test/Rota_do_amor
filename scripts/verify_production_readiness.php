<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$dbConfig = require dirname(__DIR__) . '/config/database.php';

$summary = [
    'errors' => [],
    'warnings' => [],
    'info' => [],
];

function out(string $line): void
{
    echo $line . PHP_EOL;
}

function checkTable(PDO $pdo, string $schema, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema = :schema_name AND table_name = :table_name');
    $stmt->execute([':schema_name' => $schema, ':table_name' => $table]);
    return (int) ($stmt->fetch()['c'] ?? 0) > 0;
}

function checkColumn(PDO $pdo, string $schema, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema = :schema_name AND table_name = :table_name AND column_name = :column_name');
    $stmt->execute([':schema_name' => $schema, ':table_name' => $table, ':column_name' => $column]);
    return (int) ($stmt->fetch()['c'] ?? 0) > 0;
}

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
    'activity_logs',
    'reports',
    'safe_dates',
    'safe_date_private_feedback',
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
    'payments',
    'admin_audit_logs',
];

$requiredColumns = [
    'site_settings' => ['setting_key', 'setting_value', 'value_type', 'updated_at'],
    'profile_visits' => ['visitor_user_id', 'visited_user_id', 'source_context', 'created_at'],
    'anonymous_stories' => ['author_user_id', 'status', 'is_featured', 'is_story_of_day', 'moderation_note', 'last_moderated_by_admin_id', 'last_moderated_at'],
    'anonymous_story_reports' => ['story_id', 'reporter_user_id', 'status', 'reason', 'created_at'],
    'compatibility_duels' => ['user_id', 'duel_date', 'status', 'selected_option_id', 'created_at'],
    'compatibility_duel_options' => ['duel_id', 'candidate_user_id', 'compatibility_score_snapshot', 'sort_order'],
    'compatibility_duel_choices' => ['duel_id', 'user_id', 'selected_option_id', 'created_at'],
    'daily_routes' => ['user_id', 'route_date', 'status', 'reward_status', 'streak_snapshot'],
    'daily_route_tasks' => ['daily_route_id', 'task_type', 'target_value', 'current_value', 'status'],
    'daily_route_event_logs' => ['user_id', 'daily_route_id', 'event_type', 'source_module', 'increment_value'],
    'activity_logs' => ['actor_type', 'actor_id', 'action', 'target_type', 'target_id', 'metadata_json', 'created_at'],
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
    'admin_settings' => 'SELECT setting_key, setting_value, value_type FROM site_settings ORDER BY setting_key LIMIT 5',
    'admin_risk_overview' => "SELECT COUNT(*) AS c FROM reports WHERE status='pending'",
    'admin_risk_safe_date_feedback' => "SELECT COUNT(*) AS c FROM safe_date_private_feedback WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    'super_dashboard_daily_route_summary' => "SELECT COUNT(*) AS c FROM daily_routes WHERE route_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
    'super_dashboard_visitors_summary' => "SELECT COUNT(*) AS c FROM profile_visits WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'super_dashboard_stories_summary' => "SELECT COUNT(*) AS c FROM anonymous_stories WHERE status IN ('published','featured')",
    'super_dashboard_duels_summary' => "SELECT COUNT(*) AS c FROM compatibility_duels WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    'super_dashboard_recent_activity' => 'SELECT action, actor_type, target_type, target_id, created_at FROM activity_logs ORDER BY id DESC LIMIT 3',
];

$missingTables = [];
foreach ($requiredTables as $table) {
    if (!checkTable($pdo, $dbConfig['database'], $table)) {
        $missingTables[] = $table;
    }
}

$missingColumns = [];
foreach ($requiredColumns as $table => $columns) {
    foreach ($columns as $column) {
        if (!checkColumn($pdo, $dbConfig['database'], $table, $column)) {
            $missingColumns[] = $table . '.' . $column;
        }
    }
}

$missingSettings = [];
if (checkTable($pdo, $dbConfig['database'], 'site_settings')) {
    foreach ($requiredSettings as $settingKey) {
        $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute([':setting_key' => $settingKey]);
        $row = $stmt->fetch();
        if ($row === false || trim((string) ($row['setting_value'] ?? '')) === '') {
            $missingSettings[] = $settingKey;
        }
    }
} else {
    $missingSettings = $requiredSettings;
}

$queryFailures = [];
foreach ($criticalQueries as $queryName => $query) {
    try {
        $pdo->query($query)->fetch();
    } catch (Throwable $exception) {
        $queryFailures[] = $queryName . ' => ' . $exception->getMessage();
    }
}

$uploadTmpIni = (string) ini_get('upload_tmp_dir');
$uploadTmpEffective = $uploadTmpIni !== '' ? $uploadTmpIni : (string) sys_get_temp_dir();
if ($uploadTmpEffective === '' || !is_dir($uploadTmpEffective)) {
    $summary['warnings'][] = 'upload_tmp_dir ausente/inválido (' . ($uploadTmpEffective === '' ? 'vazio' : $uploadTmpEffective) . ')';
} elseif (!is_writable($uploadTmpEffective)) {
    $summary['warnings'][] = 'upload_tmp_dir sem permissão de escrita: ' . $uploadTmpEffective;
}

$publicUploadRoot = dirname(__DIR__) . '/public/storage/uploads';
if (!is_dir($publicUploadRoot)) {
    $summary['warnings'][] = 'diretório de upload final ausente: ' . $publicUploadRoot;
} elseif (!is_writable($publicUploadRoot)) {
    $summary['warnings'][] = 'diretório de upload final sem escrita: ' . $publicUploadRoot;
}

if ($missingTables !== []) {
    $summary['errors'][] = 'Tabelas em falta: ' . implode(', ', $missingTables);
}
if ($missingColumns !== []) {
    $summary['errors'][] = 'Colunas em falta: ' . implode(', ', $missingColumns);
}
if ($missingSettings !== []) {
    $summary['errors'][] = 'Settings mínimas em falta/vazias: ' . implode(', ', $missingSettings);
}
if ($queryFailures !== []) {
    $summary['errors'][] = 'Queries críticas com falha: ' . implode(' | ', $queryFailures);
}

out(PHP_EOL . '== Rota do Amor · Production readiness check ==');
out('Database: ' . $dbConfig['database']);
out('Missing tables: ' . count($missingTables));
out('Missing columns: ' . count($missingColumns));
out('Missing/empty site_settings: ' . count($missingSettings));
out('Failed critical queries: ' . count($queryFailures));
out('Warnings: ' . count($summary['warnings']));

if ($missingTables !== []) {
    out(PHP_EOL . '[ERROR] Missing tables:');
    foreach ($missingTables as $table) {
        out(' - ' . $table);
    }
}
if ($missingColumns !== []) {
    out(PHP_EOL . '[ERROR] Missing columns:');
    foreach ($missingColumns as $column) {
        out(' - ' . $column);
    }
}
if ($missingSettings !== []) {
    out(PHP_EOL . '[ERROR] Missing or empty site_settings keys:');
    foreach ($missingSettings as $setting) {
        out(' - ' . $setting);
    }
}
if ($queryFailures !== []) {
    out(PHP_EOL . '[ERROR] Failed critical checks:');
    foreach ($queryFailures as $failure) {
        out(' - ' . $failure);
    }
}
if ($summary['warnings'] !== []) {
    out(PHP_EOL . '[WARN] Host warnings:');
    foreach ($summary['warnings'] as $warning) {
        out(' - ' . $warning);
    }
}

$statusCode = $summary['errors'] === [] ? 0 : 1;
out(PHP_EOL . 'Status: ' . ($statusCode === 0 ? 'READY' : 'NOT READY'));
exit($statusCode);
