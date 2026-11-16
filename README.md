# connectionyou.co.za

A small PHP + MySQL user system. Visitors fill an enquiry from a public form. Requests between the browser and the server are AES-256-CBC encrypted. South African ID numbers are stored as an HMAC-SHA256 hash (for duplicate checks) plus an AES-256-CBC encrypted copy (for the password-protected reveal).

## Requirements

- PHP 8.1+ with `pdo_mysql`, `openssl`, `mbstring`, `php-mysql`, `php-fpm`
- MySQL 8 or MariaDB 10.6+
- Apache 2.4 (or Nginx + PHP-FPM)
- **HTTPS in production.** The browser uses the Web Crypto API, which only runs on HTTPS or `http://localhost`. On plain HTTP the forms show "Secure connection required".

## Setup

### 1. Clone repository 
```bash
#change directory to workspace
cd workspace
```
```bash
#Clone or extract zip file in workspace HTTPS
git clone https://github.com/fulelamakasi/connectionyou.co.za.git 
#or SSH
git clone git@github.com:fulelamakasi/connectionyou.co.za.git
```
#Current Application tag release/4.0.0

### 2. Environment

```bash
cp .env.example .env
```

Then set fresh secrets for each environment:

```bash
# APP_KEY – used to HMAC ID numbers. Set once; changing it makes stored ID hashes unsearchable.
php -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;'

# ENCRYPTION_KEY – must be exactly 32 characters (AES-256 key).
php -r 'echo bin2hex(random_bytes(16)), PHP_EOL;'
```

Copy and paste the output to .env APP_KEY & ENCRYPTION_KEY

Set `REVEAL_PASSWORD` – the password asked for on the entries page before a full mobile number or ID number is shown. Plaintext works; a `password_hash()` value is also accepted:

```bash
php -r 'echo password_hash("your-password", PASSWORD_DEFAULT), PHP_EOL;'
```

Leave it empty to disable revealing.

### 3. Database

Run the scripts in order as a MySQL admin user:

```bash
mysql -u root -p < database/1_database.sql
mysql -u root -p < database/2_tables.sql
mysql -u root -p < database/3_sql_user.sql
mysql -u root -p < database/4_permissions_privileges.sql
mysql -u root -p < database/5_seeder.sql
mysql -u root -p < database/6_add_id_number_encrypted.sql
```

The app user gets `SELECT, INSERT, UPDATE` on `users` and `user_logs` only. Archiving is a soft delete (`deleted_at`), so `DELETE` is never needed.

Seeded rows carry pre-made ID hashes. They display fine, but because the original IDs aren't known they can't be matched by the duplicate-ID check. New enquiries are hashed with `hashIdNumber()` using your `APP_KEY`.


### 4. Serve App or Move to #8 docker implementation alternative
``` bash
php -S localhost:8080 -t public
```

### 5. Public Endpoints
1. View Available Endpoints
http://localhost:8080/getRestFulResponse.php?type=
2. View Endpoint Response data
http://localhost:8080/getRestFulResponse.php?type=getTestQueryTypes
3. View Encrypted Response
http://localhost:8080/getRestFulResponse.php?type=getAllUsers

### 6. Protected details on the entries page
Lists return the mobile number masked (`078*****09`) and the ID number hidden. **Show number** / **Show ID number** asks for `REVEAL_PASSWORD` and calls:

http://localhost:8080/getRestFulResponse.php?type=revealUserField&query={ciphertext}&iv={iv}

The request is the encrypted `{user_id, field, password, timestamp}` (`field` is `phone_number` or `id_number`) and the response is encrypted `{user_id, field, value}`. Requests older than 5 minutes are rejected. Every attempt is written to `user_logs` (never the password or the value), and 5 wrong passwords from one IP lock it out for 15 minutes.

Entries saved before 2.1.0 (including the seeded rows) only have the ID hash, so their ID number can't be shown.

### 7. View application on web browser
You can now start testing the application



### 8. Docker

Runs the app on PHP 8.3 + Apache and MySQL 8.4. No PHP or MySQL needed on the host.

```bash
cp .env.example .env
# fill in APP_KEY, ENCRYPTION_KEY, REVEAL_PASSWORD, DB_PASS and MYSQL_ROOT_PASSWORD
docker compose up -d --build
```

Open http://localhost:8080 (change the port with `APP_PORT`).

- The image only contains `Database.php`, `config/bootstrap.php` and `public/`. Secrets are passed in as environment variables from `.env`, never copied into the image.
- On the first start of the `db_data` volume, `docker/mysql/init.sh` runs `1_database.sql`, `2_tables.sql` and `5_seeder.sql`, then creates `DB_USER` for the Docker network with `SELECT, INSERT, UPDATE` on `users` and `user_logs` only.
- The init only runs on an empty volume. Changing `DB_PASS` later needs `docker compose down -v` (wipes data) or an `ALTER USER` as root.
- If `REVEAL_PASSWORD` is a `password_hash()` value, wrap it in single quotes in `.env` so Compose doesn't treat the `$` parts as variables.
- Web Crypto only works on HTTPS or `localhost`. In production, put the container behind a TLS proxy (Nginx, Traefik, Caddy) and forward `X-Forwarded-Proto`.

Useful commands:

```bash
docker compose logs -f app                 # Apache + PHP errors
docker compose exec db mysql -uroot -p     # MySQL shell
docker compose down                        # stop (keeps data)
docker compose down -v                     # stop and delete the database volume
```

Build and run the image on its own (against an existing database):

```bash
docker build -t connectionyou:2.1.0 .
docker run -d -p 8080:80 --env-file .env -e DB_HOST=your-db-host connectionyou:2.1.0
```

