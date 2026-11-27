-- =====================================================================
-- Upgrade an EXISTING Voter Information System database (created before Nov 2026)
-- with the voter profile columns. phpMyAdmin > your database > SQL / Import.
-- Safe to run more than once: done only when the columns are not there yet.
-- New installations do not need this (db/schema.sql already has them).
-- =====================================================================
SET @have := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'voters' AND column_name = 'membership_no');
SET @sql := IF(@have = 0, 'ALTER TABLE voters
    ADD COLUMN membership_no       VARCHAR(50)           COMMENT ''party membership no.'',
    ADD COLUMN dob                 DATE,
    ADD COLUMN anniversary         DATE                  COMMENT ''marriage anniversary'',
    ADD COLUMN deleted             TINYINT(1)   NOT NULL DEFAULT 0 COMMENT ''1 = deleted from the electoral roll'',
    ADD COLUMN shift1              VARCHAR(100),
    ADD COLUMN shift2              VARCHAR(100),
    ADD COLUMN place_of_origin     VARCHAR(150),
    ADD COLUMN current_place       VARCHAR(150),
    ADD COLUMN linked_with         VARCHAR(150),
    ADD COLUMN social_status       VARCHAR(100),
    ADD COLUMN political_status    VARCHAR(100),
    ADD COLUMN organisational_status VARCHAR(100),
    ADD COLUMN beneficiary1        VARCHAR(150)          COMMENT ''government scheme beneficiary'',
    ADD COLUMN beneficiary2        VARCHAR(150),
    ADD COLUMN beneficiary3        VARCHAR(150),
    ADD COLUMN caste               VARCHAR(100),
    ADD INDEX idx_voter_caste (caste),
    ADD INDEX idx_voter_membership_no (membership_no)', 'SELECT ''profile columns already there''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
