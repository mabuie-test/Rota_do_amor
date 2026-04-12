ALTER TABLE safe_dates
  ADD INDEX idx_safe_dates_admin_filters (status, safety_level, created_at, id),
  ADD INDEX idx_safe_dates_admin_participants (initiator_user_id, invitee_user_id, created_at);

INSERT INTO site_settings (setting_key, setting_value, value_type, updated_at)
VALUES
  ('safe_dates_premium_guard_enabled', 'true', 'bool', NOW()),
  ('safe_dates_free_daily_limit', '5', 'int', NOW()),
  ('safe_dates_premium_daily_limit', '10', 'int', NOW()),
  ('safe_dates_verified_only_requires_identity', 'true', 'bool', NOW()),
  ('safe_dates_max_open_free', '2', 'int', NOW()),
  ('safe_dates_max_open_premium', '5', 'int', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), updated_at = VALUES(updated_at);
