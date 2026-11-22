-- Admin > Settings (e.g. SMS / WhatsApp on or off). The application also adds this automatically. Safe to run again.
SET NAMES utf8mb4;
-- ---------------------------------------------------------------- settings changed on screen (Admin > Settings)
CREATE TABLE IF NOT EXISTS app_settings (
    name        VARCHAR(64)   NOT NULL PRIMARY KEY,
    value       VARCHAR(1000),
    updated_by  VARCHAR(50),
    updated_at  DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
