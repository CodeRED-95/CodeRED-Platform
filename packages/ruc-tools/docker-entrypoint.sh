#!/bin/sh
set -e

cd /app

if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

mkdir -p /root/.ruc-tool/backups /root/.ruc-tool/logs

exec "$@"
