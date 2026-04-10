CREATE TABLE IF NOT EXISTS user_connection_modes (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  current_intention VARCHAR(60) NOT NULL,
  relational_pace VARCHAR(40) NOT NULL,
  openness_level VARCHAR(40) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_user_connection_modes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_user_connection_modes_user (user_id),
  INDEX idx_connection_modes_intention (current_intention),
  INDEX idx_connection_modes_pace (relational_pace)
);
