-- ============================================================================
-- Rota_do_amor: schema autoritativo (instalação nova)
-- ============================================================================
-- Importação (phpMyAdmin / shared hosting / local):
-- 1) Selecione uma base de dados já existente.
-- 2) Importe este ficheiro completo.
-- 3) Não é necessário CREATE DATABASE/USE neste ficheiro.
--
-- Este schema já consolida estrutura final + constraints + índices + seeds essenciais.
-- Migrations em database/migrations ficam como histórico de upgrade para bases legadas.
-- ============================================================================

-- 2) TABELAS BASE GEOGRÁFICAS

CREATE TABLE IF NOT EXISTS provinces (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  province_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cities_province FOREIGN KEY (province_id) REFERENCES provinces(id),
  UNIQUE KEY uq_city_province (province_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed mínimo de localização executado cedo para evitar instalações parciais sem províncias/cidades.
INSERT IGNORE INTO provinces (name) VALUES
('Cabo Delgado'),
('Gaza'),
('Inhambane'),
('Manica'),
('Maputo Cidade'),
('Maputo Província'),
('Nampula'),
('Niassa'),
('Sofala'),
('Tete'),
('Zambézia');

INSERT IGNORE INTO cities (province_id, name)
SELECT p.id, c.city_name
FROM provinces p
JOIN (
  SELECT 'Maputo Cidade' AS province_name, 'Maputo' AS city_name UNION ALL
  SELECT 'Maputo Cidade', 'KaMpfumo' UNION ALL
  SELECT 'Maputo Cidade', 'KaMavota' UNION ALL
  SELECT 'Maputo Cidade', 'KaMubukwana' UNION ALL
  SELECT 'Maputo Cidade', 'KaTembe' UNION ALL
  SELECT 'Maputo Província', 'Matola' UNION ALL
  SELECT 'Maputo Província', 'Boane' UNION ALL
  SELECT 'Maputo Província', 'Marracuene' UNION ALL
  SELECT 'Maputo Província', 'Moamba' UNION ALL
  SELECT 'Maputo Província', 'Namaacha' UNION ALL
  SELECT 'Maputo Província', 'Matutuíne' UNION ALL
  SELECT 'Maputo Província', 'Manhiça' UNION ALL
  SELECT 'Maputo Província', 'Magude' UNION ALL
  SELECT 'Gaza', 'Xai-Xai' UNION ALL
  SELECT 'Gaza', 'Chókwè' UNION ALL
  SELECT 'Gaza', 'Chibuto' UNION ALL
  SELECT 'Gaza', 'Macia' UNION ALL
  SELECT 'Gaza', 'Bilene' UNION ALL
  SELECT 'Inhambane', 'Inhambane' UNION ALL
  SELECT 'Inhambane', 'Maxixe' UNION ALL
  SELECT 'Inhambane', 'Vilankulo' UNION ALL
  SELECT 'Inhambane', 'Massinga' UNION ALL
  SELECT 'Inhambane', 'Jangamo' UNION ALL
  SELECT 'Sofala', 'Beira' UNION ALL
  SELECT 'Sofala', 'Dondo' UNION ALL
  SELECT 'Sofala', 'Nhamatanda' UNION ALL
  SELECT 'Sofala', 'Gorongosa' UNION ALL
  SELECT 'Sofala', 'Buzi' UNION ALL
  SELECT 'Manica', 'Chimoio' UNION ALL
  SELECT 'Manica', 'Manica' UNION ALL
  SELECT 'Manica', 'Gondola' UNION ALL
  SELECT 'Manica', 'Sussundenga' UNION ALL
  SELECT 'Tete', 'Tete' UNION ALL
  SELECT 'Tete', 'Moatize' UNION ALL
  SELECT 'Tete', 'Ulongué' UNION ALL
  SELECT 'Tete', 'Angónia' UNION ALL
  SELECT 'Zambézia', 'Quelimane' UNION ALL
  SELECT 'Zambézia', 'Mocuba' UNION ALL
  SELECT 'Zambézia', 'Gurué' UNION ALL
  SELECT 'Zambézia', 'Milange' UNION ALL
  SELECT 'Zambézia', 'Mocubela' UNION ALL
  SELECT 'Nampula', 'Nampula' UNION ALL
  SELECT 'Nampula', 'Nacala' UNION ALL
  SELECT 'Nampula', 'Ilha de Moçambique' UNION ALL
  SELECT 'Nampula', 'Nacala-a-Velha' UNION ALL
  SELECT 'Nampula', 'Monapo' UNION ALL
  SELECT 'Cabo Delgado', 'Pemba' UNION ALL
  SELECT 'Cabo Delgado', 'Montepuez' UNION ALL
  SELECT 'Cabo Delgado', 'Mocímboa da Praia' UNION ALL
  SELECT 'Cabo Delgado', 'Mueda' UNION ALL
  SELECT 'Niassa', 'Lichinga' UNION ALL
  SELECT 'Niassa', 'Cuamba' UNION ALL
  SELECT 'Niassa', 'Mandimba' UNION ALL
  SELECT 'Niassa', 'Marrupa'
) c ON c.province_name = p.name;


-- 3) UTILIZADORES E IDENTIDADE

CREATE TABLE IF NOT EXISTS users (
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

CREATE TABLE IF NOT EXISTS user_photos (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  thumbnail_path VARCHAR(255) NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_user_photos_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_photos_user (user_id)
);

CREATE TABLE IF NOT EXISTS user_interests (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  interest_name VARCHAR(120) NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_user_interests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_interests_user_interest (user_id, interest_name),
  UNIQUE KEY uq_user_interest (user_id, interest_name)
);

CREATE TABLE IF NOT EXISTS user_preferences (
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


-- 4) SUBSCRIÇÃO, PAGAMENTOS E PREMIUM

CREATE TABLE IF NOT EXISTS subscriptions (
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

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  payment_type ENUM('activation','subscription','boost','premium_feature','identity_verification') NOT NULL,
  phone VARCHAR(20) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'MZN',
  status ENUM('pending','completed','failed','cancelled') NOT NULL,
  benefit_application_status ENUM('pending','applying','applied','failed','skipped') NOT NULL DEFAULT 'pending',
  benefit_applied_at DATETIME NULL,
  debito_reference VARCHAR(191) NULL,
  gateway_raw_response JSON NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_payments_user_type (user_id, payment_type),
  INDEX idx_payments_status (status),
  INDEX idx_payments_type_status (payment_type, status),
  INDEX idx_payments_benefit_status (benefit_application_status),
  UNIQUE KEY uq_debito_reference (debito_reference)
);

CREATE TABLE IF NOT EXISTS premium_features (
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

CREATE TABLE IF NOT EXISTS user_boosts (
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

CREATE TABLE IF NOT EXISTS admins (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('super_admin','moderator','finance','support','ops','content_moderator') NOT NULL DEFAULT 'moderator',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS identity_verifications (
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
  INDEX idx_identity_status (status),
  INDEX idx_identity_user_status (user_id, status)
);

CREATE TABLE IF NOT EXISTS email_verifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  token VARCHAR(191) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_email_verifications_user (user_id)
);

CREATE TABLE IF NOT EXISTS password_resets (
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

CREATE TABLE IF NOT EXISTS user_badges (
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


-- 5) CONEXÕES, CONVITES, MATCHES E CONVERSAS

CREATE TABLE IF NOT EXISTS swipe_actions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT NOT NULL,
  target_user_id BIGINT NOT NULL,
  action_type ENUM('like','pass','super_like') NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_swipe_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
  CONSTRAINT fk_swipe_target FOREIGN KEY (target_user_id) REFERENCES users(id),
  CONSTRAINT chk_swipe_distinct_users CHECK (actor_user_id <> target_user_id),
  UNIQUE KEY uq_swipe_actor_target (actor_user_id, target_user_id),
  INDEX idx_swipe_target (target_user_id, action_type)
);

CREATE TABLE IF NOT EXISTS matches (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_one_id BIGINT NOT NULL,
  user_two_id BIGINT NOT NULL,
  matched_at DATETIME NOT NULL,
  status ENUM('active','unmatched','blocked') NOT NULL DEFAULT 'active',
  created_from ENUM('swipe','connection','manual_rule') NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_match_user_one FOREIGN KEY (user_one_id) REFERENCES users(id),
  CONSTRAINT fk_match_user_two FOREIGN KEY (user_two_id) REFERENCES users(id),
  CONSTRAINT chk_matches_canonical CHECK (user_one_id < user_two_id),
  UNIQUE KEY uq_match_pair (user_one_id, user_two_id),
  INDEX idx_matches_status (status)
);

CREATE TABLE IF NOT EXISTS compatibility_scores (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  target_user_id BIGINT NOT NULL,
  score DECIMAL(5,2) NOT NULL,
  breakdown_json JSON NOT NULL,
  calculated_at DATETIME NOT NULL,
  CONSTRAINT fk_comp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_comp_target FOREIGN KEY (target_user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_comp_pair (user_id, target_user_id),
  INDEX idx_comp_score (score),
  INDEX idx_comp_user_calculated (user_id, calculated_at)
);


CREATE TABLE IF NOT EXISTS user_connection_modes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  current_intention VARCHAR(60) NOT NULL,
  relational_pace VARCHAR(40) NOT NULL,
  openness_level VARCHAR(40) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_user_connection_modes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_user_connection_modes_user (user_id),
  INDEX idx_connection_modes_intention (current_intention),
  INDEX idx_connection_modes_pace (relational_pace)
);

CREATE TABLE IF NOT EXISTS connections (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  requester_id BIGINT NOT NULL,
  receiver_id BIGINT NOT NULL,
  status ENUM('pending','accepted','rejected','blocked') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_connections_requester FOREIGN KEY (requester_id) REFERENCES users(id),
  CONSTRAINT fk_connections_receiver FOREIGN KEY (receiver_id) REFERENCES users(id),
  CONSTRAINT chk_connections_distinct_users CHECK (requester_id <> receiver_id),
  UNIQUE KEY uq_connection_pair (requester_id, receiver_id)
);



CREATE TABLE IF NOT EXISTS connection_invites (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sender_user_id BIGINT NOT NULL,
  receiver_user_id BIGINT NOT NULL,
  status ENUM('pending','accepted','declined','expired','cancelled') NOT NULL DEFAULT 'pending',
  invitation_type ENUM('standard','priority') NOT NULL DEFAULT 'standard',
  opening_message VARCHAR(500) NULL,
  current_intention_snapshot VARCHAR(60) NOT NULL,
  relational_pace_snapshot VARCHAR(40) NOT NULL,
  compatibility_score_snapshot DECIMAL(5,2) NOT NULL DEFAULT 0,
  compatibility_breakdown_snapshot JSON NULL,
  pending_guard TINYINT GENERATED ALWAYS AS (
    CASE WHEN status = 'pending' THEN 1 ELSE NULL END
  ) STORED,
  responded_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_connection_invites_sender FOREIGN KEY (sender_user_id) REFERENCES users(id),
  CONSTRAINT fk_connection_invites_receiver FOREIGN KEY (receiver_user_id) REFERENCES users(id),
  INDEX idx_connection_invites_receiver_status_created (receiver_user_id, status, created_at),
  INDEX idx_connection_invites_sender_status_created (sender_user_id, status, created_at),
  INDEX idx_connection_invites_type_status (invitation_type, status),
  UNIQUE KEY uq_connection_invites_pending_once (sender_user_id, receiver_user_id, pending_guard)
);

CREATE TABLE IF NOT EXISTS conversations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_one_id BIGINT NOT NULL,
  user_two_id BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_conversation_user_one FOREIGN KEY (user_one_id) REFERENCES users(id),
  CONSTRAINT fk_conversation_user_two FOREIGN KEY (user_two_id) REFERENCES users(id),
  CONSTRAINT chk_conversations_canonical CHECK (user_one_id < user_two_id),
  UNIQUE KEY uq_conversation_pair (user_one_id, user_two_id)
);

CREATE TABLE IF NOT EXISTS messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT NOT NULL,
  sender_id BIGINT NOT NULL,
  receiver_id BIGINT NOT NULL,
  message_text TEXT NOT NULL,
  message_type ENUM('text','image','system') NOT NULL DEFAULT 'text',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  sent_at DATETIME NOT NULL,
  delivered_at DATETIME NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id),
  CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users(id),
  CONSTRAINT chk_messages_sender_receiver CHECK (sender_id <> receiver_id),
  INDEX idx_messages_receiver_read (receiver_id, is_read),
  INDEX idx_messages_conversation_id (conversation_id, id),
  INDEX idx_messages_conversation_receiver_read (conversation_id, receiver_id, is_read, id),
  INDEX idx_messages_delivery (conversation_id, receiver_id, delivered_at, read_at)
);

CREATE TABLE IF NOT EXISTS message_typing_states (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_typing_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_typing_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_typing_conversation_user (conversation_id, user_id),
  INDEX idx_typing_expires (conversation_id, expires_at)
);


-- 6) FEED SOCIAL

