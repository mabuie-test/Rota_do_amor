ALTER TABLE messages
  ADD COLUMN IF NOT EXISTS sent_at DATETIME NULL AFTER is_read,
  ADD COLUMN IF NOT EXISTS delivered_at DATETIME NULL AFTER sent_at,
  ADD COLUMN IF NOT EXISTS read_at DATETIME NULL AFTER delivered_at;

UPDATE messages
SET sent_at = COALESCE(sent_at, created_at, NOW()),
    delivered_at = CASE
      WHEN delivered_at IS NOT NULL THEN delivered_at
      WHEN is_read = 1 THEN COALESCE(created_at, NOW())
      ELSE NULL
    END,
    read_at = CASE
      WHEN read_at IS NOT NULL THEN read_at
      WHEN is_read = 1 THEN COALESCE(created_at, NOW())
      ELSE NULL
    END
WHERE sent_at IS NULL OR (is_read = 1 AND (delivered_at IS NULL OR read_at IS NULL));

ALTER TABLE messages
  MODIFY COLUMN sent_at DATETIME NOT NULL,
  ADD INDEX IF NOT EXISTS idx_messages_delivery (conversation_id, receiver_id, delivered_at, read_at);

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

ALTER TABLE notifications
  ADD COLUMN IF NOT EXISTS sent_at DATETIME NULL AFTER is_read,
  ADD COLUMN IF NOT EXISTS delivered_at DATETIME NULL AFTER sent_at,
  ADD COLUMN IF NOT EXISTS read_at DATETIME NULL AFTER delivered_at;

UPDATE notifications
SET sent_at = COALESCE(sent_at, created_at, NOW()),
    delivered_at = CASE WHEN is_read = 1 THEN COALESCE(delivered_at, created_at, NOW()) ELSE delivered_at END,
    read_at = CASE WHEN is_read = 1 THEN COALESCE(read_at, created_at, NOW()) ELSE read_at END
WHERE sent_at IS NULL;

ALTER TABLE notifications
  MODIFY COLUMN sent_at DATETIME NOT NULL,
  ADD INDEX IF NOT EXISTS idx_notifications_user_delivery (user_id, delivered_at, read_at, sent_at);
