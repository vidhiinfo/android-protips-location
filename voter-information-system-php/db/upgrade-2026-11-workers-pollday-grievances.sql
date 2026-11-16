-- =====================================================================
-- Workers, poll-day turnout marks and grievances, for a database created before this version.
-- The application also adds these automatically on the first page load. Safe to run more than once.
-- =====================================================================
-- ---------------------------------------------------------------- party workers and their voters
CREATE TABLE IF NOT EXISTS workers (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    mobile      VARCHAR(15),
    assembly_no INT          NOT NULL,
    booth_no    INT,
    role        VARCHAR(30)  NOT NULL DEFAULT 'VOLUNTEER' COMMENT 'BOOTH_PRESIDENT / BOOTH_AGENT / PANNA_PRAMUKH / VOLUNTEER',
    username    VARCHAR(50)           COMMENT 'login of this worker: sees only the voters assigned to them',
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    notes       VARCHAR(300),
    created_at  DATETIME,
    INDEX idx_worker_booth (assembly_no, booth_no),
    INDEX idx_worker_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- poll day: voters marked as voted
CREATE TABLE IF NOT EXISTS poll_marks (
    id          BIGINT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    election_id BIGINT      NOT NULL,
    voter_id    BIGINT      NOT NULL,
    assembly_no INT         NOT NULL,
    booth_no    INT         NOT NULL,
    marked_by   VARCHAR(50),
    marked_at   DATETIME    NOT NULL,
    CONSTRAINT uk_poll_mark UNIQUE (election_id, voter_id),
    INDEX idx_poll_booth (election_id, assembly_no, booth_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- grievances / complaints from voters
CREATE TABLE IF NOT EXISTS grievances (
    id          BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    voter_id    BIGINT,
    assembly_no INT           NOT NULL,
    ward_no     INT,
    booth_no    INT,
    name        VARCHAR(120)  NOT NULL,
    mobile      VARCHAR(15),
    category    VARCHAR(30)   NOT NULL,
    subject     VARCHAR(200)  NOT NULL,
    description VARCHAR(2000),
    location    VARCHAR(300),
    priority    VARCHAR(10)   NOT NULL DEFAULT 'NORMAL',
    status      VARCHAR(20)   NOT NULL DEFAULT 'OPEN' COMMENT 'OPEN / IN_PROGRESS / RESOLVED / CLOSED',
    worker_id   BIGINT,
    resolution  VARCHAR(1000),
    created_by  VARCHAR(50),
    created_at  DATETIME,
    updated_at  DATETIME,
    resolved_at DATETIME,
    INDEX idx_grv_booth (assembly_no, booth_no),
    INDEX idx_grv_status (status),
    INDEX idx_grv_voter (voter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'voters' AND column_name = 'worker_id');
SET @sql := IF(@has = 0, 'ALTER TABLE voters ADD COLUMN worker_id BIGINT NULL, ADD INDEX idx_voter_worker (worker_id)', 'SELECT 1');
PREPARE add_w FROM @sql; EXECUTE add_w; DEALLOCATE PREPARE add_w;
