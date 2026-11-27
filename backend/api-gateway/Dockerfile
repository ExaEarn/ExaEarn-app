FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --classmap-authoritative --no-scripts

FROM php:8.3-fpm-alpine
RUN apk upgrade --no-cache libxml2 \
    && apk add --no-cache icu-libs libpq libzip && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev libzip-dev \
    && docker-php-ext-install bcmath intl opcache pcntl pdo_pgsql zip \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && apk del .build-deps
WORKDIR /app
COPY --from=vendor /app/vendor vendor
COPY . .
COPY docker/production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/zz-production.conf
ENV LOG_CHANNEL=stderr
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache
USER root
EXPOSE 9000
STOPSIGNAL SIGTERM
CMD ["php-fpm", "-F"]
