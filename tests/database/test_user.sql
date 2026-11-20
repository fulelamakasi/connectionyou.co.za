-- One-off setup for the test suite (run as a MySQL admin user):
--   mysql -u root -p < tests/database/test_user.sql
-- The suite creates and resets its own tables inside connectionyou_test,
-- so this user gets full rights on that database only.
CREATE DATABASE IF NOT EXISTS `connectionyou_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'connector_test'@'localhost' IDENTIFIED BY 'test1234';
CREATE USER IF NOT EXISTS 'connector_test'@'127.0.0.1' IDENTIFIED BY 'test1234';
CREATE USER IF NOT EXISTS 'connector_test'@'%' IDENTIFIED BY 'test1234';

GRANT ALL PRIVILEGES ON `connectionyou_test`.* TO 'connector_test'@'localhost';
GRANT ALL PRIVILEGES ON `connectionyou_test`.* TO 'connector_test'@'127.0.0.1';
GRANT ALL PRIVILEGES ON `connectionyou_test`.* TO 'connector_test'@'%';
FLUSH PRIVILEGES;