CREATE TABLE IF NOT EXISTS posts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  content TEXT NOT NULL,
  status ENUM('active','hidden','deleted') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_posts_status_created (status, created_at),
  INDEX idx_posts_user_status_created (user_id, status, created_at)
);

CREATE TABLE IF NOT EXISTS post_images (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  thumbnail_path VARCHAR(255) NULL,
  mime_type VARCHAR(120) NULL,
  file_size INT NOT NULL DEFAULT 0,
  sort_order SMALLINT NOT NULL DEFAULT 1,
  created_by_user_id BIGINT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_post_images_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_images_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_post_images_post_sort (post_id, sort_order, id)
);

CREATE TABLE IF NOT EXISTS message_attachments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  message_id BIGINT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NULL,
  file_size INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_message_attachments_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
  INDEX idx_message_attachments_message (message_id, id)
);


-- 7) ENCONTROS SEGUROS

CREATE TABLE IF NOT EXISTS safe_dates (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  initiator_user_id BIGINT NOT NULL,
  invitee_user_id BIGINT NOT NULL,
  match_id BIGINT NULL,
  conversation_id BIGINT NULL,
  title VARCHAR(160) NOT NULL,
  meeting_type ENUM('coffee','lunch','dinner','walk','event','video_call','other') NOT NULL DEFAULT 'coffee',
  proposed_location VARCHAR(255) NOT NULL,
  proposed_datetime DATETIME NOT NULL,
  note VARCHAR(500) NULL,
  status ENUM('proposed','accepted','declined','cancelled','reschedule_requested','rescheduled','completed','expired') NOT NULL DEFAULT 'proposed',
  pair_user_low BIGINT GENERATED ALWAYS AS (LEAST(initiator_user_id, invitee_user_id)) STORED,
  pair_user_high BIGINT GENERATED ALWAYS AS (GREATEST(initiator_user_id, invitee_user_id)) STORED,
  open_pair_guard TINYINT GENERATED ALWAYS AS (
    CASE
      WHEN status IN ('proposed','accepted','reschedule_requested','rescheduled') THEN 1
      ELSE NULL
    END
  ) STORED,
  safety_level ENUM('standard','verified_only','premium_guard') NOT NULL DEFAULT 'standard',
  confirmation_code VARCHAR(20) NULL,
  accepted_at DATETIME NULL,
  declined_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  completed_at DATETIME NULL,
  reschedule_requested_by_user_id BIGINT NULL,
  reschedule_requested_at DATETIME NULL,
  reschedule_proposed_datetime DATETIME NULL,
  expires_at DATETIME NULL,
  last_transition_at DATETIME NULL,
  reminder_24h_sent_at DATETIME NULL,
  reminder_2h_sent_at DATETIME NULL,
  reminder_same_day_sent_at DATETIME NULL,
  arrived_confirmed_at DATETIME NULL,
  arrived_confirmed_by_user_id BIGINT NULL,
  ended_well_confirmed_at DATETIME NULL,
  ended_well_confirmed_by_user_id BIGINT NULL,
  safety_signal_level ENUM('none','attention','emergency') NOT NULL DEFAULT 'none',
  safety_signal_note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_safe_dates_initiator FOREIGN KEY (initiator_user_id) REFERENCES users(id),
  CONSTRAINT fk_safe_dates_invitee FOREIGN KEY (invitee_user_id) REFERENCES users(id),
  CONSTRAINT fk_safe_dates_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE SET NULL,
  CONSTRAINT fk_safe_dates_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
  CONSTRAINT fk_safe_dates_reschedule_requested_by FOREIGN KEY (reschedule_requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_safe_dates_arrived_by FOREIGN KEY (arrived_confirmed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_safe_dates_ended_well_by FOREIGN KEY (ended_well_confirmed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_safe_dates_distinct_users CHECK (initiator_user_id <> invitee_user_id),
  INDEX idx_safe_dates_initiator_status_created (initiator_user_id, status, created_at),
  INDEX idx_safe_dates_invitee_status_created (invitee_user_id, status, created_at),
  INDEX idx_safe_dates_status_datetime (status, proposed_datetime),
  INDEX idx_safe_dates_pair_status (initiator_user_id, invitee_user_id, status),
  INDEX idx_safe_dates_expires (status, expires_at),
  INDEX idx_safe_dates_reschedule_pending (status, reschedule_requested_by_user_id, reschedule_requested_at),
  INDEX idx_safe_dates_reminder_windows (status, proposed_datetime, reminder_24h_sent_at, reminder_2h_sent_at),
  INDEX idx_safe_dates_admin_filters (status, safety_level, created_at, id),
  INDEX idx_safe_dates_admin_participants (initiator_user_id, invitee_user_id, created_at),
  UNIQUE KEY uq_safe_dates_open_pair (pair_user_low, pair_user_high, open_pair_guard)
);

CREATE TABLE IF NOT EXISTS safe_date_status_history (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  safe_date_id BIGINT NOT NULL,
  actor_user_id BIGINT NULL,
  old_status ENUM('proposed','accepted','declined','cancelled','reschedule_requested','rescheduled','completed','expired') NULL,
  new_status ENUM('proposed','accepted','declined','cancelled','reschedule_requested','rescheduled','completed','expired') NOT NULL,
  reason VARCHAR(255) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_safe_date_history_date FOREIGN KEY (safe_date_id) REFERENCES safe_dates(id) ON DELETE CASCADE,
  CONSTRAINT fk_safe_date_history_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_safe_date_history_date_created (safe_date_id, created_at),
  INDEX idx_safe_date_history_actor_created (actor_user_id, created_at)
);



CREATE TABLE IF NOT EXISTS safe_date_private_feedback (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  safe_date_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  rating TINYINT NULL,
  feedback_note VARCHAR(500) NULL,
  safety_signal ENUM('none','attention','emergency') NOT NULL DEFAULT 'none',
  safety_note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_safe_date_private_feedback_date FOREIGN KEY (safe_date_id) REFERENCES safe_dates(id) ON DELETE CASCADE,
  CONSTRAINT fk_safe_date_private_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_safe_date_private_feedback_pair (safe_date_id, user_id),
  INDEX idx_safe_date_private_feedback_signal (safety_signal, created_at)
);

CREATE TABLE IF NOT EXISTS post_likes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_post_likes_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_likes_user FOREIGN KEY (user_id) REFERENCES users(id),
  UNIQUE KEY uq_post_like (post_id, user_id)
);

CREATE TABLE IF NOT EXISTS post_comments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  parent_comment_id BIGINT NULL,
  comment_text TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_post_comments_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_comments_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_post_comments_parent FOREIGN KEY (parent_comment_id) REFERENCES post_comments(id) ON DELETE CASCADE,
  INDEX idx_post_comments_post_created (post_id, created_at),
  INDEX idx_post_comments_parent_created (parent_comment_id, created_at)
);

CREATE TABLE IF NOT EXISTS favorites (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  favorite_user_id BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_favorites_target FOREIGN KEY (favorite_user_id) REFERENCES users(id),
  CONSTRAINT chk_favorites_distinct_users CHECK (user_id <> favorite_user_id),
  UNIQUE KEY uq_favorite_pair (user_id, favorite_user_id)
);

CREATE TABLE IF NOT EXISTS blocks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT NOT NULL,
  target_user_id BIGINT NOT NULL,
  reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_blocks_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
  CONSTRAINT fk_blocks_target FOREIGN KEY (target_user_id) REFERENCES users(id),
  CONSTRAINT chk_blocks_distinct_users CHECK (actor_user_id <> target_user_id),
  UNIQUE KEY uq_block_pair (actor_user_id, target_user_id)
);

