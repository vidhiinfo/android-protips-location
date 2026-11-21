-- =====================================================================
-- Field tools (house GPS, worker tasks, attendance, merged duplicates), for a database created before this version.
-- The application also adds these automatically. Safe to run again.
-- =====================================================================
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS house_locations (
    id           BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    assembly_no  INT           NOT NULL,
    booth_no     INT           NOT NULL,
    house_ref    VARCHAR(191)  NOT NULL COMMENT 'house no. key | address | village (same grouping as the field survey)',
    voter_id     BIGINT                 COMMENT 'first voter of the house when the location was taken',
    lat          DECIMAL(10,7) NOT NULL,
    lng          DECIMAL(10,7) NOT NULL,
    accuracy_m   INT,
    by_user      VARCHAR(50),
    at           DATETIME      NOT NULL,
    CONSTRAINT uk_house_loc UNIQUE (assembly_no, booth_no, house_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS worker_tasks (
    id           BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200)  NOT NULL,
    kind         VARCHAR(20)   NOT NULL DEFAULT 'OTHER' COMMENT 'SURVEY (target = voters to survey), VISIT, CALL, MEETING, OTHER',
    worker_id    BIGINT,
    username     VARCHAR(50)            COMMENT 'login the task is for',
    assembly_no  INT           NOT NULL,
    booth_no     INT,
    target       INT,
    done_count   INT                    COMMENT 'progress reported by the worker (non-survey tasks)',
    due_date     DATE,
    status       VARCHAR(10)   NOT NULL DEFAULT 'OPEN',
    notes        VARCHAR(1000),
    created_by   VARCHAR(50),
    created_at   DATETIME,
    done_at      DATETIME,
    reminded_on  DATE,
    INDEX idx_task_user (username, status),
    INDEX idx_task_due (assembly_no, status, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance (
    id           BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50)   NOT NULL,
    worker_id    BIGINT,
    assembly_no  INT,
    day          DATE          NOT NULL,
    check_in     DATETIME      NOT NULL,
    in_lat       DECIMAL(10,7),
    in_lng       DECIMAL(10,7),
    check_out    DATETIME,
    out_lat      DECIMAL(10,7),
    out_lng      DECIMAL(10,7),
    note         VARCHAR(300),
    CONSTRAINT uk_attendance UNIQUE (username, day),
    INDEX idx_att_day (assembly_no, day)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS voter_merges (
    id           BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    keep_id      BIGINT        NOT NULL,
    drop_id      BIGINT        NOT NULL,
    assembly_no  INT           NOT NULL,
    `before`     TEXT          NOT NULL COMMENT 'JSON: fields of the kept voter before the merge, the removed voter''s deleted flag, moved rows',
    by_user      VARCHAR(50),
    at           DATETIME      NOT NULL,
    undone_at    DATETIME,
    INDEX idx_merge_keep (keep_id),
    INDEX idx_merge_drop (drop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
