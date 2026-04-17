ALTER TABLE posts
  ADD COLUMN IF NOT EXISTS post_mood VARCHAR(80) NULL AFTER status,
  ADD COLUMN IF NOT EXISTS relational_phase VARCHAR(80) NULL AFTER post_mood,
  ADD COLUMN IF NOT EXISTS origin_type VARCHAR(40) NOT NULL DEFAULT 'normal' AFTER relational_phase,
  ADD INDEX IF NOT EXISTS idx_posts_origin_created (origin_type, created_at),
  ADD INDEX IF NOT EXISTS idx_posts_mood_phase (post_mood, relational_phase, created_at);

CREATE TABLE IF NOT EXISTS post_reactions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  reaction_type VARCHAR(60) NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_post_reactions_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_reactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_post_reaction (post_id, user_id),
  INDEX idx_post_reactions_type_created (reaction_type, created_at),
  INDEX idx_post_reactions_post_type (post_id, reaction_type)
);

CREATE TABLE IF NOT EXISTS feed_prompts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  prompt_text VARCHAR(255) NOT NULL,
  category VARCHAR(80) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_feed_prompts_active_sort (is_active, is_featured, sort_order)
);

CREATE TABLE IF NOT EXISTS post_prompt_answers (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL,
  prompt_id BIGINT NOT NULL,
  prompt_snapshot VARCHAR(255) NOT NULL,
  answer_text TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_post_prompt_answers_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_prompt_answers_prompt FOREIGN KEY (prompt_id) REFERENCES feed_prompts(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_post_prompt_answer (post_id)
);

CREATE TABLE IF NOT EXISTS post_polls (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL,
  question VARCHAR(255) NOT NULL,
  status ENUM('active','closed','deleted') NOT NULL DEFAULT 'active',
  ends_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_post_polls_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  UNIQUE KEY uq_post_poll_post (post_id),
  INDEX idx_post_polls_status_end (status, ends_at)
);

CREATE TABLE IF NOT EXISTS post_poll_options (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  poll_id BIGINT NOT NULL,
  option_text VARCHAR(190) NOT NULL,
  sort_order SMALLINT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_post_poll_options_poll FOREIGN KEY (poll_id) REFERENCES post_polls(id) ON DELETE CASCADE,
  INDEX idx_post_poll_options_poll_sort (poll_id, sort_order)
);

CREATE TABLE IF NOT EXISTS post_poll_votes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  poll_id BIGINT NOT NULL,
  option_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_post_poll_votes_poll FOREIGN KEY (poll_id) REFERENCES post_polls(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_poll_votes_option FOREIGN KEY (option_id) REFERENCES post_poll_options(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_poll_votes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_post_poll_vote_user (poll_id, user_id),
  INDEX idx_post_poll_votes_option (option_id, created_at)
);

CREATE TABLE IF NOT EXISTS post_private_interests (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL,
  sender_user_id BIGINT NOT NULL,
  receiver_user_id BIGINT NOT NULL,
  interest_type VARCHAR(80) NOT NULL,
  message_optional VARCHAR(240) NULL,
  status ENUM('sent','viewed','dismissed','converted') NOT NULL DEFAULT 'sent',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_post_private_interests_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_private_interests_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_private_interests_receiver FOREIGN KEY (receiver_user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_post_private_interest (post_id, sender_user_id),
  INDEX idx_post_private_interest_receiver (receiver_user_id, status, created_at)
);

CREATE TABLE IF NOT EXISTS user_social_availability (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  availability_type VARCHAR(60) NOT NULL,
  status ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_user_social_availability_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_social_availability_user_status_end (user_id, status, ends_at),
  INDEX idx_user_social_availability_status_end (status, ends_at)
);

CREATE TABLE IF NOT EXISTS post_diary_shares (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  post_id BIGINT NOT NULL,
  diary_entry_id BIGINT NOT NULL,
  share_mode ENUM('publico','so_matches','so_interessados','anonimo') NOT NULL DEFAULT 'publico',
  is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_post_diary_shares_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_post_diary_shares_diary FOREIGN KEY (diary_entry_id) REFERENCES diary_entries(id) ON DELETE CASCADE,
  UNIQUE KEY uq_post_diary_share (post_id),
  INDEX idx_post_diary_shares_diary (diary_entry_id, created_at)
);

INSERT INTO feed_prompts (prompt_text, category, is_active, is_featured, sort_order, created_at, updated_at)
SELECT * FROM (
  SELECT 'O que em ti está mais pronto para amar nesta fase?' AS prompt_text, 'autoconhecimento' AS category, 1 AS is_active, 1 AS is_featured, 1 AS sort_order, NOW() AS created_at, NOW() AS updated_at
  UNION ALL SELECT 'Qual pequena atitude de carinho mudaria teu dia hoje?', 'romance', 1, 1, 2, NOW(), NOW()
  UNION ALL SELECT 'Que tipo de conversa te faz sentir visto(a) de verdade?', 'conexao', 1, 0, 3, NOW(), NOW()
) seeded
WHERE NOT EXISTS (SELECT 1 FROM feed_prompts fp WHERE fp.prompt_text = seeded.prompt_text);
