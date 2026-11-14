-- Upgrade a database created by version 1 (voters table only) to version 2.
--   mysql -u root -p voter_db < db/upgrade-v1-to-v2.sql
-- Afterwards run db/schema.sql (CREATE TABLE IF NOT EXISTS adds the new tables),
-- or simply start the application with DDL_AUTO=update.

-- 1. Make sure everything is full Unicode (utf8mb4). Safe to run more than once.
ALTER DATABASE voter_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE voters CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Local-language columns next to the English ones
ALTER TABLE voters
    ADD COLUMN name_local          VARCHAR(120) AFTER name,
    ADD COLUMN relative_name_local VARCHAR(120) AFTER relative_name,
    ADD COLUMN address_local       VARCHAR(300) AFTER address,
    ADD INDEX idx_voter_name_local (name_local);

-- 3. Version 1 printed house no. inside the address; it now has its own column (no change needed).
