#!/bin/sh
set -eu

TARGETS="/var/www/html/bootstrap/cache /var/www/html/storage/framework/cache /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views /var/www/html/storage/logs"

for dir in $TARGETS; do
    mkdir -p "$dir"
done

touch /var/www/html/storage/logs/laravel.log

chown -R www:www /var/www/html/bootstrap/cache /var/www/html/storage
find /var/www/html/bootstrap/cache /var/www/html/storage -type d -exec chmod 775 {} \;
find /var/www/html/bootstrap/cache /var/www/html/storage -type f -exec chmod 664 {} \;

if [ "${1:-}" = "php-fpm" ] || [ "${1:-}" = "php-fpm8.3" ]; then
    exec "$@"
fi

exec su-exec www "$@"
