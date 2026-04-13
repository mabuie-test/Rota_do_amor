CREATE TABLE IF NOT EXISTS daily_route_event_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  daily_route_id BIGINT NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  source_module VARCHAR(60) NOT NULL DEFAULT 'unknown',
  increment_value INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_daily_route_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_daily_route_events_route FOREIGN KEY (daily_route_id) REFERENCES daily_routes(id) ON DELETE CASCADE,
  INDEX idx_daily_route_events_route_created (daily_route_id, created_at),
  INDEX idx_daily_route_events_type_created (event_type, created_at),
  INDEX idx_daily_route_events_module_created (source_module, created_at)
);

INSERT INTO site_settings (setting_key, setting_value, value_type, updated_at)
VALUES
('daily_route_reward_badge_days', '30', 'int', NOW()),
('daily_route_reward_badge_days_premium', '45', 'int', NOW()),
('daily_route_nudge_end_of_day_hour', '19', 'int', NOW()),
('daily_route_nudge_inactive_days', '3', 'int', NOW()),
('daily_route_nudge_streak_risk_min_streak', '2', 'int', NOW()),
('daily_route_nudge_new_user_window_days', '14', 'int', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), updated_at = VALUES(updated_at);
