-- =====================================================================
-- State summary across division installations, for a database created before this version.
-- The application also adds it automatically. Safe to run again.
-- =====================================================================
SET NAMES utf8mb4;

-- State summary: the latest totals fetched from each division installation (STATE_SOURCES; state site only).
CREATE TABLE IF NOT EXISTS state_snapshots (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    source      VARCHAR(100) NOT NULL,
    url         VARCHAR(300),
    ok          TINYINT(1)   NOT NULL DEFAULT 0,
    error       VARCHAR(300),
    payload     LONGTEXT              COMMENT 'JSON: totals per assembly from the division',
    fetched_at  DATETIME,
    last_ok_at  DATETIME,
    CONSTRAINT uk_state_source UNIQUE (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO role_permissions (role_id, permission)
SELECT r.id, 'STATE_VIEW' FROM roles r
WHERE r.code = 'ADMIN' AND NOT EXISTS (SELECT 1 FROM role_permissions p WHERE p.role_id = r.id AND p.permission = 'STATE_VIEW');
