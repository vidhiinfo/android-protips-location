-- =====================================================================
-- Voter roll update (compare a new roll, apply it without losing survey data, undo),
-- for a database created before this version. The application also adds it automatically. Safe to run again.
-- =====================================================================
SET NAMES utf8mb4;

-- Roll update: a new voter roll compared with / applied to an assembly (values before the update kept for undo).
CREATE TABLE IF NOT EXISTS roll_updates (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    assembly_no INT          NOT NULL,
    file_name   VARCHAR(255) NOT NULL,
    booths      TEXT                  COMMENT 'booth numbers in the uploaded roll, comma separated',
    status      VARCHAR(12)  NOT NULL DEFAULT 'PREVIEW' COMMENT 'PREVIEW, APPLIED, UNDONE, DISCARDED',
    counts      TEXT                  COMMENT 'JSON: rows per action',
    errors      TEXT                  COMMENT 'JSON: rows that could not be read',
    created_by  VARCHAR(50),
    created_at  DATETIME,
    applied_by  VARCHAR(50),
    applied_at  DATETIME,
    undone_at   DATETIME,
    INDEX idx_rollupd_ac (assembly_no, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roll_update_rows (
    id             BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    roll_update_id BIGINT       NOT NULL,
    row_no         INT,
    action         VARCHAR(12)  NOT NULL COMMENT 'SAME, CHANGED, SHIFTED, NEW, RESTORED, NAMEMATCH, DELETED',
    voter_id       BIGINT,
    epic_no        VARCHAR(30),
    name           VARCHAR(150),
    booth_no       INT                   COMMENT 'booth in the new roll (old booth for DELETED)',
    serial_no      INT,
    old_booth_no   INT,
    old_serial_no  INT,
    has_survey     TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'voter already has data entered by the party',
    data           TEXT                  COMMENT 'JSON: roll values from the new roll',
    changes        TEXT                  COMMENT 'JSON: field => [old, new]',
    `before`       TEXT                  COMMENT 'JSON: values before applying, for undo',
    INDEX idx_rollrow_action (roll_update_id, action),
    INDEX idx_rollrow_booth (roll_update_id, booth_no),
    INDEX idx_rollrow_voter (roll_update_id, voter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- the Administrator role gets the new permission
INSERT INTO role_permissions (role_id, permission)
SELECT r.id, 'ROLL_UPDATE' FROM roles r
WHERE r.code = 'ADMIN' AND NOT EXISTS (SELECT 1 FROM role_permissions p WHERE p.role_id = r.id AND p.permission = 'ROLL_UPDATE');
