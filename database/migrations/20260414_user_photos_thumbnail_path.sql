ALTER TABLE user_photos
  ADD COLUMN IF NOT EXISTS thumbnail_path VARCHAR(255) NULL AFTER image_path;
