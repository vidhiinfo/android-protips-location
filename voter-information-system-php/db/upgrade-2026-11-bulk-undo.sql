-- Undo for bulk updates, for a database created before this version. The application also adds it automatically. Safe to run again.
SET NAMES utf8mb4;

-- Bulk updates with the values they replaced (Voters > Bulk changes: undo).
CREATE TABLE IF NOT EXISTS bulk_changes (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    created_by  VARCHAR(50),
    created_at  DATETIME,
    assembly_no INT,
    fields      TEXT         NOT NULL COMMENT 'JSON: field => new value',
    voter_count INT          NOT NULL DEFAULT 0,
    status      VARCHAR(10)  NOT NULL DEFAULT 'APPLIED' COMMENT 'APPLIED, UNDONE',
    undone_by   VARCHAR(50),
    undone_at   DATETIME,
    skipped     INT          NOT NULL DEFAULT 0 COMMENT 'values changed again later, kept at undo',
    INDEX idx_bulk_ac (assembly_no, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bulk_change_rows (
    id             BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bulk_change_id BIGINT       NOT NULL,
    voter_id       BIGINT       NOT NULL,
    `before`       TEXT         NOT NULL COMMENT 'JSON: field => value before the bulk update',
    `after`        TEXT         NOT NULL COMMENT 'JSON: field => value written',
    INDEX idx_bulkrow_change (bulk_change_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
