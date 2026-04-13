CREATE TABLE IF NOT EXISTS daily_routes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  route_date DATE NOT NULL,
  status ENUM('active','completed','expired') NOT NULL DEFAULT 'active',
  streak_snapshot INT NOT NULL DEFAULT 0,
  reward_status ENUM('pending','claimable','claimed','expired') NOT NULL DEFAULT 'pending',
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_daily_routes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_daily_routes_user_date (user_id, route_date),
  INDEX idx_daily_routes_status_date (status, route_date),
  INDEX idx_daily_routes_reward_status (reward_status, route_date)
);

CREATE TABLE IF NOT EXISTS daily_route_tasks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  daily_route_id BIGINT NOT NULL,
  task_type VARCHAR(80) NOT NULL,
  title VARCHAR(190) NOT NULL,
  description VARCHAR(255) NULL,
  target_value INT NOT NULL DEFAULT 1,
  current_value INT NOT NULL DEFAULT 0,
  status ENUM('pending','completed','expired') NOT NULL DEFAULT 'pending',
  reward_payload_json JSON NULL,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_daily_route_tasks_route FOREIGN KEY (daily_route_id) REFERENCES daily_routes(id) ON DELETE CASCADE,
  INDEX idx_daily_route_tasks_route_status (daily_route_id, status),
  INDEX idx_daily_route_tasks_type_status (task_type, status)
);

CREATE TABLE IF NOT EXISTS daily_route_rewards (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  daily_route_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  reward_type VARCHAR(100) NOT NULL,
  reward_payload_json JSON NULL,
  status ENUM('pending','claimed','cancelled') NOT NULL DEFAULT 'pending',
  claimed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_daily_route_rewards_route FOREIGN KEY (daily_route_id) REFERENCES daily_routes(id) ON DELETE CASCADE,
  CONSTRAINT fk_daily_route_rewards_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_daily_route_rewards_user_status (user_id, status, created_at),
  INDEX idx_daily_route_rewards_type_created (reward_type, created_at)
);

CREATE TABLE IF NOT EXISTS daily_route_streaks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  current_streak INT NOT NULL DEFAULT 0,
  best_streak INT NOT NULL DEFAULT 0,
  last_completed_date DATE NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_daily_route_streaks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_daily_route_streaks_user (user_id),
  INDEX idx_daily_route_streaks_current (current_streak, best_streak)
);

INSERT INTO site_settings (setting_key, setting_value, value_type, updated_at)
VALUES
('daily_route_reward_boost_hours', '2', 'int', NOW()),
('daily_route_reward_badge_type', 'constancia_diaria', 'string', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), updated_at = VALUES(updated_at);
