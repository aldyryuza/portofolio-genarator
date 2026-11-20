# syntax=docker/dockerfile:1

# Production images for the Portfolio CMS.
#   target "app": PHP-FPM with the application (also used for the scheduler)
#   target "web": nginx serving /public and proxying PHP requests to "app"
# See docs/DOCKER.md.

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22
ARG NGINX_VERSION=1.27

# ---------------------------------------------------------------------------
# PHP runtime with the extensions the app needs (GD with WebP/JPEG for media).
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-bookworm AS php-base

COPY --from=mlocati/php-extension-installer:2.7 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd zip exif intl pdo_mysql opcache

COPY docker/php/production.ini /usr/local/etc/php/conf.d/zz-production.ini
WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# Composer dependencies (no dev packages, no scripts until the source is present).
# ---------------------------------------------------------------------------
FROM php-base AS vendor

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --no-autoloader

# ---------------------------------------------------------------------------
# Frontend build (Vite + Tailwind). Tailwind also scans Laravel's pagination views.
# ---------------------------------------------------------------------------
FROM node:${NODE_VERSION}-bookworm-slim AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY --from=vendor /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# ---------------------------------------------------------------------------
# Application image (php-fpm).
# ---------------------------------------------------------------------------
FROM php-base AS app

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi \
    && rm /usr/local/bin/composer \
    && mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/portfolio-entrypoint
RUN chmod +x /usr/local/bin/portfolio-entrypoint

USER www-data
ENTRYPOINT ["portfolio-entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# Web server image: static files only, PHP goes to the "app" service.
# ---------------------------------------------------------------------------
FROM nginx:${NGINX_VERSION}-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
