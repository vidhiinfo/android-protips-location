-- =====================================================================
-- Caste and Sub Caste masters + voters.sub_caste, for a database created before this version.
-- The application also adds these automatically on the first page load. Safe to run more than once.
-- =====================================================================
-- ---------------------------------------------------------------- castes (master)
CREATE TABLE IF NOT EXISTS castes (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    name_local  VARCHAR(100),
    category    VARCHAR(10)           COMMENT 'GEN / OBC / SC / ST (optional)',
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uk_caste_name UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- sub castes (master)
CREATE TABLE IF NOT EXISTS sub_castes (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    caste_id    BIGINT       NOT NULL,
    name        VARCHAR(100) NOT NULL,
    name_local  VARCHAR(100),
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uk_sub_caste UNIQUE (caste_id, name),
    CONSTRAINT fk_sub_caste_caste FOREIGN KEY (caste_id) REFERENCES castes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'voters' AND column_name = 'sub_caste');
SET @sql := IF(@has = 0, 'ALTER TABLE voters ADD COLUMN sub_caste VARCHAR(100) NULL AFTER caste, ADD INDEX idx_voter_sub_caste (sub_caste)', 'SELECT 1');
PREPARE add_sub FROM @sql; EXECUTE add_sub; DEALLOCATE PREPARE add_sub;
