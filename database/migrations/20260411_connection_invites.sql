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

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE connection_invites ADD COLUMN invitation_type ENUM(''standard'',''priority'') NOT NULL DEFAULT ''standard'' AFTER status',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'connection_invites'
    AND COLUMN_NAME = 'invitation_type'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE connection_invites ADD COLUMN opening_message VARCHAR(500) NULL AFTER invitation_type',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'connection_invites'
    AND COLUMN_NAME = 'opening_message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE connection_invites ADD COLUMN compatibility_breakdown_snapshot JSON NULL AFTER compatibility_score_snapshot',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'connection_invites'
    AND COLUMN_NAME = 'compatibility_breakdown_snapshot'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE connection_invites ADD COLUMN expires_at DATETIME NULL AFTER responded_at',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'connection_invites'
    AND COLUMN_NAME = 'expires_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE connection_invites ci
JOIN (
  SELECT sender_user_id, receiver_user_id, MAX(id) AS keep_id
  FROM connection_invites
  WHERE status = 'pending'
  GROUP BY sender_user_id, receiver_user_id
  HAVING COUNT(*) > 1
) dup ON dup.sender_user_id = ci.sender_user_id
    AND dup.receiver_user_id = ci.receiver_user_id
SET ci.status = 'expired',
    ci.responded_at = COALESCE(ci.responded_at, NOW()),
    ci.updated_at = NOW()
WHERE ci.status = 'pending'
  AND ci.id <> dup.keep_id;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE connection_invites ADD COLUMN pending_guard TINYINT GENERATED ALWAYS AS (CASE WHEN status = ''pending'' THEN 1 ELSE NULL END) STORED AFTER compatibility_breakdown_snapshot',
    'SELECT 1'
  )
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'connection_invites'
    AND COLUMN_NAME = 'pending_guard'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE connection_invites ADD INDEX idx_connection_invites_receiver_status_created (receiver_user_id, status, created_at)',
    'SELECT 1'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'connection_invites'
    AND INDEX_NAME = 'idx_connection_invites_receiver_status_created'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE connection_invites ADD INDEX idx_connection_invites_sender_status_created (sender_user_id, status, created_at)',
    'SELECT 1'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'connection_invites'
    AND INDEX_NAME = 'idx_connection_invites_sender_status_created'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE connection_invites ADD INDEX idx_connection_invites_type_status (invitation_type, status)',
    'SELECT 1'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'connection_invites'
    AND INDEX_NAME = 'idx_connection_invites_type_status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE connection_invites ADD UNIQUE KEY uq_connection_invites_pending_once (sender_user_id, receiver_user_id, pending_guard)',
    'SELECT 1'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'connection_invites'
    AND INDEX_NAME = 'uq_connection_invites_pending_once'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
