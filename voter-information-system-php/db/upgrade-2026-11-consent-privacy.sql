-- =====================================================================
-- Consent and privacy (DPDP Act 2023), for a database created before this version.
-- The application also adds these automatically. Safe to run again.
-- =====================================================================
SET NAMES utf8mb4;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'voters' AND column_name = 'consent_contact') = 0,
  'ALTER TABLE voters ADD COLUMN consent_contact CHAR(1) NULL, ADD COLUMN consent_at DATETIME NULL, ADD COLUMN consent_by VARCHAR(50) NULL, ADD COLUMN consent_source VARCHAR(20) NULL, ADD COLUMN erased_at DATETIME NULL',
  'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Consent history and privacy requests (DPDP Act 2023).
CREATE TABLE IF NOT EXISTS consent_log (
    id        BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    voter_id  BIGINT       NOT NULL,
    value     CHAR(1),
    source    VARCHAR(20),
    note      VARCHAR(300),
    by_user   VARCHAR(50),
    at        DATETIME     NOT NULL,
    INDEX idx_consent_voter (voter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS privacy_requests (
    id           BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    voter_id     BIGINT,
    assembly_no  INT,
    name         VARCHAR(150)  NOT NULL,
    epic_no      VARCHAR(30),
    type         VARCHAR(15)   NOT NULL COMMENT 'ACCESS, CORRECTION, ERASURE, WITHDRAW, OTHER',
    channel      VARCHAR(30),
    details      VARCHAR(1000),
    status       VARCHAR(10)   NOT NULL DEFAULT 'OPEN' COMMENT 'OPEN, DONE, REJECTED',
    outcome      VARCHAR(1000),
    created_by   VARCHAR(50),
    requested_at DATETIME      NOT NULL,
    due_at       DATETIME,
    done_by      VARCHAR(50),
    done_at      DATETIME,
    INDEX idx_privacy_status (status, due_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO role_permissions (role_id, permission)
SELECT r.id, 'PRIVACY_ADMIN' FROM roles r
WHERE r.code = 'ADMIN' AND NOT EXISTS (SELECT 1 FROM role_permissions p WHERE p.role_id = r.id AND p.permission = 'PRIVACY_ADMIN');
