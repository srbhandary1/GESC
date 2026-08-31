# PHP 8.4 CLI used for make verify / phpunit.postgres.xml on hosts that
# are not on the pinned series. Must include pcntl (ADMIN-ORG concurrency
# tests) and pdo_pgsql. Tag as ge-php84-pgsql:local.
FROM php:8.4.24-cli-bookworm

RUN apt-get update \
  && apt-get install -y --no-install-recommends libpq-dev git unzip \
  && docker-php-ext-install -j"$(nproc)" pdo_pgsql pcntl \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.10.2 /usr/bin/composer /usr/bin/composer
