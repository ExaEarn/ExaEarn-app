FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --classmap-authoritative --no-scripts

FROM php:8.3-cli-alpine
RUN apk add --no-cache icu-libs libpq libzip && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev libzip-dev \
    && docker-php-ext-install bcmath intl opcache pcntl pdo_pgsql zip \
    && apk del .build-deps
WORKDIR /app
COPY --from=vendor /app/vendor vendor
COPY . .
RUN php artisan package:discover --ansi && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 8080
STOPSIGNAL SIGTERM
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]

