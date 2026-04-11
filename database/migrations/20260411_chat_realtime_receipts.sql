ALTER TABLE messages
  ADD COLUMN sent_at DATETIME NULL AFTER is_read,
  ADD COLUMN delivered_at DATETIME NULL AFTER sent_at,
  ADD COLUMN read_at DATETIME NULL AFTER delivered_at;

UPDATE messages
SET sent_at = COALESCE(created_at, NOW()),
    delivered_at = CASE WHEN is_read = 1 THEN COALESCE(created_at, NOW()) ELSE NULL END,
    read_at = CASE WHEN is_read = 1 THEN COALESCE(created_at, NOW()) ELSE NULL END
WHERE sent_at IS NULL;

ALTER TABLE messages
  MODIFY COLUMN sent_at DATETIME NOT NULL,
  ADD INDEX idx_messages_delivery (conversation_id, receiver_id, delivered_at, read_at);

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
