FROM composer:2.8 AS vendor

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.4-fpm-alpine AS php-base

RUN apk add --no-cache \
        freetype \
        icu-libs \
        libjpeg-turbo \
        libpng \
        libzip \
        mariadb-client \
        nginx \
        oniguruma \
        supervisor \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl opcache pcntl pdo_mysql zip \
    && apk del .build-deps

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-meja.ini

FROM php-base AS wayfinder

WORKDIR /var/www/html

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .

ENV APP_ENV=production \
    APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
    APP_DEBUG=false

RUN php artisan package:discover --ansi \
    && php artisan wayfinder:generate --with-form

FROM node:22-alpine AS assets

WORKDIR /var/www/html

ENV WAYFINDER_COMMAND=true

COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=wayfinder /var/www/html/resources/js/actions ./resources/js/actions
COPY --from=wayfinder /var/www/html/resources/js/routes ./resources/js/routes

RUN npm run build

FROM php-base AS app

WORKDIR /var/www/html

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
COPY --from=wayfinder /var/www/html/bootstrap/cache/packages.php ./bootstrap/cache/packages.php
COPY --from=wayfinder /var/www/html/bootstrap/cache/services.php ./bootstrap/cache/services.php
COPY --from=wayfinder /var/www/html/resources/js/actions ./resources/js/actions
COPY --from=wayfinder /var/www/html/resources/js/routes ./resources/js/routes
COPY --from=assets /var/www/html/public/build ./public/build

RUN mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data bootstrap/cache storage

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf

EXPOSE 8080

CMD ["supervisord", "-c", "/etc/supervisord.conf"]
