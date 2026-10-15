# PHP-FPM image untuk apps/api. OPcache diaktifkan (FSD §4.2 poin 5) —
# mengurangi parsing/compilation per request pada beban tinggi.
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        postgresql-dev \
        icu-dev \
        libzip-dev \
        libsodium-dev \
        oniguruma-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        sodium \
        bcmath \
        opcache

COPY opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

CMD ["php-fpm"]
