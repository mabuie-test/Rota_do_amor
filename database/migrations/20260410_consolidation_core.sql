ALTER TABLE user_interests
  ADD INDEX idx_user_interests_user_interest (user_id, interest_name);

ALTER TABLE activity_logs
  ADD COLUMN rate_limit_key VARCHAR(190) NULL AFTER target_id,
  ADD COLUMN rate_limit_outcome VARCHAR(24) NULL AFTER rate_limit_key,
  ADD INDEX idx_activity_rate_limit_key (action, target_type, rate_limit_key, rate_limit_outcome, created_at);

ALTER TABLE post_images
  ADD COLUMN thumbnail_path VARCHAR(255) NULL AFTER image_path,
  ADD COLUMN mime_type VARCHAR(120) NULL AFTER thumbnail_path,
  ADD COLUMN file_size INT NOT NULL DEFAULT 0 AFTER mime_type,
  ADD COLUMN sort_order SMALLINT NOT NULL DEFAULT 1 AFTER file_size,
  ADD COLUMN created_by_user_id BIGINT NULL AFTER sort_order,
  ADD INDEX idx_post_images_post_sort (post_id, sort_order, id),
  ADD CONSTRAINT fk_post_images_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE message_attachments (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  message_id BIGINT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NULL,
  file_size INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_message_attachments_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
  INDEX idx_message_attachments_message (message_id, id)
);
