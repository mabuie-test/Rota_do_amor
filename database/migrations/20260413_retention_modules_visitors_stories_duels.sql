CREATE TABLE IF NOT EXISTS profile_visits (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  visitor_user_id BIGINT NOT NULL,
  visited_user_id BIGINT NOT NULL,
  source_context VARCHAR(40) NOT NULL DEFAULT 'discover',
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_profile_visits_visitor FOREIGN KEY (visitor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_profile_visits_visited FOREIGN KEY (visited_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_profile_visits_visited_created (visited_user_id, created_at),
  INDEX idx_profile_visits_visitor_created (visitor_user_id, created_at),
  INDEX idx_profile_visits_pair_created (visitor_user_id, visited_user_id, created_at)
);

CREATE TABLE IF NOT EXISTS anonymous_stories (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  author_user_id BIGINT NOT NULL,
  category VARCHAR(40) NOT NULL,
  title VARCHAR(120) NULL,
  content TEXT NOT NULL,
  status ENUM('draft','published','hidden','moderated','featured','removed') NOT NULL DEFAULT 'published',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_story_of_day TINYINT(1) NOT NULL DEFAULT 0,
  moderation_note VARCHAR(500) NULL,
  last_moderated_by_admin_id BIGINT NULL,
  last_moderated_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_anonymous_stories_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_anonymous_stories_moderator FOREIGN KEY (last_moderated_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_anonymous_stories_status_created (status, created_at),
  INDEX idx_anonymous_stories_featured_created (is_featured, created_at),
  INDEX idx_anonymous_stories_category_created (category, created_at)
);

CREATE TABLE IF NOT EXISTS anonymous_story_reactions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  story_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  reaction_type VARCHAR(30) NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_story_reactions_story FOREIGN KEY (story_id) REFERENCES anonymous_stories(id) ON DELETE CASCADE,
  CONSTRAINT fk_story_reactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_story_reaction_story_user (story_id, user_id),
  INDEX idx_story_reactions_type_created (reaction_type, created_at)
);

CREATE TABLE IF NOT EXISTS anonymous_story_comments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  story_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  comment_text VARCHAR(500) NOT NULL,
  status ENUM('active','hidden','moderated') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_story_comments_story FOREIGN KEY (story_id) REFERENCES anonymous_stories(id) ON DELETE CASCADE,
  CONSTRAINT fk_story_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_story_comments_story_created (story_id, created_at),
  INDEX idx_story_comments_status_created (status, created_at)
);

CREATE TABLE IF NOT EXISTS anonymous_story_reports (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  story_id BIGINT NOT NULL,
  reporter_user_id BIGINT NOT NULL,
  reason VARCHAR(120) NOT NULL,
  details TEXT NULL,
  status ENUM('pending','reviewing','resolved','dismissed') NOT NULL DEFAULT 'pending',
  resolved_by_admin_id BIGINT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_story_reports_story FOREIGN KEY (story_id) REFERENCES anonymous_stories(id) ON DELETE CASCADE,
  CONSTRAINT fk_story_reports_user FOREIGN KEY (reporter_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_story_reports_admin FOREIGN KEY (resolved_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_story_reports_status_created (status, created_at),
  INDEX idx_story_reports_story_status (story_id, status)
);

CREATE TABLE IF NOT EXISTS compatibility_duels (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  duel_date DATE NOT NULL,
  status ENUM('open','voted','engaged','expired') NOT NULL DEFAULT 'open',
  selected_option_id BIGINT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_compatibility_duels_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_compatibility_duels_user_date (user_id, duel_date),
  INDEX idx_compatibility_duels_status_date (status, duel_date)
);

CREATE TABLE IF NOT EXISTS compatibility_duel_options (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  duel_id BIGINT NOT NULL,
  candidate_user_id BIGINT NOT NULL,
  compatibility_score_snapshot DECIMAL(5,2) NOT NULL DEFAULT 0,
  compatibility_breakdown_snapshot JSON NULL,
  sort_order SMALLINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_duel_options_duel FOREIGN KEY (duel_id) REFERENCES compatibility_duels(id) ON DELETE CASCADE,
  CONSTRAINT fk_duel_options_candidate FOREIGN KEY (candidate_user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_duel_options_duel_sort (duel_id, sort_order),
  INDEX idx_duel_options_candidate_created (candidate_user_id, created_at)
);

CREATE TABLE IF NOT EXISTS compatibility_duel_choices (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  duel_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  selected_option_id BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_duel_choices_duel FOREIGN KEY (duel_id) REFERENCES compatibility_duels(id) ON DELETE CASCADE,
  CONSTRAINT fk_duel_choices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_duel_choices_option FOREIGN KEY (selected_option_id) REFERENCES compatibility_duel_options(id) ON DELETE CASCADE,
  UNIQUE KEY uq_duel_choices_duel_user (duel_id, user_id),
  INDEX idx_duel_choices_selected_created (selected_option_id, created_at)
);

CREATE TABLE IF NOT EXISTS compatibility_duel_actions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  duel_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  action_type ENUM('view_profile','invite','favorite','discover') NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_duel_actions_duel FOREIGN KEY (duel_id) REFERENCES compatibility_duels(id) ON DELETE CASCADE,
  CONSTRAINT fk_duel_actions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_duel_actions_type_created (action_type, created_at),
  INDEX idx_duel_actions_duel_created (duel_id, created_at)
);

ALTER TABLE anonymous_stories
  MODIFY COLUMN status ENUM('draft','published','hidden','moderated','featured','removed') NOT NULL DEFAULT 'published';
ALTER TABLE anonymous_stories
  ADD COLUMN IF NOT EXISTS is_story_of_day TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS moderation_note VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS last_moderated_by_admin_id BIGINT NULL,
  ADD COLUMN IF NOT EXISTS last_moderated_at DATETIME NULL;

INSERT INTO site_settings (setting_key, setting_value, value_type, updated_at)
VALUES
('daily_route_enable_visitors_hub_task', '1', 'bool', NOW()),
('daily_route_enable_anonymous_stories_task', '1', 'bool', NOW()),
('daily_route_enable_compatibility_duel_task', '1', 'bool', NOW()),
('visitors_free_visible_visitors', '2', 'int', NOW()),
('visitors_free_history_hours', '24', 'int', NOW()),
('visitors_premium_history_days', '30', 'int', NOW()),
('visitors_track_limit_per_hour', '120', 'int', NOW()),
('compatibility_duel_free_daily_limit', '1', 'int', NOW()),
('compatibility_duel_premium_daily_limit', '1', 'int', NOW()),
('compatibility_duel_extra_enabled', '0', 'bool', NOW()),
('compatibility_duel_premium_insights_enabled', '1', 'bool', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), updated_at = VALUES(updated_at);
