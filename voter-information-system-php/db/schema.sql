-- =====================================================================
-- Voter Information System (PHP / Laravel) - MySQL 8 / MariaDB 10.5+ schema
--
-- SHARED HOSTING (cPanel, GoDaddy, HostGator ...):
--   1. cPanel > MySQL Databases: create the database and a user, add the user
--      to the database with ALL PRIVILEGES.
--   2. phpMyAdmin > select that database > Import > choose this file > Go.
--   (No CREATE DATABASE / CREATE USER here - shared hosts do not allow them.)
--
-- Same tables as the Java version: both versions can use one database.
-- Every table is utf8mb4 so Hindi, Urdu, Bengali, Tamil ... can be stored.
-- =====================================================================

SET NAMES utf8mb4;
-- ---------------------------------------------------------------- voters
CREATE TABLE IF NOT EXISTS voters (
    id                  BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    epic_no             VARCHAR(20)  NOT NULL,
    name                VARCHAR(120) NOT NULL COMMENT 'name as on the roll (English)',
    name_local          VARCHAR(120)          COMMENT 'name in the regional language',
    relative_name       VARCHAR(120),
    relative_name_local VARCHAR(120),
    relation_type       VARCHAR(10),
    gender              VARCHAR(10)  NOT NULL,
    age                 INT,
    assembly_no         INT          NOT NULL,
    assembly_name       VARCHAR(100),
    booth_no            INT          NOT NULL,
    booth_name          VARCHAR(200),
    serial_no           INT,
    house_no            VARCHAR(30),
    address             VARCHAR(300),
    address_local       VARCHAR(300),
    village_ward        VARCHAR(100),
    ward_no             INT                   COMMENT 'municipal ward no.',
    district            VARCHAR(100),
    pincode             VARCHAR(6),
    mobile              VARCHAR(10),
    alt_mobile          VARCHAR(10),
    email               VARCHAR(120),
    category            VARCHAR(50),
    remarks             VARCHAR(500),
    membership_no       VARCHAR(50)           COMMENT 'party membership no.',
    dob                 DATE,
    anniversary         DATE                  COMMENT 'marriage anniversary',
    deleted             TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = deleted from the electoral roll',
    shift1              VARCHAR(100),
    shift2              VARCHAR(100),
    place_of_origin     VARCHAR(150),
    current_place       VARCHAR(150),
    linked_with         VARCHAR(150),
    social_status       VARCHAR(100),
    political_status    VARCHAR(100),
    organisational_status VARCHAR(100),
    beneficiary1        VARCHAR(150)          COMMENT 'government scheme beneficiary',
    beneficiary2        VARCHAR(150),
    beneficiary3        VARCHAR(150),
    caste               VARCHAR(100),
    sub_caste           VARCHAR(100),
    created_at          DATETIME(6)  NOT NULL,
    updated_at          DATETIME(6)  NOT NULL,
    CONSTRAINT uk_voter_epic UNIQUE (epic_no),
    INDEX idx_voter_name (name),
    INDEX idx_voter_name_local (name_local),
    INDEX idx_voter_assembly_booth (assembly_no, booth_no),
    INDEX idx_voter_mobile (mobile),
    INDEX idx_voter_district (district),
    INDEX idx_voter_village (village_ward),
    INDEX idx_voter_ward_no (ward_no),
    INDEX idx_voter_caste (caste),
    INDEX idx_voter_sub_caste (sub_caste),
    INDEX idx_voter_membership_no (membership_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- masters
CREATE TABLE IF NOT EXISTS assemblies (
    id            BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    assembly_no   INT          NOT NULL,
    name          VARCHAR(100) NOT NULL,
    name_local    VARCHAR(100),
    district      VARCHAR(100),
    state         VARCHAR(100),
    reserved_for  VARCHAR(10),
    CONSTRAINT uk_assembly_no UNIQUE (assembly_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booths (
    id            BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    assembly_no   INT          NOT NULL,
    booth_no      INT          NOT NULL,
    name          VARCHAR(200) NOT NULL,
    name_local    VARCHAR(200),
    location      VARCHAR(300),
    village_ward  VARCHAR(100),
    CONSTRAINT uk_booth UNIQUE (assembly_no, booth_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parties (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    name_local  VARCHAR(150),
    short_name  VARCHAR(20)  NOT NULL,
    symbol      VARCHAR(100),
    color       VARCHAR(7),
    CONSTRAINT uk_party_short UNIQUE (short_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS elections (
    id             BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(150) NOT NULL,
    type           VARCHAR(20)  NOT NULL,
    election_year  INT          NOT NULL,
    poll_date      DATE,
    current        BIT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS candidates (
    id           BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    election_id  BIGINT       NOT NULL,
    assembly_no  INT          NOT NULL,
    name         VARCHAR(120) NOT NULL,
    name_local   VARCHAR(120),
    party_id     BIGINT,
    ballot_no    INT,
    INDEX idx_candidate_contest (election_id, assembly_no),
    CONSTRAINT fk_candidate_election FOREIGN KEY (election_id) REFERENCES elections (id),
    CONSTRAINT fk_candidate_party FOREIGN KEY (party_id) REFERENCES parties (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- results
CREATE TABLE IF NOT EXISTS booth_results (
    id            BIGINT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    election_id   BIGINT      NOT NULL,
    assembly_no   INT         NOT NULL,
    booth_no      INT         NOT NULL,
    candidate_id  BIGINT      NOT NULL,
    votes         INT         NOT NULL,
    entered_by    VARCHAR(50),
    updated_at    DATETIME(6),
    CONSTRAINT uk_booth_result UNIQUE (candidate_id, booth_no),
    INDEX idx_result_booth (election_id, assembly_no, booth_no),
    CONSTRAINT fk_result_candidate FOREIGN KEY (candidate_id) REFERENCES candidates (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- users & audit
-- Role-based access: users (app_users) <- user_roles -> roles <- role_permissions (permission codes).
-- Default roles ADMIN / OPERATOR / VIEWER are created by the application on first start.
CREATE TABLE IF NOT EXISTS roles (
    id           BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(40)   NOT NULL,
    name         VARCHAR(100)  NOT NULL,
    description  VARCHAR(300),
    system_role  BIT           NOT NULL DEFAULT 0,
    CONSTRAINT uk_role_code UNIQUE (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id     BIGINT       NOT NULL,
    permission  VARCHAR(30)  NOT NULL COMMENT 'e.g. VOTER_EDIT, REPORT_SENSITIVE, USER_ADMIN',
    CONSTRAINT uk_role_permission UNIQUE (role_id, permission),
    CONSTRAINT fk_permission_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_users (
    id             BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(50)  NOT NULL,
    password_hash  VARCHAR(100) NOT NULL COMMENT 'BCrypt',
    full_name      VARCHAR(100) NOT NULL,
    enabled        BIT          NOT NULL DEFAULT 1,
    assembly_no    INT                   COMMENT 'NULL = may log in to any assembly',
    last_login     DATETIME(6),
    created_at     DATETIME(6),
    CONSTRAINT uk_user_username UNIQUE (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
    user_id  BIGINT NOT NULL,
    role_id  BIGINT NOT NULL,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES app_users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id         BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    at         DATETIME(6)  NOT NULL,
    username   VARCHAR(50),
    action     VARCHAR(30)  NOT NULL,
    entity     VARCHAR(30),
    entity_id  VARCHAR(50),
    details    VARCHAR(500),
    ip_address VARCHAR(45)           COMMENT 'IP address of the user',
    user_agent VARCHAR(255)          COMMENT 'Browser / device',
    INDEX idx_audit_time (at),
    INDEX idx_audit_ip (ip_address),
    INDEX idx_audit_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The first administrator is created by the application on first start
-- (app.security.admin.username / ADMIN_PASSWORD).

-- ---------------------------------------------------------------- SMS / WhatsApp
CREATE TABLE IF NOT EXISTS message_templates (
    id                    BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name                  VARCHAR(100)  NOT NULL,
    channel               VARCHAR(10)   NOT NULL COMMENT 'SMS / WHATSAPP',
    language              VARCHAR(10)   NOT NULL,
    body                  VARCHAR(1000) NOT NULL,
    provider_template_id  VARCHAR(100)           COMMENT 'DLT template id / WhatsApp template name / Twilio Content SID',
    params                VARCHAR(300)           COMMENT 'placeholders in provider variable order',
    active                BIT           NOT NULL DEFAULT 1,
    created_at            DATETIME(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_campaigns (
    id              BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)  NOT NULL,
    channel         VARCHAR(10)   NOT NULL,
    template_id     BIGINT        NOT NULL,
    filter_query    VARCHAR(1000),
    filter_summary  VARCHAR(500),
    status          VARCHAR(10)   NOT NULL,
    scheduled_at    DATETIME(6),
    total           INT           NOT NULL,
    sent            INT           NOT NULL,
    failed          INT           NOT NULL,
    skipped         INT           NOT NULL,
    created_by      VARCHAR(50),
    created_at      DATETIME(6),
    completed_at    DATETIME(6),
    CONSTRAINT fk_campaign_template FOREIGN KEY (template_id) REFERENCES message_templates (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_outbox (
    id            BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    campaign_id   BIGINT        NOT NULL,
    voter_id      BIGINT,
    voter_name    VARCHAR(120),
    mobile        VARCHAR(16)   NOT NULL COMMENT 'E.164',
    body          VARCHAR(1000) NOT NULL,
    param_values  VARCHAR(1000),
    status        VARCHAR(10)   NOT NULL COMMENT 'QUEUED / SENDING / SENT / FAILED / SKIPPED',
    attempts      INT           NOT NULL,
    provider_id   VARCHAR(100),
    error         VARCHAR(300),
    created_at    DATETIME(6),
    sent_at       DATETIME(6),
    INDEX idx_outbox_status (status, campaign_id),
    INDEX idx_outbox_campaign (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_optouts (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    mobile      VARCHAR(10)  NOT NULL,
    channel     VARCHAR(10)           COMMENT 'NULL = every channel',
    reason      VARCHAR(200),
    created_by  VARCHAR(50),
    created_at  DATETIME(6),
    CONSTRAINT uk_optout UNIQUE (mobile, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- castes (master)
CREATE TABLE IF NOT EXISTS castes (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    name_local  VARCHAR(100),
    category    VARCHAR(10)           COMMENT 'GEN / OBC / SC / ST (optional)',
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uk_caste_name UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- sub castes (master)
CREATE TABLE IF NOT EXISTS sub_castes (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    caste_id    BIGINT       NOT NULL,
    name        VARCHAR(100) NOT NULL,
    name_local  VARCHAR(100),
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uk_sub_caste UNIQUE (caste_id, name),
    CONSTRAINT fk_sub_caste_caste FOREIGN KEY (caste_id) REFERENCES castes (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
