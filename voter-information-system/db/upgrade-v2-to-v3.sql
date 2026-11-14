-- Upgrade version 2 (one role column per user) to version 3 (roles, role_permissions, user_roles)
-- and add the messaging tables.
--   1) mysql -u root -p voter_db < db/schema.sql            (creates the new tables, keeps data)
--   2) start the application once                          (creates roles ADMIN / OPERATOR / VIEWER with their permissions)
--   3) mysql -u root -p voter_db < db/upgrade-v2-to-v3.sql  (moves each user's old role into user_roles)

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM app_users u JOIN roles r ON r.code = u.role;

ALTER TABLE app_users DROP COLUMN role;
