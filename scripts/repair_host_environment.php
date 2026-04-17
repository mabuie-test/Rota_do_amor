<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$dbConfig = require dirname(__DIR__) . '/config/database.php';

function out(string $line): void
{
    echo $line . PHP_EOL;
}

function tableExists(PDO $pdo, string $schema, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=:schema_name AND table_name=:table_name');
    $stmt->execute([':schema_name' => $schema, ':table_name' => $table]);
    return (int) ($stmt->fetch()['c'] ?? 0) > 0;
}

function columnExists(PDO $pdo, string $schema, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) c FROM information_schema.columns WHERE table_schema=:schema_name AND table_name=:table_name AND column_name=:column_name');
    $stmt->execute([':schema_name' => $schema, ':table_name' => $table, ':column_name' => $column]);
    return (int) ($stmt->fetch()['c'] ?? 0) > 0;
}

$settingsDefaults = [
    'daily_route_reward_boost_hours' => ['value' => '4', 'type' => 'int'],
    'daily_route_reward_badge_type' => ['value' => 'consistencia', 'type' => 'string'],
    'daily_route_nudge_end_of_day_hour' => ['value' => '19', 'type' => 'int'],
    'daily_route_nudge_inactive_days' => ['value' => '3', 'type' => 'int'],
    'daily_route_nudge_streak_risk_min_streak' => ['value' => '2', 'type' => 'int'],
    'daily_route_nudge_new_user_window_days' => ['value' => '10', 'type' => 'int'],
    'daily_route_enable_visitors_hub_task' => ['value' => '1', 'type' => 'bool'],
    'daily_route_enable_anonymous_stories_task' => ['value' => '1', 'type' => 'bool'],
    'daily_route_enable_compatibility_duel_task' => ['value' => '1', 'type' => 'bool'],
    'visitors_free_visible_visitors' => ['value' => '3', 'type' => 'int'],
    'visitors_free_history_hours' => ['value' => '24', 'type' => 'int'],
    'visitors_premium_history_days' => ['value' => '30', 'type' => 'int'],
    'visitors_track_limit_per_hour' => ['value' => '120', 'type' => 'int'],
    'compatibility_duel_free_daily_limit' => ['value' => '1', 'type' => 'int'],
    'compatibility_duel_premium_daily_limit' => ['value' => '3', 'type' => 'int'],
    'compatibility_duel_extra_enabled' => ['value' => '1', 'type' => 'bool'],
    'compatibility_duel_premium_insights_enabled' => ['value' => '1', 'type' => 'bool'],
];

$criticalTables = [
    'safe_dates',
    'safe_date_private_feedback',
    'profile_visits',
    'anonymous_story_reports',
    'compatibility_duels',
    'daily_routes',
    'activity_logs',
];

$criticalColumns = [
    'activity_logs' => ['actor_type', 'actor_id', 'action', 'target_type', 'target_id', 'metadata_json', 'created_at'],
    'site_settings' => ['setting_key', 'setting_value', 'value_type', 'updated_at'],
];

try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbConfig['host'], $dbConfig['port'], $dbConfig['database']);
    $pdo = new PDO($dsn, (string) $dbConfig['username'], (string) $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $exception) {
    fwrite(STDERR, '[repair] database connection failed: ' . $exception->getMessage() . PHP_EOL);
    exit(2);
}

out('== Rota do Amor · Repair host environment ==');
out('Database: ' . $dbConfig['database']);

$actions = [];
$warnings = [];

if (!tableExists($pdo, $dbConfig['database'], 'site_settings')) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS site_settings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(191) NOT NULL UNIQUE,
        setting_value TEXT NULL,
        value_type VARCHAR(20) NOT NULL DEFAULT "string",
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $actions[] = 'Criada tabela site_settings';
}

$insertStmt = $pdo->prepare(
    'INSERT INTO site_settings (setting_key,setting_value,value_type,updated_at)
     VALUES (:key,:value,:type,NOW())
     ON DUPLICATE KEY UPDATE
        setting_value = CASE WHEN site_settings.setting_value IS NULL OR site_settings.setting_value = "" THEN VALUES(setting_value) ELSE site_settings.setting_value END,
        value_type = CASE WHEN site_settings.value_type IS NULL OR site_settings.value_type = "" THEN VALUES(value_type) ELSE site_settings.value_type END,
        updated_at = NOW()'
);

$inserted = 0;
foreach ($settingsDefaults as $key => $definition) {
    $insertStmt->execute([':key' => $key, ':value' => $definition['value'], ':type' => $definition['type']]);
    $inserted++;
}
$actions[] = 'Bootstrap de settings executado (' . $inserted . ' chaves processadas)';

foreach ($criticalTables as $table) {
    if (!tableExists($pdo, $dbConfig['database'], $table)) {
        $warnings[] = 'Tabela crítica ausente (migração manual necessária): ' . $table;
    }
}

foreach ($criticalColumns as $table => $columns) {
    foreach ($columns as $column) {
        if (!columnExists($pdo, $dbConfig['database'], $table, $column)) {
            $warnings[] = 'Coluna crítica ausente: ' . $table . '.' . $column;
        }
    }
}

$uploadTmpIni = (string) ini_get('upload_tmp_dir');
$uploadTmpEffective = $uploadTmpIni !== '' ? $uploadTmpIni : (string) sys_get_temp_dir();
if ($uploadTmpEffective === '' || !is_dir($uploadTmpEffective) || !is_writable($uploadTmpEffective)) {
    $warnings[] = 'upload_tmp_dir inválido/sem escrita; corrigir no php.ini/.user.ini (UPLOAD_ERR_NO_TMP_DIR).';
}

$uploadRoot = dirname(__DIR__) . '/public/storage/uploads';
if (!is_dir($uploadRoot) && !@mkdir($uploadRoot, 0755, true) && !is_dir($uploadRoot)) {
    $warnings[] = 'Não foi possível criar diretório de upload final: ' . $uploadRoot;
} elseif (!is_writable($uploadRoot)) {
    $warnings[] = 'Diretório de upload final sem permissão de escrita: ' . $uploadRoot;
} else {
    $actions[] = 'Diretório de upload final verificado: ' . $uploadRoot;
}

if ($actions !== []) {
    out(PHP_EOL . '[OK] Ações aplicadas:');
    foreach ($actions as $action) {
        out(' - ' . $action);
    }
}

if ($warnings !== []) {
    out(PHP_EOL . '[WARN] Pendências para migração completa:');
    foreach ($warnings as $warning) {
        out(' - ' . $warning);
    }
}

out(PHP_EOL . 'Repair finalizado (idempotente). Execute também: php scripts/verify_production_readiness.php');
exit(0);
