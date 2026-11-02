# House Cost Planner – production image for Coolify (or any Docker host).
# Web server: nginx + PHP-FPM on port 8080 (serversideup/php).

# 1. PHP dependencies
FROM serversideup/php:8.4-cli AS vendor
USER root
RUN install-php-extensions intl bcmath exif gd
USER www-data
WORKDIR /var/www/html
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && php artisan filament:assets

# 2. Front-end assets (the Filament theme reads vendor/ and app/)
FROM node:22-alpine AS assets
WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci
COPY --from=vendor /var/www/html ./
RUN npm run build

# 3. Runtime
FROM serversideup/php:8.4-fpm-nginx
USER root
RUN install-php-extensions intl bcmath exif gd
USER www-data

ENV AUTORUN_ENABLED=true \
    PHP_OPCACHE_ENABLE=1 \
    PHP_UPLOAD_MAX_FILE_SIZE=20M \
    PHP_POST_MAX_SIZE=25M

WORKDIR /var/www/html
COPY --chown=www-data:www-data --from=vendor /var/www/html ./
COPY --chown=www-data:www-data --from=assets /var/www/html/public/build ./public/build

EXPOSE 8080
