<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Model;
use Throwable;

final class SettingsService extends Model
{
    /**
     * @return array<string, array{value:string,type:string}>
     */
    public function baselineSettings(): array
    {
        return [
            'daily_route_reward_boost_hours' => ['value' => '4', 'type' => 'int'],
            'daily_route_reward_badge_type' => ['value' => 'consistencia', 'type' => 'string'],
            'daily_route_nudge_end_of_day_hour' => ['value' => '19', 'type' => 'int'],
            'daily_route_nudge_inactive_days' => ['value' => '3', 'type' => 'int'],
            'daily_route_nudge_streak_risk_min_streak' => ['value' => '2', 'type' => 'int'],
            'daily_route_nudge_new_user_window_days' => ['value' => '10', 'type' => 'int'],
            'daily_route_enable_visitors_hub_task' => ['value' => '1', 'type' => 'bool'],
            'daily_route_enable_anonymous_stories_task' => ['value' => '1', 'type' => 'bool'],
            'daily_route_enable_compatibility_duel_task' => ['value' => '1', 'type' => 'bool'],
            'daily_route_reward_badge_days' => ['value' => '7', 'type' => 'int'],
            'daily_route_reward_boost_hours_premium' => ['value' => '8', 'type' => 'int'],
            'daily_route_reward_badge_days_premium' => ['value' => '14', 'type' => 'int'],
            'daily_route_streak_bonus_threshold' => ['value' => '5', 'type' => 'int'],
            'daily_route_streak_bonus_boost_hours' => ['value' => '2', 'type' => 'int'],
            'daily_route_premium_streak_bonus_threshold' => ['value' => '4', 'type' => 'int'],
            'daily_route_premium_streak_bonus_boost_hours' => ['value' => '4', 'type' => 'int'],
            'daily_route_premium_discovery_priority_hours' => ['value' => '24', 'type' => 'int'],
            'visitors_free_visible_visitors' => ['value' => '3', 'type' => 'int'],
            'visitors_free_history_hours' => ['value' => '24', 'type' => 'int'],
            'visitors_premium_history_days' => ['value' => '30', 'type' => 'int'],
            'visitors_track_limit_per_hour' => ['value' => '120', 'type' => 'int'],
            'compatibility_duel_free_daily_limit' => ['value' => '1', 'type' => 'int'],
            'compatibility_duel_premium_daily_limit' => ['value' => '3', 'type' => 'int'],
            'compatibility_duel_extra_enabled' => ['value' => '1', 'type' => 'bool'],
            'compatibility_duel_premium_insights_enabled' => ['value' => '1', 'type' => 'bool'],
        ];
    }

    public function tableExists(string $table): bool
    {
        try {
            $row = $this->fetchOne(
                'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1',
                [':table' => $table]
            );

            return (int) ($row['c'] ?? 0) > 0;
        } catch (Throwable $exception) {
            error_log('[settings.table_exists_failed] table=' . $table . ' error=' . $exception->getMessage());
            return false;
        }
    }

    public function ensureSiteSettingsTable(): bool
    {
        if ($this->tableExists('site_settings')) {
            return true;
        }

        try {
            $this->execute(
                'CREATE TABLE IF NOT EXISTS site_settings (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(191) NOT NULL UNIQUE,
                    setting_value TEXT NULL,
                    value_type VARCHAR(20) NOT NULL DEFAULT "string",
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            return true;
        } catch (Throwable $exception) {
            error_log('[settings.ensure_table_failed] error=' . $exception->getMessage());
            return false;
        }
    }

    /**
     * @return array{inserted:int,failed:array<int,string>}
     */
    public function bootstrapMinimumSettings(): array
    {
        if (!$this->ensureSiteSettingsTable()) {
            return ['inserted' => 0, 'failed' => ['site_settings_table_unavailable']];
        }

        $inserted = 0;
        $failed = [];
        foreach ($this->baselineSettings() as $key => $definition) {
            try {
                $this->execute(
                    'INSERT INTO site_settings (setting_key,setting_value,value_type,updated_at)
                     VALUES (:key,:value,:type,NOW())
                     ON DUPLICATE KEY UPDATE setting_value=COALESCE(NULLIF(setting_value, ""), VALUES(setting_value)),
                                             value_type=COALESCE(NULLIF(value_type, ""), VALUES(value_type)),
                                             updated_at=NOW()',
                    [':key' => $key, ':value' => $definition['value'], ':type' => $definition['type']]
                );
                $inserted++;
            } catch (Throwable $exception) {
                $failed[] = $key;
                error_log('[settings.bootstrap_failed] key=' . $key . ' error=' . $exception->getMessage());
            }
        }

        return ['inserted' => $inserted, 'failed' => $failed];
    }

    public function listAllSafe(): array
    {
        if (!$this->tableExists('site_settings')) {
            return [];
        }

        try {
            return $this->fetchAll('SELECT * FROM site_settings ORDER BY setting_key');
        } catch (Throwable $exception) {
            error_log('[settings.list_failed] error=' . $exception->getMessage());
            return [];
        }
    }
}
