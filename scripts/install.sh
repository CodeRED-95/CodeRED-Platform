#!/usr/bin/env bash
set -Eeuo pipefail

trap 'code=$?; echo "[ERROR] Fallo en la línea $LINENO" >&2; echo "[ERROR] Comando: ${BASH_COMMAND}" >&2; echo "[ERROR] Código de salida: $code" >&2; exit $code' ERR

if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        echo "No existe .env ni .env.example."
        exit 1
    fi
fi

docker compose up -d --build
docker compose exec -T app mkdir -p \
    storage/app/private/ruc/incoming \
    storage/app/private/ruc/working \
    storage/app/private/ruc/archive \
    storage/app/private/ruc/errors
docker compose exec -T app chown -R www:www storage/app/private/ruc
docker compose exec -T app chmod -R 775 storage/app/private/ruc

docker compose exec -T app sh -lc '
set -eu

composer install --no-interaction --prefer-dist --optimize-autoloader

if [ -f package.json ]; then
    if [ -f package-lock.json ]; then
        npm ci
    else
        npm install
    fi

    npm run build

    test -f public/build/manifest.json
fi

if ! grep -qE "^APP_KEY=base64:.+" .env; then
    php artisan key:generate
fi

php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link || true
php artisan optimize:clear
'
