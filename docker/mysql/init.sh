#!/bin/bash

set -eo pipefail

: "${DB_USER:?DB_USER is required}"
: "${DB_PASS:?DB_PASS is required}"

run_sql() {
    mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" "$@"
}

for file in 1_database.sql 2_tables.sql 5_seeder.sql; do
    echo "connectionyou init: running ${file}"
    run_sql < "/schema/${file}"
done

echo "connectionyou init: creating app user ${DB_USER}"
run_sql <<SQL
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT SELECT, INSERT, UPDATE ON \`connectionyou\`.\`users\` TO '${DB_USER}'@'%';
GRANT SELECT, INSERT, UPDATE ON \`connectionyou\`.\`user_logs\` TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL
