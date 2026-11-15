-- =====================================================================
-- Upgrade an EXISTING Voter Information System database (created before Nov 2026)
-- with the new voter profile columns. Run once: phpMyAdmin > your database > SQL /
-- Import. New installations do not need this (db/schema.sql already has them).
-- =====================================================================
ALTER TABLE voters
    ADD COLUMN membership_no       VARCHAR(50)           COMMENT 'party membership no.',
    ADD COLUMN dob                 DATE,
    ADD COLUMN anniversary         DATE                  COMMENT 'marriage anniversary',
    ADD COLUMN deleted             TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = deleted from the electoral roll',
    ADD COLUMN shift1              VARCHAR(100),
    ADD COLUMN shift2              VARCHAR(100),
    ADD COLUMN place_of_origin     VARCHAR(150),
    ADD COLUMN current_place       VARCHAR(150),
    ADD COLUMN linked_with         VARCHAR(150),
    ADD COLUMN social_status       VARCHAR(100),
    ADD COLUMN political_status    VARCHAR(100),
    ADD COLUMN organisational_status VARCHAR(100),
    ADD COLUMN beneficiary1        VARCHAR(150)          COMMENT 'government scheme beneficiary',
    ADD COLUMN beneficiary2        VARCHAR(150),
    ADD COLUMN beneficiary3        VARCHAR(150),
    ADD COLUMN caste               VARCHAR(100),
    ADD INDEX idx_voter_caste (caste),
    ADD INDEX idx_voter_membership_no (membership_no);
