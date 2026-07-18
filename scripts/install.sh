#!/bin/sh
set -eu

if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        echo "No existe .env ni .env.example."
        exit 1
    fi
fi

docker compose exec app composer install

if [ -f package-lock.json ]; then
    docker compose exec app npm ci
else
    docker compose exec app npm install
fi

if ! grep -qE '^APP_KEY=base64:.+' .env; then
    docker compose exec app php artisan key:generate
fi

docker compose exec app npm run build

if docker compose exec app php artisan migrate:status >/dev/null 2>&1; then
    docker compose exec app php artisan optimize:clear
    docker compose exec app php artisan migrate
else
    echo "No fue posible verificar PostgreSQL. Revisa la conexión antes de continuar."
    exit 1
fi

docker compose exec app php artisan storage:link || true
docker compose exec app php artisan health:redis || true