CREATE TABLE IF NOT EXISTS reports (
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
  CONSTRAINT chk_reports_not_self_target CHECK (target_user_id IS NULL OR reporter_user_id <> target_user_id),
  CONSTRAINT chk_reports_target_coherence CHECK (
    (report_type = 'profile' AND target_user_id IS NOT NULL AND target_post_id IS NULL AND target_message_id IS NULL)
    OR (report_type = 'post' AND target_post_id IS NOT NULL AND target_user_id IS NULL AND target_message_id IS NULL)
    OR (report_type = 'message' AND target_message_id IS NOT NULL AND target_user_id IS NULL AND target_post_id IS NULL)
  ),
  INDEX idx_reports_status (status)
);



-- 8) DIÁRIO E GAMIFICAÇÃO

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

CREATE TABLE IF NOT EXISTS daily_route_nudge_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  route_id BIGINT NOT NULL,
  nudge_type VARCHAR(80) NOT NULL,
  segment VARCHAR(60) NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_daily_route_nudges_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_daily_route_nudges_route FOREIGN KEY (route_id) REFERENCES daily_routes(id) ON DELETE CASCADE,
  INDEX idx_daily_route_nudges_route_type (route_id, nudge_type, created_at),
  INDEX idx_daily_route_nudges_user_created (user_id, created_at)
);

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


