#CREATE USER localhost & 127.0.0.1
-- CREATE USER IF NOT EXISTS 'connector_user'@'%' IDENTIFIED BY '12345678'; -- this could also be used
CREATE USER IF NOT EXISTS 'connector_user'@'localhost' IDENTIFIED BY '12345678';
CREATE USER IF NOT EXISTS 'connector_user'@'127.0.0.1' IDENTIFIED BY '12345678';

