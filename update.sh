#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_DIR="$HOME/CodeRED-Platform"
APP_SERVICE="app"
BACKUP_DIR="$HOME/codered-backups"
BACKUP_FILE="$BACKUP_DIR/untracked-$(date +%Y%m%d-%H%M%S).tar.gz"

echo "========================================"
echo " Actualizando CodeRED Platform"
echo "========================================"

cd "$PROJECT_DIR"

echo ""
echo "[1/9] Comprobando archivos sin seguimiento..."

mkdir -p "$BACKUP_DIR"

if [ -n "$(git ls-files --others --exclude-standard)" ]; then
    echo "Creando respaldo de archivos sin seguimiento..."

    git ls-files --others --exclude-standard -z \
        | tar --null -T - -czf "$BACKUP_FILE"

    echo "Respaldo creado en:"
    echo "$BACKUP_FILE"
else
    echo "No hay archivos sin seguimiento para respaldar."
fi

echo ""
echo "[2/9] Eliminando archivos generados que bloquean Git..."

rm -f .phpunit.result.cache
rm -f composer.lock
rm -f package-lock.json
rm -f storage/framework/.codered_bootstrapped
rm -rf public/vendor/livewire
rm -rf public/storage

echo ""
echo "[3/9] Descargando cambios de Git..."

git pull --ff-only

echo ""
echo "[4/9] Reconstruyendo y levantando contenedores..."

docker compose up -d --build

echo ""
echo "[5/9] Esperando que Laravel esté disponible..."

APP_READY=false

for intento in {1..30}; do
    if docker compose exec -T "$APP_SERVICE" \
        php artisan --version >/dev/null 2>&1; then
        APP_READY=true
        echo "El contenedor app está listo."
        break
    fi

    echo "Esperando contenedor app... intento $intento/30"
    sleep 2
done

if [ "$APP_READY" != "true" ]; then
    echo ""
    echo "ERROR: Laravel no respondió correctamente."
    echo ""

    docker compose ps
    docker compose logs --tail=100 "$APP_SERVICE"

    exit 1
fi

echo ""
echo "[6/9] Instalando dependencias de Composer..."

docker compose exec -T "$APP_SERVICE" \
    composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

echo ""
echo "[7/9] Ejecutando migraciones..."

docker compose exec -T "$APP_SERVICE" \
    php artisan migrate --force

echo ""
echo "[8/9] Limpiando y regenerando cachés de Laravel..."

docker compose exec -T "$APP_SERVICE" php artisan optimize:clear
docker compose exec -T "$APP_SERVICE" php artisan config:cache
docker compose exec -T "$APP_SERVICE" php artisan route:cache
docker compose exec -T "$APP_SERVICE" php artisan view:cache

echo ""
echo "Creando enlace público de storage..."

docker compose exec -T "$APP_SERVICE" \
    php artisan storage:link || true

echo ""
echo "[9/9] Reiniciando servicios auxiliares..."

docker compose restart queue scheduler

echo ""
echo "Estado final de los contenedores:"
docker compose ps

echo ""
echo "========================================"
echo " CodeRED Platform actualizado correctamente"
echo "========================================"