-- 9) NOTIFICAÇÕES E AUDITORIA

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  type VARCHAR(100) NOT NULL,
  title VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  payload_json JSON NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  sent_at DATETIME NOT NULL,
  delivered_at DATETIME NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_notifications_user_read (user_id, is_read),
  INDEX idx_notifications_user_delivery (user_id, delivered_at, read_at, sent_at)
);

CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  actor_type ENUM('user','admin','system') NOT NULL,
  actor_id BIGINT NULL,
  action VARCHAR(190) NOT NULL,
  target_type VARCHAR(120) NULL,
  target_id BIGINT NULL,
  rate_limit_key VARCHAR(190) NULL,
  rate_limit_outcome VARCHAR(24) NULL,
  metadata_json JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_activity_actor (actor_type, actor_id),
  INDEX idx_activity_action (action),
  INDEX idx_activity_rate_limit_key (action, target_type, rate_limit_key, rate_limit_outcome, created_at),
  INDEX idx_activity_rate_limit_lookup (action, target_type, created_at),
  INDEX idx_activity_actor_created (actor_id, created_at),
  INDEX idx_activity_target (target_type, target_id, created_at),
  INDEX idx_activity_actor_type_id_created (actor_type, actor_id, created_at)
);

CREATE TABLE IF NOT EXISTS moderation_actions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  admin_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  action_type ENUM('suspend','ban','unsuspend','unban','activate','deactivate','warn','remove_content') NOT NULL,
  reason VARCHAR(255) NOT NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_moderation_admin FOREIGN KEY (admin_id) REFERENCES admins(id),
  CONSTRAINT fk_moderation_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS site_settings (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value TEXT NOT NULL,
  value_type ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
  updated_at DATETIME NOT NULL
);



