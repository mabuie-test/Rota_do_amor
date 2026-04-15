-- Phase 2 hardening: canonical pairs, safe date open-pair integrity, relational checks, login rate-limit lookup.

-- 1) Conversations: merge legacy inverted/duplicated pairs into canonical rows.
CREATE TEMPORARY TABLE tmp_conversation_keep AS
SELECT
  LEAST(user_one_id, user_two_id) AS c1,
  GREATEST(user_one_id, user_two_id) AS c2,
  MIN(id) AS keep_id
FROM conversations
GROUP BY LEAST(user_one_id, user_two_id), GREATEST(user_one_id, user_two_id);

UPDATE messages m
JOIN conversations c ON c.id = m.conversation_id
JOIN tmp_conversation_keep k
  ON k.c1 = LEAST(c.user_one_id, c.user_two_id)
 AND k.c2 = GREATEST(c.user_one_id, c.user_two_id)
SET m.conversation_id = k.keep_id
WHERE m.conversation_id <> k.keep_id;

UPDATE safe_dates sd
JOIN conversations c ON c.id = sd.conversation_id
JOIN tmp_conversation_keep k
  ON k.c1 = LEAST(c.user_one_id, c.user_two_id)
 AND k.c2 = GREATEST(c.user_one_id, c.user_two_id)
SET sd.conversation_id = k.keep_id
WHERE sd.conversation_id <> k.keep_id;

DELETE c
FROM conversations c
JOIN tmp_conversation_keep k
  ON k.c1 = LEAST(c.user_one_id, c.user_two_id)
 AND k.c2 = GREATEST(c.user_one_id, c.user_two_id)
WHERE c.id <> k.keep_id;

UPDATE conversations
SET user_one_id = LEAST(user_one_id, user_two_id),
    user_two_id = GREATEST(user_one_id, user_two_id)
WHERE user_one_id > user_two_id;

-- 2) Matches: merge legacy inverted/duplicated pairs into canonical rows.
CREATE TEMPORARY TABLE tmp_match_keep AS
SELECT
  LEAST(user_one_id, user_two_id) AS c1,
  GREATEST(user_one_id, user_two_id) AS c2,
  MIN(id) AS keep_id
FROM matches
GROUP BY LEAST(user_one_id, user_two_id), GREATEST(user_one_id, user_two_id);

UPDATE safe_dates sd
JOIN matches m ON m.id = sd.match_id
JOIN tmp_match_keep k
  ON k.c1 = LEAST(m.user_one_id, m.user_two_id)
 AND k.c2 = GREATEST(m.user_one_id, m.user_two_id)
SET sd.match_id = k.keep_id
WHERE sd.match_id <> k.keep_id;

DELETE m
FROM matches m
JOIN tmp_match_keep k
  ON k.c1 = LEAST(m.user_one_id, m.user_two_id)
 AND k.c2 = GREATEST(m.user_one_id, m.user_two_id)
WHERE m.id <> k.keep_id;

UPDATE matches
SET user_one_id = LEAST(user_one_id, user_two_id),
    user_two_id = GREATEST(user_one_id, user_two_id)
WHERE user_one_id > user_two_id;

-- 3) Safe dates: canonical generated pair + unique open guard to block concurrent duplicates.
ALTER TABLE safe_dates
  ADD COLUMN IF NOT EXISTS pair_user_low BIGINT GENERATED ALWAYS AS (LEAST(initiator_user_id, invitee_user_id)) STORED,
  ADD COLUMN IF NOT EXISTS pair_user_high BIGINT GENERATED ALWAYS AS (GREATEST(initiator_user_id, invitee_user_id)) STORED,
  ADD COLUMN IF NOT EXISTS open_pair_guard TINYINT GENERATED ALWAYS AS (
    CASE
      WHEN status IN ('proposed','accepted','reschedule_requested','rescheduled') THEN 1
      ELSE NULL
    END
  ) STORED;

SET @sql_add_open_pair_idx = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE safe_dates ADD UNIQUE KEY uq_safe_dates_open_pair (pair_user_low, pair_user_high, open_pair_guard)',
    'SELECT 1'
  )
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'safe_dates'
    AND INDEX_NAME = 'uq_safe_dates_open_pair'
);
PREPARE stmt_open_pair FROM @sql_add_open_pair_idx;
EXECUTE stmt_open_pair;
DEALLOCATE PREPARE stmt_open_pair;

-- 4) Activity logs: normalize login events to indexable target_type/rate_limit_key.
UPDATE activity_logs
SET target_type = 'login'
WHERE action IN ('login_failed', 'login_success')
  AND (target_type IS NULL OR target_type = 'email');

UPDATE activity_logs
SET rate_limit_key = CONCAT('login:', SHA2(CONCAT(
    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata_json, '$.email')), ''),
    '|',
    COALESCE(ip_address, '')
), 256))
WHERE action IN ('login_failed', 'login_success')
  AND (rate_limit_key IS NULL OR rate_limit_key = '')
  AND metadata_json IS NOT NULL;

-- 5) Add relational/domain constraints.
ALTER TABLE matches
  ADD CONSTRAINT chk_matches_canonical CHECK (user_one_id < user_two_id);

ALTER TABLE conversations
  ADD CONSTRAINT chk_conversations_canonical CHECK (user_one_id < user_two_id);

ALTER TABLE messages
  ADD CONSTRAINT chk_messages_sender_receiver CHECK (sender_id <> receiver_id);

ALTER TABLE safe_dates
  ADD CONSTRAINT chk_safe_dates_distinct_users CHECK (initiator_user_id <> invitee_user_id);

ALTER TABLE swipe_actions
  ADD CONSTRAINT chk_swipe_distinct_users CHECK (actor_user_id <> target_user_id);

ALTER TABLE connections
  ADD CONSTRAINT chk_connections_distinct_users CHECK (requester_id <> receiver_id);

ALTER TABLE favorites
  ADD CONSTRAINT chk_favorites_distinct_users CHECK (user_id <> favorite_user_id);

ALTER TABLE blocks
  ADD CONSTRAINT chk_blocks_distinct_users CHECK (actor_user_id <> target_user_id);

ALTER TABLE reports
  ADD CONSTRAINT chk_reports_not_self_target CHECK (target_user_id IS NULL OR reporter_user_id <> target_user_id),
  ADD CONSTRAINT chk_reports_target_coherence CHECK (
    (report_type = 'profile' AND target_user_id IS NOT NULL AND target_post_id IS NULL AND target_message_id IS NULL)
    OR (report_type = 'post' AND target_post_id IS NOT NULL AND target_user_id IS NULL AND target_message_id IS NULL)
    OR (report_type = 'message' AND target_message_id IS NOT NULL AND target_user_id IS NULL AND target_post_id IS NULL)
  );
