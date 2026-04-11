-- Compatibilidade incremental para bases antigas (MySQL 5.7+/8+):
-- Alinha mensagens/notificações com sent_at/delivered_at/read_at
-- e garante suporte ao typing state em chat.

SET @db_name := DATABASE();

-- messages.sent_at
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE messages ADD COLUMN sent_at DATETIME NULL AFTER is_read',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'messages'
    AND COLUMN_NAME = 'sent_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- messages.delivered_at
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE messages ADD COLUMN delivered_at DATETIME NULL AFTER sent_at',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'messages'
    AND COLUMN_NAME = 'delivered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- messages.read_at
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE messages ADD COLUMN read_at DATETIME NULL AFTER delivered_at',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'messages'
    AND COLUMN_NAME = 'read_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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
WHERE sent_at IS NULL
   OR (is_read = 1 AND (delivered_at IS NULL OR read_at IS NULL));

ALTER TABLE messages
  MODIFY COLUMN sent_at DATETIME NOT NULL;

-- Índice idx_messages_conversation_id
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE messages ADD INDEX idx_messages_conversation_id (conversation_id, id)',
    'SELECT 1'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'messages'
    AND INDEX_NAME = 'idx_messages_conversation_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice idx_messages_conversation_receiver_read
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE messages ADD INDEX idx_messages_conversation_receiver_read (conversation_id, receiver_id, is_read, id)',
    'SELECT 1'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'messages'
    AND INDEX_NAME = 'idx_messages_conversation_receiver_read'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice idx_messages_delivery
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE messages ADD INDEX idx_messages_delivery (conversation_id, receiver_id, delivered_at, read_at)',
    'SELECT 1'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'messages'
    AND INDEX_NAME = 'idx_messages_delivery'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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

-- notifications.sent_at
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE notifications ADD COLUMN sent_at DATETIME NULL AFTER is_read',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'notifications'
    AND COLUMN_NAME = 'sent_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- notifications.delivered_at
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE notifications ADD COLUMN delivered_at DATETIME NULL AFTER sent_at',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'notifications'
    AND COLUMN_NAME = 'delivered_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- notifications.read_at
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE notifications ADD COLUMN read_at DATETIME NULL AFTER delivered_at',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'notifications'
    AND COLUMN_NAME = 'read_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE notifications
SET sent_at = COALESCE(sent_at, created_at, NOW()),
    delivered_at = CASE
      WHEN is_read = 1 THEN COALESCE(delivered_at, created_at, NOW())
      ELSE delivered_at
    END,
    read_at = CASE
      WHEN is_read = 1 THEN COALESCE(read_at, created_at, NOW())
      ELSE read_at
    END
WHERE sent_at IS NULL;

ALTER TABLE notifications
  MODIFY COLUMN sent_at DATETIME NOT NULL;

SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE notifications ADD INDEX idx_notifications_user_delivery (user_id, delivered_at, read_at, sent_at)',
    'SELECT 1'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'notifications'
    AND INDEX_NAME = 'idx_notifications_user_delivery'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
