-- =====================================================================
-- Two-step login (OTP) columns on app_users, for a database created before this version.
-- The application also adds them automatically. Safe to run again.
-- =====================================================================
SET @t := 'app_users';
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @t AND column_name = 'mobile') = 0,
  'ALTER TABLE app_users ADD COLUMN mobile VARCHAR(15) NULL', 'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @t AND column_name = 'otp_method') = 0,
  'ALTER TABLE app_users ADD COLUMN otp_method VARCHAR(10) NULL, ADD COLUMN otp_secret TEXT NULL, ADD COLUMN otp_recovery TEXT NULL, ADD COLUMN otp_since DATETIME NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
