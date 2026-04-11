ALTER TABLE connection_invites
  ADD COLUMN pending_guard TINYINT GENERATED ALWAYS AS (
    CASE WHEN status = 'pending' THEN 1 ELSE NULL END
  ) STORED,
  ADD UNIQUE KEY uq_connection_invites_pending_once (sender_user_id, receiver_user_id, pending_guard);
