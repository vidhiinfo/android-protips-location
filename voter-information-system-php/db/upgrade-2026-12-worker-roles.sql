-- =====================================================================
-- Worker roles (Masters > Worker roles): rename, hide and reorder roles, add own roles.
-- The application also adds this automatically on the first page load after the update. Safe to run more than once.
-- =====================================================================
CREATE TABLE IF NOT EXISTS worker_roles (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(30)  NOT NULL,
    base        VARCHAR(30)  NOT NULL COMMENT 'built-in role this one works like',
    name        VARCHAR(60),
    name_hi     VARCHAR(60),
    name_pa     VARCHAR(60),
    sort        INT          NOT NULL DEFAULT 0,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    builtin     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME,
    UNIQUE KEY uk_worker_role_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO worker_roles (code, base, sort, active, builtin, created_at) VALUES
    ('LS_INCHARGE', 'LS_INCHARGE', 10, 1, 1, NOW()), ('AC_INCHARGE', 'AC_INCHARGE', 20, 1, 1, NOW()),
    ('MANDAL_INCHARGE', 'MANDAL_INCHARGE', 30, 1, 1, NOW()), ('BOOTH_PRESIDENT', 'BOOTH_PRESIDENT', 40, 1, 1, NOW()),
    ('BOOTH_AGENT', 'BOOTH_AGENT', 50, 1, 1, NOW()), ('PANNA_PRAMUKH', 'PANNA_PRAMUKH', 60, 1, 1, NOW()),
    ('VOLUNTEER', 'VOLUNTEER', 70, 1, 1, NOW());
