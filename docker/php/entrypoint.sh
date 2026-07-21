#!/bin/sh
set -eu

git config --global --add safe.directory /var/www/html >/dev/null 2>&1 || true

APP_DIR="/var/www/html"
BOOTSTRAP_MARKER="$APP_DIR/storage/framework/.codered_bootstrapped"
TARGETS="$APP_DIR/bootstrap/cache $APP_DIR/storage/app/private $APP_DIR/storage/framework/cache $APP_DIR/storage/framework/sessions $APP_DIR/storage/framework/views $APP_DIR/storage/logs"

for dir in $TARGETS; do
    mkdir -p "$dir"
done

touch "$APP_DIR/storage/logs/laravel.log"

chown -R www:www "$APP_DIR/bootstrap/cache" "$APP_DIR/storage"
find "$APP_DIR/bootstrap/cache" "$APP_DIR/storage" -type d -exec chmod 775 {} \;
find "$APP_DIR/bootstrap/cache" "$APP_DIR/storage" -type f -exec chmod 664 {} \;

bootstrap_application() {
    cd "$APP_DIR"

    if [ ! -f vendor/autoload.php ]; then
        composer install --no-interaction --prefer-dist --optimize-autoloader
    fi

    if [ -f package.json ]; then
        if [ -f package-lock.json ]; then
            if [ ! -d node_modules ]; then
                npm ci
            fi
        else
            npm install
        fi
    fi

    if [ ! -f public/build/manifest.json ]; then
        npm run build
    fi

    if ! grep -qE '^APP_KEY=base64:.+' .env; then
        php artisan key:generate
    fi

    php artisan optimize:clear
    php artisan migrate --force
    php artisan db:seed --force
    php artisan storage:link || true
    php artisan config:clear
    php artisan optimize:clear

    touch "$BOOTSTRAP_MARKER"
}

if [ "${1:-}" = "php-fpm" ] || [ "${1:-}" = "php-fpm8.3" ]; then
    bootstrap_application
    exec "$@"
fi

if [ "${1:-}" = "php" ] && [ "${2:-}" = "artisan" ]; then
    if [ ! -f "$BOOTSTRAP_MARKER" ]; then
        while [ ! -f "$BOOTSTRAP_MARKER" ]; do
            sleep 2
        done
    fi
fi

exec su-exec www "$@"
