-- =====================================================================
-- Workers picked from the voter list: workers.voter_id (link to the voter record).
-- The application also adds it automatically on the first page load after the update. Safe to run more than once.
-- =====================================================================
SET @have := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'workers' AND column_name = 'voter_id');
SET @sql := IF(@have = 0, 'ALTER TABLE workers ADD COLUMN voter_id BIGINT NULL, ADD INDEX idx_worker_voter (voter_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