CREATE TABLE IF NOT EXISTS diary_entries (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  title VARCHAR(190) NULL,
  content TEXT NOT NULL,
  mood VARCHAR(80) NULL,
  emotional_state VARCHAR(120) NULL,
  relational_focus VARCHAR(120) NULL,
  visibility ENUM('private','trusted_circle','public') NOT NULL DEFAULT 'private',
  tags_json JSON NULL,
  intention_snapshot VARCHAR(60) NULL,
  relational_pace_snapshot VARCHAR(40) NULL,
  archived_at DATETIME NULL,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_diary_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_diary_user_created (user_id, created_at),
  INDEX idx_diary_mood_created (mood, created_at),
  INDEX idx_diary_visibility (visibility),
  INDEX idx_diary_user_deleted_created (user_id, deleted_at, created_at),
  INDEX idx_diary_deleted_created (deleted_at, created_at)
);

CREATE TABLE IF NOT EXISTS banners (
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

CREATE TABLE IF NOT EXISTS financial_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  payment_id BIGINT NOT NULL,
  entry_type ENUM('revenue','refund','chargeback','adjustment') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'MZN',
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_financial_payment FOREIGN KEY (payment_id) REFERENCES payments(id)
);

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


-- 10) HISTÓRIAS ANÓNIMAS

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


