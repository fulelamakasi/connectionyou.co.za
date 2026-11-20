# connectionyou.co.za - PHP 8.3 + Apache
# Stages: base (shared) -> test (PHPUnit, used by docker-compose.test.yml) -> app (default, deployed)
FROM php:8.3-apache AS base

# pdo_mysql is the only extension missing from the base image (openssl and mbstring are built in)
RUN docker-php-ext-install pdo_mysql \
    && a2enmod headers \
    && a2dissite 000-default \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/app.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY docker/apache/connectionyou.conf /etc/apache2/sites-available/connectionyou.conf
RUN a2ensite connectionyou

WORKDIR /var/www/html

# app code only, secrets come from the container environment (see .dockerignore)
COPY --chown=root:www-data Database.php ./
COPY --chown=root:www-data config/bootstrap.php ./config/
COPY --chown=root:www-data public/ ./public/
RUN find /var/www/html -type d -exec chmod 750 {} + \
    && find /var/www/html -type f -exec chmod 640 {} +


# ---------- test: same PHP build plus Composer, PHPUnit and the test suite ----------
FROM base AS test

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-progress --prefer-dist

COPY Database.php ./
COPY config/bootstrap.php ./config/
COPY public/ ./public/
COPY database/2_tables.sql ./database/
COPY tests/ ./tests/
COPY phpunit.xml.dist ./

CMD ["vendor/bin/phpunit"]


# ---------- app: what runs in production (default build target) ----------
FROM base AS app

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS "http://127.0.0.1/getRestFulResponse.php?type=" > /dev/null || exit 1
