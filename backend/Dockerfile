# syntax=docker/dockerfile:1

# ---------- base: PHP runtime with required extensions ----------
FROM php:8.4-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql amqp zip gd intl opcache \
    && apk add --no-cache fcgi

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
ENV OPCACHE_VALIDATE_TIMESTAMPS=1
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

WORKDIR /var/www/html
RUN mkdir -p var/cache var/imports public/uploads/products \
    && chown -R www-data:www-data /var/www/html

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

# ---------- vendor: production dependencies ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---------- dev: tooling for tests / static analysis (source is bind-mounted) ----------
FROM base AS dev
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer
USER www-data

# ---------- prod: application image ----------
FROM base AS prod
ENV APP_ENV=prod OPCACHE_VALIDATE_TIMESTAMPS=0
COPY --chown=www-data:www-data --from=vendor /app /var/www/html
USER www-data
HEALTHCHECK --interval=10s --timeout=3s --retries=5 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1
