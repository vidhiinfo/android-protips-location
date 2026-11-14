-- =====================================================================
-- Voter Information System - MySQL 8 schema (also works on MariaDB 10.5+)
-- Run once as a DBA user:   mysql -u root -p < db/schema.sql
--
-- MULTILINGUAL DATA: the database, every table and every connection use utf8mb4,
-- MySQL's full Unicode encoding (4 bytes per character). Any column can store Hindi,
-- Bengali, Urdu, Marathi, Gujarati, Punjabi, Tamil, Telugu, English ... side by side.
-- (MySQL's old "utf8" is only 3 bytes and must NOT be used.)
-- VARCHAR(n) counts characters, not bytes, so a 120-character name fits in any script.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS voter_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE voter_db;

-- Application user with only the rights it needs (change the password!)
CREATE USER IF NOT EXISTS 'voter_app'@'localhost' IDENTIFIED BY 'change_me';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON voter_db.* TO 'voter_app'@'localhost';
FLUSH PRIVILEGES;

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
    district            VARCHAR(100),
    pincode             VARCHAR(6),
    mobile              VARCHAR(10),
    alt_mobile          VARCHAR(10),
    email               VARCHAR(120),
    category            VARCHAR(50),
    remarks             VARCHAR(500),
    created_at          DATETIME(6)  NOT NULL,
    updated_at          DATETIME(6)  NOT NULL,
    CONSTRAINT uk_voter_epic UNIQUE (epic_no),
    INDEX idx_voter_name (name),
    INDEX idx_voter_name_local (name_local),
    INDEX idx_voter_assembly_booth (assembly_no, booth_no),
    INDEX idx_voter_mobile (mobile),
    INDEX idx_voter_district (district),
    INDEX idx_voter_village (village_ward)
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
CREATE TABLE IF NOT EXISTS app_users (
    id             BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(50)  NOT NULL,
    password_hash  VARCHAR(100) NOT NULL COMMENT 'BCrypt',
    full_name      VARCHAR(100) NOT NULL,
    role           VARCHAR(10)  NOT NULL COMMENT 'ADMIN / OPERATOR / VIEWER',
    enabled        BIT          NOT NULL DEFAULT 1,
    assembly_no    INT                   COMMENT 'NULL = may log in to any assembly',
    last_login     DATETIME(6),
    created_at     DATETIME(6),
    CONSTRAINT uk_user_username UNIQUE (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id         BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    at         DATETIME(6)  NOT NULL,
    username   VARCHAR(50),
    action     VARCHAR(30)  NOT NULL,
    entity     VARCHAR(30),
    entity_id  VARCHAR(50),
    details    VARCHAR(500),
    INDEX idx_audit_time (at),
    INDEX idx_audit_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The first administrator is created by the application on first start
-- (app.security.admin.username / ADMIN_PASSWORD).
