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

ALTER TABLE connection_invites
  ADD COLUMN IF NOT EXISTS pending_guard TINYINT GENERATED ALWAYS AS (
    CASE WHEN status = 'pending' THEN 1 ELSE NULL END
  ) STORED;

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
