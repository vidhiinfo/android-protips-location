-- =====================================================================
-- Organisation chain: mandals (named groups of booths) and the mandal of a mandal in-charge.
-- The application also adds these automatically on the first page load after the update; run this only
-- if you prefer to do it yourself (phpMyAdmin > your database > Import). Safe to run more than once.
-- =====================================================================
CREATE TABLE IF NOT EXISTS mandals (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    assembly_no INT          NOT NULL,
    name        VARCHAR(100) NOT NULL,
    booths      VARCHAR(1000),
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME,
    INDEX idx_mandal_assembly (assembly_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET @have := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'workers' AND column_name = 'mandal_id');
SET @sql := IF(@have = 0, 'ALTER TABLE workers ADD COLUMN mandal_id BIGINT NULL, ADD INDEX idx_worker_mandal (mandal_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
