ALTER TABLE post_comments
  ADD COLUMN parent_comment_id BIGINT NULL AFTER user_id,
  ADD CONSTRAINT fk_post_comments_parent FOREIGN KEY (parent_comment_id) REFERENCES post_comments(id) ON DELETE CASCADE,
  ADD INDEX idx_post_comments_parent_created (parent_comment_id, created_at);
