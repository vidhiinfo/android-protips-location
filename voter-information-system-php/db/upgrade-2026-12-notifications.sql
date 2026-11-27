-- =====================================================================
-- In-app notifications (bell in the top bar): announcements, tasks, grievances.
-- The application also adds this automatically on the first page load after the update. Safe to run more than once.
-- =====================================================================
CREATE TABLE IF NOT EXISTS notifications (
    id          BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)   NOT NULL,
    kind        VARCHAR(20)   NOT NULL COMMENT 'ANNOUNCE, TASK, GRIEVANCE, ESCALATION',
    title       VARCHAR(200)  NOT NULL,
    body        VARCHAR(1000),
    url         VARCHAR(300),
    created_by  VARCHAR(50),
    created_at  DATETIME,
    read_at     DATETIME,
    INDEX idx_notif_user (username, read_at),
    INDEX idx_notif_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
