ALTER TABLE safe_dates
  ADD COLUMN reschedule_requested_by_user_id BIGINT NULL AFTER completed_at,
  ADD COLUMN reschedule_requested_at DATETIME NULL AFTER reschedule_requested_by_user_id,
  ADD COLUMN reschedule_proposed_datetime DATETIME NULL AFTER reschedule_requested_at,
  ADD COLUMN reminder_24h_sent_at DATETIME NULL AFTER last_transition_at,
  ADD COLUMN reminder_2h_sent_at DATETIME NULL AFTER reminder_24h_sent_at,
  ADD COLUMN reminder_same_day_sent_at DATETIME NULL AFTER reminder_2h_sent_at,
  ADD COLUMN arrived_confirmed_at DATETIME NULL AFTER reminder_same_day_sent_at,
  ADD COLUMN arrived_confirmed_by_user_id BIGINT NULL AFTER arrived_confirmed_at,
  ADD COLUMN ended_well_confirmed_at DATETIME NULL AFTER arrived_confirmed_by_user_id,
  ADD COLUMN ended_well_confirmed_by_user_id BIGINT NULL AFTER ended_well_confirmed_at,
  ADD COLUMN safety_signal_level ENUM('none','attention','emergency') NOT NULL DEFAULT 'none' AFTER ended_well_confirmed_by_user_id,
  ADD COLUMN safety_signal_note VARCHAR(500) NULL AFTER safety_signal_level,
  ADD CONSTRAINT fk_safe_dates_reschedule_requested_by FOREIGN KEY (reschedule_requested_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_safe_dates_arrived_by FOREIGN KEY (arrived_confirmed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_safe_dates_ended_well_by FOREIGN KEY (ended_well_confirmed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  ADD INDEX idx_safe_dates_reschedule_pending (status, reschedule_requested_by_user_id, reschedule_requested_at),
  ADD INDEX idx_safe_dates_reminder_windows (status, proposed_datetime, reminder_24h_sent_at, reminder_2h_sent_at);

CREATE TABLE safe_date_private_feedback (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  safe_date_id BIGINT NOT NULL,
  user_id BIGINT NOT NULL,
  rating TINYINT NULL,
  feedback_note VARCHAR(500) NULL,
  safety_signal ENUM('none','attention','emergency') NOT NULL DEFAULT 'none',
  safety_note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_safe_date_private_feedback_date FOREIGN KEY (safe_date_id) REFERENCES safe_dates(id) ON DELETE CASCADE,
  CONSTRAINT fk_safe_date_private_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_safe_date_private_feedback_pair (safe_date_id, user_id),
  INDEX idx_safe_date_private_feedback_signal (safety_signal, created_at)
);
