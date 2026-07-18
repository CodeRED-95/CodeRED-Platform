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

docker compose up -d --build

docker compose exec -T app sh -lc '
set -eu

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

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
