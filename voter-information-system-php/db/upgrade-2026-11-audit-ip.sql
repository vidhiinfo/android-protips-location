-- =====================================================================
-- Audit log: IP address and browser / device of every action, for a database created before this version.
-- The application also adds these automatically on the first page load. Safe to run more than once.
-- =====================================================================
SET @has := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'audit_log' AND column_name = 'ip_address');
SET @sql := IF(@has = 0, 'ALTER TABLE audit_log ADD COLUMN ip_address VARCHAR(45) NULL AFTER details, ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip_address, ADD INDEX idx_audit_ip (ip_address)', 'SELECT 1');
PREPARE add_ip FROM @sql; EXECUTE add_ip; DEALLOCATE PREPARE add_ip;
