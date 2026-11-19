-- =====================================================================
-- Access area of a user (ward / booth / serial range), for a database created before this version.
-- The application also adds these columns automatically on the first page load. Safe to run more than once.
-- =====================================================================
SET @has := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'app_users' AND column_name = 'scope_booths');
SET @sql := IF(@has = 0, 'ALTER TABLE app_users ADD COLUMN scope_wards VARCHAR(200) NULL, ADD COLUMN scope_booths VARCHAR(500) NULL, ADD COLUMN scope_serial_from INT NULL, ADD COLUMN scope_serial_to INT NULL', 'SELECT 1');
PREPARE add_area FROM @sql; EXECUTE add_area; DEALLOCATE PREPARE add_area;
