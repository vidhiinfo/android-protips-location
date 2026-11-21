-- =====================================================================
-- Grievance deadlines / escalation / photos and poll-day transport, for a database created before this version.
-- The application also adds these automatically. Safe to run again.
-- =====================================================================
SET NAMES utf8mb4;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'grievances' AND column_name = 'due_at') = 0,
  'ALTER TABLE grievances ADD COLUMN due_at DATETIME NULL, ADD COLUMN escalation_level INT NOT NULL DEFAULT 0, ADD COLUMN escalated_at DATETIME NULL, ADD INDEX idx_grv_due (status, due_at)',
  'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------- grievance deadlines / timeline / photos, poll-day transport
CREATE TABLE IF NOT EXISTS grievance_sla (
    id            BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category      VARCHAR(30)  NOT NULL,
    days          INT          NOT NULL,
    escalate_to   VARCHAR(50)           COMMENT 'login told when overdue (level 1)',
    escalate_to2  VARCHAR(50)           COMMENT 'login told at twice the time (level 2)',
    CONSTRAINT uk_grv_sla UNIQUE (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO grievance_sla (category, days) VALUES ('WATER', 3), ('ELECTRICITY', 2), ('ROAD', 15), ('SEWER', 5), ('GARBAGE', 2), ('RATION', 7), ('PENSION', 30), ('HEALTH', 3), ('EDUCATION', 15), ('DOCUMENTS', 15), ('POLICE', 3), ('HOUSING', 30), ('EMPLOYMENT', 30), ('OTHER', 7);

CREATE TABLE IF NOT EXISTS grievance_updates (
    id            BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    grievance_id  BIGINT        NOT NULL,
    kind          VARCHAR(12)   NOT NULL COMMENT 'NOTE, STATUS, ESCALATED, PHOTO',
    text          VARCHAR(1000),
    by_user       VARCHAR(50),
    at            DATETIME      NOT NULL,
    INDEX idx_grv_upd (grievance_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grievance_photos (
    id            BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    grievance_id  BIGINT        NOT NULL,
    stage         VARCHAR(8)    NOT NULL DEFAULT 'BEFORE' COMMENT 'BEFORE (the problem) or AFTER (the fix)',
    path          VARCHAR(200)  NOT NULL COMMENT 'under storage/app/grievances',
    mime          VARCHAR(40)   NOT NULL,
    bytes         INT           NOT NULL,
    caption       VARCHAR(200),
    by_user       VARCHAR(50),
    at            DATETIME      NOT NULL,
    INDEX idx_grv_photo (grievance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transport_requests (
    id              BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    voter_id        BIGINT        NOT NULL,
    election_id     BIGINT,
    assembly_no     INT           NOT NULL,
    booth_no        INT           NOT NULL,
    reason          VARCHAR(12)   NOT NULL COMMENT 'AGE_85, DISABLED, ILL, OTHER',
    need            VARCHAR(12)   NOT NULL DEFAULT 'VEHICLE' COMMENT 'VEHICLE, WHEELCHAIR, ESCORT',
    pickup_address  VARCHAR(300),
    pickup_time     VARCHAR(5),
    mobile          VARCHAR(15),
    status          VARCHAR(10)   NOT NULL DEFAULT 'REQUESTED' COMMENT 'REQUESTED, ASSIGNED, PICKED, VOTED, CANCELLED',
    vehicle         VARCHAR(60),
    driver_name     VARCHAR(80),
    driver_mobile   VARCHAR(15),
    worker_id       BIGINT,
    notes           VARCHAR(500),
    created_by      VARCHAR(50),
    created_at      DATETIME,
    updated_at      DATETIME,
    CONSTRAINT uk_transport_voter UNIQUE (voter_id, election_id),
    INDEX idx_transport_booth (assembly_no, booth_no, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
