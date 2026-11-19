-- =====================================================================
-- Faster households for big assemblies: computed, indexed house_key column (house no. without spaces / hyphens / dots).
-- The application adds it automatically; this file is for phpMyAdmin if you prefer. Safe to run again.
-- =====================================================================
SET @have := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'voters' AND column_name = 'house_key');
SET @sql := IF(@have = 0, 'ALTER TABLE voters ADD COLUMN house_key VARCHAR(50) GENERATED ALWAYS AS (lower(replace(replace(replace(trim(coalesce(house_no, '''')), '' '', ''''), ''-'', ''''), ''.'', ''''))) VIRTUAL', 'SELECT ''house_key already there''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @have := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'voters' AND index_name = 'idx_voter_house');
SET @sql := IF(@have = 0, 'ALTER TABLE voters ADD INDEX idx_voter_house (assembly_no, house_key)', 'SELECT ''idx_voter_house already there''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
