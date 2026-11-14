-- Voter Information System - MySQL 8 schema
-- Run once as a DBA user:  mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS voter_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE voter_db;

-- Application user with only the rights it needs (change the password!)
CREATE USER IF NOT EXISTS 'voter_app'@'localhost' IDENTIFIED BY 'change_me';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES ON voter_db.* TO 'voter_app'@'localhost';
FLUSH PRIVILEGES;

CREATE TABLE IF NOT EXISTS voters (
    id             BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    epic_no        VARCHAR(20)  NOT NULL,
    name           VARCHAR(120) NOT NULL,
    relative_name  VARCHAR(120),
    relation_type  VARCHAR(10),
    gender         VARCHAR(10)  NOT NULL,
    age            INT,
    assembly_no    INT          NOT NULL,
    assembly_name  VARCHAR(100),
    booth_no       INT          NOT NULL,
    booth_name     VARCHAR(200),
    serial_no      INT,
    house_no       VARCHAR(30),
    address        VARCHAR(300),
    village_ward   VARCHAR(100),
    district       VARCHAR(100),
    pincode        VARCHAR(6),
    mobile         VARCHAR(10),
    alt_mobile     VARCHAR(10),
    email          VARCHAR(120),
    category       VARCHAR(50),
    remarks        VARCHAR(500),
    created_at     DATETIME(6)  NOT NULL,
    updated_at     DATETIME(6)  NOT NULL,
    CONSTRAINT uk_voter_epic UNIQUE (epic_no),
    INDEX idx_voter_name (name),
    INDEX idx_voter_assembly_booth (assembly_no, booth_no),
    INDEX idx_voter_mobile (mobile),
    INDEX idx_voter_district (district),
    INDEX idx_voter_village (village_ward)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
