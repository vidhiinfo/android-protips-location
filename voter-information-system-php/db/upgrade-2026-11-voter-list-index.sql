-- =====================================================================
-- Faster voter list for big assemblies: index on (assembly, booth, serial no.).
-- The application adds it automatically; this file is for phpMyAdmin if you prefer. Safe to run again.
-- =====================================================================
SET @have := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'voters' AND index_name = 'idx_voter_list');
SET @sql := IF(@have = 0, 'ALTER TABLE voters ADD INDEX idx_voter_list (assembly_no, booth_no, serial_no)', 'SELECT ''idx_voter_list already there''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