-- 11) DUELO DE COMPATIBILIDADE

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


-- 12) SEEDS ESSENCIAIS
-- Nota: seed de províncias/cidades já executado no topo (junto das tabelas base)
-- para evitar importações incompletas sem dados geográficos.

-- 13) SETTINGS BASE DO SISTEMA
INSERT INTO site_settings (setting_key, setting_value, value_type, updated_at) VALUES
('activation_price', '100', 'int', NOW()),
('monthly_subscription_price', '40', 'int', NOW()),
('boost_price', '25', 'int', NOW()),
('boost_duration_hours', '24', 'int', NOW()),
('email_verification_required', 'true', 'bool', NOW()),
('allow_chat_only_after_match', 'true', 'bool', NOW()),
('invites_expiration_days', '7', 'int', NOW()),
('safe_dates_premium_guard_enabled', 'true', 'bool', NOW()),
('safe_dates_free_daily_limit', '5', 'int', NOW()),
('safe_dates_premium_daily_limit', '10', 'int', NOW()),
('safe_dates_verified_only_requires_identity', 'true', 'bool', NOW()),
('safe_dates_max_open_free', '2', 'int', NOW()),
('safe_dates_max_open_premium', '5', 'int', NOW()),
('daily_route_reward_boost_hours', '2', 'int', NOW()),
('daily_route_reward_badge_type', 'constancia_diaria', 'string', NOW()),
('daily_route_reward_boost_hours_premium', '3', 'int', NOW()),
('daily_route_streak_bonus_threshold', '7', 'int', NOW()),
('daily_route_streak_bonus_boost_hours', '1', 'int', NOW()),
('daily_route_target_discover_active', '8', 'int', NOW()),
('daily_route_target_discover_default', '5', 'int', NOW()),
('daily_route_target_feed_interactions', '2', 'int', NOW()),
('daily_route_target_premium_momentum', '1', 'int', NOW()),
('daily_route_premium_streak_bonus_threshold', '10', 'int', NOW()),
('daily_route_premium_streak_bonus_boost_hours', '1', 'int', NOW()),
('daily_route_premium_discovery_priority_hours', '2', 'int', NOW()),
('daily_route_reward_badge_days', '30', 'int', NOW()),
('daily_route_reward_badge_days_premium', '45', 'int', NOW()),
('daily_route_nudge_end_of_day_hour', '19', 'int', NOW()),
('daily_route_nudge_inactive_days', '3', 'int', NOW()),
('daily_route_nudge_streak_risk_min_streak', '2', 'int', NOW()),
('daily_route_nudge_new_user_window_days', '14', 'int', NOW()),
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
ON DUPLICATE KEY UPDATE
  setting_value = VALUES(setting_value),
  value_type = VALUES(value_type),
  updated_at = VALUES(updated_at);

-- Admin inicial (idempotente, apenas bootstrap de instalação nova).
-- Credencial inicial prevista: admin@rotadoamor.mz / Admin@123. Trocar imediatamente após primeiro login.
-- ON DUPLICATE KEY UPDATE NÃO altera email/password para evitar sobrescrita acidental em reimportações.
INSERT INTO admins (name, email, password, role, status, created_at, updated_at)
VALUES (
  'Super Admin',
  'admin@rotadoamor.mz',
  '$2y$10$QQq7P4ElG29E6Ytv8PHdPu2jX4rAtA3ahUBygQzf1sY4pVH4A8M7a',
  'super_admin',
  'active',
  NOW(),
  NOW()
)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  role = VALUES(role),
  status = VALUES(status),
  updated_at = VALUES(updated_at);
