-- =====================================================================
-- Adds voters.ward_no (municipal ward no.) to a database created before this version.
-- The application also adds it automatically on the first page load; run this only if you prefer
-- to do it yourself (phpMyAdmin > your database > Import). Safe to run more than once.
-- =====================================================================
SET @has_ward := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'voters' AND column_name = 'ward_no');
SET @sql := IF(@has_ward = 0, 'ALTER TABLE voters ADD COLUMN ward_no INT NULL AFTER village_ward, ADD INDEX idx_voter_ward_no (ward_no)', 'SELECT 1');
PREPARE add_ward FROM @sql; EXECUTE add_ward; DEALLOCATE PREPARE add_ward;
