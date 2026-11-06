#!/bin/sh
set -eu

mkdir -p /var/www/storage/imports /var/www/storage/images
chown -R www-data:www-data /var/www/storage /var/www/var || true

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    rm -f /var/www/storage/.migrations-ready
    gosu www-data php bin/console migrate
    gosu www-data php bin/console admin:ensure
    touch /var/www/storage/.migrations-ready
fi

if [ "$1" = "php-fpm" ]; then
    exec php-fpm
fi

exec gosu www-data "$@"
