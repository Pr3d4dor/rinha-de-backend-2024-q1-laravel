FROM composer:latest AS composer

FROM php:8.3-alpine

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache \
    postgresql-dev \
    && docker-php-ext-install pdo_pgsql pcntl

COPY . /app
WORKDIR /app

RUN composer install -o -a --apcu-autoloader --no-dev && php artisan optimize

CMD ["php", "artisan", "octane:start", "--host=0.0.0.0", "--port=8000"]
