ALTER TABLE diary_entries
    ADD COLUMN archived_at DATETIME NULL AFTER relational_pace_snapshot,
    ADD COLUMN deleted_at DATETIME NULL AFTER archived_at;

CREATE INDEX idx_diary_user_deleted_created ON diary_entries (user_id, deleted_at, created_at);
CREATE INDEX idx_diary_deleted_created ON diary_entries (deleted_at, created_at);

CREATE INDEX idx_activity_target ON activity_logs (target_type, target_id, created_at);
CREATE INDEX idx_activity_actor_type_id_created ON activity_logs (actor_type, actor_id, created_at);
