-- =====================================================================
-- Outreach (automatic greetings, delivery receipts, inbox, phone bank), for a database created before this version.
-- The application also adds these automatically. Safe to run again.
-- =====================================================================
SET NAMES utf8mb4;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'message_outbox' AND column_name = 'delivery') = 0,
  'ALTER TABLE message_outbox ADD COLUMN delivery VARCHAR(12) NULL, ADD COLUMN delivered_at DATETIME NULL, ADD COLUMN read_at DATETIME NULL',
  'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql := IF((SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'message_outbox' AND index_name = 'idx_outbox_provider') = 0,
  'ALTER TABLE message_outbox ADD INDEX idx_outbox_provider (provider_id)',
  'SELECT 1'); PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---------------------------------------------------------------- outreach: automatic greetings, inbox, phone bank
CREATE TABLE IF NOT EXISTS auto_greetings (
    id               BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    kind             VARCHAR(12)  NOT NULL COMMENT 'BIRTHDAY or ANNIVERSARY',
    template_id      BIGINT       NOT NULL,
    assembly_no      INT                   COMMENT 'NULL = every assembly',
    send_at          VARCHAR(5)   NOT NULL DEFAULT '09:00',
    active           TINYINT(1)   NOT NULL DEFAULT 1,
    last_run_on      DATE,
    last_campaign_id BIGINT,
    created_by       VARCHAR(50),
    created_at       DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversations (
    id           BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    channel      VARCHAR(10)  NOT NULL,
    mobile       VARCHAR(16)  NOT NULL COMMENT '+91XXXXXXXXXX',
    voter_id     BIGINT,
    name         VARCHAR(120),
    assembly_no  INT,
    unread       INT          NOT NULL DEFAULT 0,
    status       VARCHAR(10)  NOT NULL DEFAULT 'OPEN',
    assigned_to  VARCHAR(50),
    last_in_at   DATETIME              COMMENT 'last message from the voter (WhatsApp: free replies within 24 hours)',
    last_at      DATETIME,
    CONSTRAINT uk_conversation UNIQUE (channel, mobile),
    INDEX idx_conv_status (status, last_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversation_messages (
    id               BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    conversation_id  BIGINT        NOT NULL,
    direction        CHAR(3)       NOT NULL COMMENT 'IN or OUT',
    body             VARCHAR(2000),
    media_type       VARCHAR(20),
    provider_id      VARCHAR(100),
    status           VARCHAR(12)            COMMENT 'OUT: SENT, FAILED, DELIVERED, READ',
    error            VARCHAR(300),
    by_user          VARCHAR(50),
    at               DATETIME      NOT NULL,
    INDEX idx_convmsg (conversation_id, id),
    INDEX idx_convmsg_provider (provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS call_logs (
    id           BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    voter_id     BIGINT       NOT NULL,
    assembly_no  INT          NOT NULL,
    booth_no     INT,
    outcome      VARCHAR(16)  NOT NULL COMMENT 'ANSWERED, NO_ANSWER, BUSY, SWITCHED_OFF, WRONG_NUMBER, CALL_BACK, REFUSED',
    leaning      CHAR(1)               COMMENT 'S support, N neutral, O opposition (what the voter said)',
    notes        VARCHAR(500),
    callback_at  DATETIME,
    by_user      VARCHAR(50),
    at           DATETIME     NOT NULL,
    INDEX idx_call_voter (voter_id, at),
    INDEX idx_call_day (assembly_no, at),
    INDEX idx_call_user (by_user, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
