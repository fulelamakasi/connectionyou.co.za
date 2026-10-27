-- UPDATE PRIVILEGES
-- GRANT ALL PRIVILEGES ON connectionyou.users TO 'connector_user'@'%'; -- this could also be used
GRANT SELECT, INSERT, UPDATE ON connectionyou.users TO 'connector_user'@'localhost';
GRANT SELECT, INSERT, UPDATE ON connectionyou.users TO 'connector_user'@'127.0.0.1';
-- GRANT ALL PRIVILEGES ON connectionyou.user_logs TO 'connector_user'@'%';
GRANT SELECT, INSERT, UPDATE ON connectionyou.user_logs TO 'connector_user'@'localhost';
GRANT SELECT, INSERT, UPDATE ON connectionyou.user_logs TO 'connector_user'@'127.0.0.1';
FLUSH PRIVILEGES;
