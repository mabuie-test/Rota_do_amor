ALTER TABLE payments
  ADD COLUMN benefit_application_status ENUM('pending','applying','applied','failed','skipped') NOT NULL DEFAULT 'pending' AFTER status,
  ADD COLUMN benefit_applied_at DATETIME NULL AFTER benefit_application_status,
  ADD INDEX idx_payments_type_status (payment_type, status),
  ADD INDEX idx_payments_benefit_status (benefit_application_status);

ALTER TABLE identity_verifications
  ADD INDEX idx_identity_user_status (user_id, status);

ALTER TABLE compatibility_scores
  ADD INDEX idx_comp_user_calculated (user_id, calculated_at);

ALTER TABLE messages
  ADD INDEX idx_messages_conversation_id (conversation_id, id),
  ADD INDEX idx_messages_conversation_receiver_read (conversation_id, receiver_id, is_read, id);

ALTER TABLE posts
  ADD INDEX idx_posts_user_status_created (user_id, status, created_at);

ALTER TABLE post_comments
  ADD INDEX idx_post_comments_post_created (post_id, created_at);

ALTER TABLE activity_logs
  ADD INDEX idx_activity_rate_limit_lookup (action, target_type, created_at),
  ADD INDEX idx_activity_actor_created (actor_id, created_at);
