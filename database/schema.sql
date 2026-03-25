CREATE DATABASE IF NOT EXISTS rota_do_amor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rota_do_amor;

CREATE TABLE provinces (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE cities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  province_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cities_province FOREIGN KEY (province_id) REFERENCES provinces(id),
  UNIQUE KEY uq_city_province (province_id, name)
);

CREATE TABLE users (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(120) NOT NULL,
  last_name VARCHAR(120) NOT NULL,
  username VARCHAR(120) NULL UNIQUE,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(20) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  birth_date DATE NOT NULL,
  gender ENUM('male','female','other') NOT NULL,
  relationship_goal ENUM('friendship','dating','marriage') NOT NULL,
  province_id INT NOT NULL,
  city_id INT NOT NULL,
  bio TEXT NULL,
  profession VARCHAR(120) NULL,
  education VARCHAR(120) NULL,
  religion VARCHAR(120) NULL,
  height_cm SMALLINT NULL,
  has_children TINYINT(1) DEFAULT 0,
  habits VARCHAR(255) NULL,
  status ENUM('pending_activation','active','expired','suspended','banned','pending_verification') NOT NULL DEFAULT 'pending_activation',
  premium_status ENUM('basic','premium','boosted','verified') NOT NULL DEFAULT 'basic',
  email_verified_at DATETIME NULL,
  email_verification_required TINYINT(1) NOT NULL DEFAULT 1,
  activation_paid_at DATETIME NULL,
  profile_photo_path VARCHAR(255) NULL,
  visibility ENUM('public','hidden') NOT NULL DEFAULT 'public',
  online_status TINYINT(1) NOT NULL DEFAULT 0,
  last_activity_at DATETIME NULL,
  terms_accepted_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_users_province FOREIGN KEY (province_id) REFERENCES provinces(id),
  CONSTRAINT fk_users_city FOREIGN KEY (city_id) REFERENCES cities(id),
  INDEX idx_users_status (status),
  INDEX idx_users_last_activity (last_activity_at)
);

CREATE TABLE user_photos (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_user_photos_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_photos_user (user_id)
);

CREATE TABLE user_interests (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  interest_name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_user_interests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_user_interest (user_id, interest_name)
);

CREATE TABLE user_preferences (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL UNIQUE,
  interested_in ENUM('male','female','all') DEFAULT 'all',
  age_min TINYINT UNSIGNED NOT NULL DEFAULT 18,
  age_max TINYINT UNSIGNED NOT NULL DEFAULT 70,
  preferred_province_id INT NULL,
  preferred_city_id INT NULL,
  preferred_goal ENUM('friendship','dating','marriage','any') DEFAULT 'any',
  max_distance_km INT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_preferences_province FOREIGN KEY (preferred_province_id) REFERENCES provinces(id),
  CONSTRAINT fk_preferences_city FOREIGN KEY (preferred_city_id) REFERENCES cities(id)
);

CREATE TABLE subscriptions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  status ENUM('active','expired','cancelled') NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_subscriptions_user_status (user_id, status),
  INDEX idx_subscriptions_ends_at (ends_at)
);

CREATE TABLE payments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  payment_type ENUM('activation','subscription','boost','premium_feature','identity_verification') NOT NULL,
  phone VARCHAR(20) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'MZN',
  status ENUM('pending','completed','failed','cancelled') NOT NULL,
  debito_reference VARCHAR(191) NULL,
  gateway_raw_response JSON NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_payments_user_type (user_id, payment_type),
  INDEX idx_payments_status (status),
  UNIQUE KEY uq_debito_reference (debito_reference)
);

CREATE TABLE premium_features (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  feature_type VARCHAR(100) NOT NULL,
  status ENUM('active','expired','cancelled') NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_premium_features_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_premium_features_user (user_id, status)
);

CREATE TABLE user_boosts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  payment_id BIGINT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  status ENUM('active','expired','cancelled') NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_boost_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_boost_payment FOREIGN KEY (payment_id) REFERENCES payments(id),
  INDEX idx_boost_user_status (user_id, status)
);

CREATE TABLE admins (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('super_admin','moderator','finance') NOT NULL DEFAULT 'moderator',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE identity_verifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  document_image_path VARCHAR(255) NOT NULL,
  selfie_image_path VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  rejection_reason VARCHAR(500) NULL,
  reviewed_by_admin_id BIGINT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_identity_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_identity_admin FOREIGN KEY (reviewed_by_admin_id) REFERENCES admins(id),
  INDEX idx_identity_status (status)
);

CREATE TABLE email_verifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  token VARCHAR(191) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_email_verifications_user (user_id)
);

CREATE TABLE password_resets (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  email VARCHAR(190) NOT NULL,
  token VARCHAR(191) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_password_resets_user (user_id)
);

CREATE TABLE user_badges (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  badge_type VARCHAR(60) NOT NULL,
  source VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_user_badges_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_user_badges_user_active (user_id, is_active)
);

CREATE TABLE swipe_actions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT NOT NULL,
  target_user_id BIGINT NOT NULL,
  action_type ENUM('like','pass','super_like') NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_swipe_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
  CONSTRAINT fk_swipe_target FOREIGN KEY (target_user_id) REFERENCES users(id),
  UNIQUE KEY uq_swipe_actor_target (actor_user_id, target_user_id),
  INDEX idx_swipe_target (target_user_id, action_type)
);

