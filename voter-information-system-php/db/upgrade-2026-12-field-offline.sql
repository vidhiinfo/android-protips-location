-- Offline field app (downloads and last sync per booth). The application also adds this automatically. Safe to run again.
SET NAMES utf8mb4;
-- ---------------------------------------------------------------- offline field app: downloads and last sync per booth
CREATE TABLE IF NOT EXISTS field_syncs (
    id             BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(50)  NOT NULL,
    assembly_no    INT          NOT NULL,
    booth_no       INT          NOT NULL,
    downloaded_at  DATETIME,
    synced_at      DATETIME,
    last_items     INT          NOT NULL DEFAULT 0 COMMENT 'items received in the last sync',
    CONSTRAINT uk_field_sync UNIQUE (username, assembly_no, booth_no),
    INDEX idx_field_sync_booth (assembly_no, booth_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
