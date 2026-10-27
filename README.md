# connectionyou.co.za

A small PHP + MySQL user system. Visitors fill an enquiry from a public form. Requests between the browser and the server are AES-256-CBC encrypted, and South African ID numbers are stored only as an HMAC-SHA256 hash.

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

### 3. Database

Run the scripts in order as a MySQL admin user:

```bash
mysql -u root -p < database/1_database.sql
mysql -u root -p < database/2_tables.sql
mysql -u root -p < database/3_sql_user.sql
mysql -u root -p < database/4_permissions_privileges.sql
mysql -u root -p < database/5_seeder.sql
```

The app user gets `SELECT, INSERT, UPDATE` on `users` and `user_logs` only. Archiving is a soft delete (`deleted_at`), so `DELETE` is never needed.

Seeded rows carry pre-made ID hashes. They display fine, but because the original IDs aren't known they can't be matched by the duplicate-ID check. New enquiries are hashed with `hashIdNumber()` using your `APP_KEY`.


### 4. Serve App
``` bash
php -S localhost:8080 -t public
```


# @TODO
Consider DOCKER for deployment, currently set for apache/nginx

