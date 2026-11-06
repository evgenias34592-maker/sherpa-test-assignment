FROM composer:2 AS vendor

WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --ignore-platform-req=ext-gd --ignore-platform-req=ext-sockets
COPY backend/ ./
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

FROM php:8.3-fpm-bookworm AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev libcurl4-openssl-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev unzip gosu \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql zip curl opcache gd sockets \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www
COPY --from=vendor /app /var/www
RUN mkdir -p /var/www/var/proxies /var/www/storage/imports /var/www/storage/images \
    && chown -R www-data:www-data /var/www/var /var/www/storage

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