CREATE TABLE matches (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_one_id BIGINT NOT NULL,
  user_two_id BIGINT NOT NULL,
  matched_at DATETIME NOT NULL,
  status ENUM('active','unmatched','blocked') NOT NULL DEFAULT 'active',
  created_from ENUM('swipe','connection','manual_rule') NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_match_user_one FOREIGN KEY (user_one_id) REFERENCES users(id),
  CONSTRAINT fk_match_user_two FOREIGN KEY (user_two_id) REFERENCES users(id),
  UNIQUE KEY uq_match_pair (user_one_id, user_two_id),
  INDEX idx_matches_status (status)
);

CREATE TABLE compatibility_scores (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  target_user_id BIGINT NOT NULL,
  score DECIMAL(5,2) NOT NULL,
  breakdown_json JSON NOT NULL,
  calculated_at DATETIME NOT NULL,
  CONSTRAINT fk_comp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_comp_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_comp_pair (user_id, target_user_id),
  INDEX idx_comp_score (score)
);

CREATE TABLE connections (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  requester_id BIGINT NOT NULL,
  receiver_id BIGINT NOT NULL,
  status ENUM('pending','accepted','rejected','blocked') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_connections_requester FOREIGN KEY (requester_id) REFERENCES users(id),
  CONSTRAINT fk_connections_receiver FOREIGN KEY (receiver_id) REFERENCES users(id),
  UNIQUE KEY uq_connection_pair (requester_id, receiver_id)
);

CREATE TABLE conversations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_one_id BIGINT NOT NULL,
  user_two_id BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_conversation_user_one FOREIGN KEY (user_one_id) REFERENCES users(id),
  CONSTRAINT fk_conversation_user_two FOREIGN KEY (user_two_id) REFERENCES users(id),
  UNIQUE KEY uq_conversation_pair (user_one_id, user_two_id)
);

CREATE TABLE messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT NOT NULL,
  sender_id BIGINT NOT NULL,
  receiver_id BIGINT NOT NULL,
  message_text TEXT NOT NULL,
  message_type ENUM('text','image','system') NOT NULL DEFAULT 'text',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id),
  CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users(id),
  INDEX idx_messages_receiver_read (receiver_id, is_read)
);

CREATE TABLE posts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  content TEXT NOT NULL,
  status ENUM('active','hidden','deleted') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_posts_status_created (status, created_at)
);

CREATE TABLE post_images (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_post_images_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

CREATE TABLE post_likes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_post_likes_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_likes_user FOREIGN KEY (user_id) REFERENCES users(id),
  UNIQUE KEY uq_post_like (post_id, user_id)
);

CREATE TABLE post_comments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  comment_text TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_post_comments_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_comments_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE favorites (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  favorite_user_id BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_favorites_target FOREIGN KEY (favorite_user_id) REFERENCES users(id),
  UNIQUE KEY uq_favorite_pair (user_id, favorite_user_id)
);

CREATE TABLE blocks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT NOT NULL,
  target_user_id BIGINT NOT NULL,
  reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_blocks_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
  CONSTRAINT fk_blocks_target FOREIGN KEY (target_user_id) REFERENCES users(id),
  UNIQUE KEY uq_block_pair (actor_user_id, target_user_id)
);

CREATE TABLE reports (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  reporter_user_id BIGINT NOT NULL,
  target_user_id BIGINT NULL,
  target_post_id BIGINT NULL,
  target_message_id BIGINT NULL,
  report_type ENUM('profile','post','message') NOT NULL,
  reason VARCHAR(255) NOT NULL,
  details TEXT NULL,
  status ENUM('pending','resolved','dismissed') NOT NULL DEFAULT 'pending',
  resolved_by_admin_id BIGINT NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_user_id) REFERENCES users(id),
  CONSTRAINT fk_reports_target_user FOREIGN KEY (target_user_id) REFERENCES users(id),
  CONSTRAINT fk_reports_target_post FOREIGN KEY (target_post_id) REFERENCES posts(id),
  CONSTRAINT fk_reports_target_message FOREIGN KEY (target_message_id) REFERENCES messages(id),
  CONSTRAINT fk_reports_resolved_admin FOREIGN KEY (resolved_by_admin_id) REFERENCES admins(id),
  INDEX idx_reports_status (status)
);

CREATE TABLE notifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  type VARCHAR(100) NOT NULL,
  title VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  payload_json JSON NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_notifications_user_read (user_id, is_read)
);

CREATE TABLE activity_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('user','admin','system') NOT NULL,
  actor_id BIGINT NULL,
  action VARCHAR(190) NOT NULL,
  target_type VARCHAR(120) NULL,
  target_id BIGINT NULL,
  metadata_json JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_activity_actor (actor_type, actor_id),
  INDEX idx_activity_action (action)
);

CREATE TABLE moderation_actions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  admin_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  action_type ENUM('suspend','ban','unsuspend','warn','remove_content') NOT NULL,
  reason VARCHAR(255) NOT NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_moderation_admin FOREIGN KEY (admin_id) REFERENCES admins(id),
  CONSTRAINT fk_moderation_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE site_settings (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value TEXT NOT NULL,
  value_type ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
  updated_at DATETIME NOT NULL
);

CREATE TABLE banners (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  link_url VARCHAR(255) NULL,
  position_key VARCHAR(60) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE financial_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  payment_id BIGINT NOT NULL,
  entry_type ENUM('revenue','refund','chargeback','adjustment') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'MZN',
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_financial_payment FOREIGN KEY (payment_id) REFERENCES payments(id)
);
