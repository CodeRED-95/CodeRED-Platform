#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="${PROJECT_DIR:-$HOME/CodeRED-Platform}"
BACKUP_DIR="$PROJECT_DIR/storage/update-backups"
STAMP="$(date +%Y%m%d_%H%M%S)"

ok(){ echo "[OK] $*"; }
info(){ echo "[INFO] $*"; }
warn(){ echo "[AVISO] $*"; }
die(){ echo "[ERROR] $*" >&2; exit 1; }

trap 'echo "[ERROR] Fallo en la línea $LINENO" >&2' ERR

[[ -d "$PROJECT_DIR/.git" ]] || die "No existe una instalación válida en $PROJECT_DIR"
cd "$PROJECT_DIR"

mkdir -p "$BACKUP_DIR"

echo "============================================================"
echo "        Actualizando CodeRED Platform"
echo "============================================================"

if [[ -f .env ]]; then
    cp .env "$BACKUP_DIR/.env.$STAMP"
    ok "Backup del .env creado."
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
    warn "Hay cambios locales versionados."
    git status --short
    die "Guarda, confirma o descarta esos cambios antes de actualizar."
fi

OLD_COMPOSER="$(git rev-parse HEAD:composer.lock 2>/dev/null || true)"
OLD_PACKAGE="$(git rev-parse HEAD:package-lock.json 2>/dev/null || true)"
OLD_COMPOSE="$(git rev-parse HEAD:docker-compose.yml 2>/dev/null || true)"
OLD_DOCKERFILE="$(git rev-parse HEAD:docker/php/Dockerfile 2>/dev/null || true)"

info "Descargando cambios..."
git pull --ff-only

NEW_COMPOSER="$(git rev-parse HEAD:composer.lock 2>/dev/null || true)"
NEW_PACKAGE="$(git rev-parse HEAD:package-lock.json 2>/dev/null || true)"
NEW_COMPOSE="$(git rev-parse HEAD:docker-compose.yml 2>/dev/null || true)"
NEW_DOCKERFILE="$(git rev-parse HEAD:docker/php/Dockerfile 2>/dev/null || true)"

info "Actualizando servicios principales sin bloquear codered-queue..."
docker compose up -d postgres redis

if [[ "$OLD_COMPOSE" != "$NEW_COMPOSE" || "$OLD_DOCKERFILE" != "$NEW_DOCKERFILE" ]]; then
    info "Cambió la definición Docker. Construyendo imágenes..."
    docker compose build app queue scheduler
else
    info "No cambiaron Dockerfile ni Compose."
fi

docker compose up -d --no-deps app nginx scheduler

if [[ "$OLD_COMPOSER" != "$NEW_COMPOSER" ]]; then
    info "composer.lock cambió. Instalando dependencias PHP..."
    docker compose exec -T app composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [[ "$OLD_PACKAGE" != "$NEW_PACKAGE" ]]; then
    info "package-lock.json cambió. Instalando dependencias frontend..."
    docker compose exec -T app npm ci
    docker compose exec -T app npm run build
fi

info "Ejecutando migraciones..."
docker compose exec -T app php artisan migrate --force

info "Limpiando cachés..."
docker compose exec -T app php artisan optimize:clear

if [[ "$OLD_COMPOSE" != "$NEW_COMPOSE" || "$OLD_DOCKERFILE" != "$NEW_DOCKERFILE" ]]; then
    if docker compose exec -T app php artisan ruc:has-active --quiet; then
        warn "codered-queue NO fue recreado: existe una importación RUC activa."
        warn "Recréalo cuando la importación finalice."
    else
        info "No hay importaciones RUC activas. Recreando worker de colas..."
        docker compose up -d --no-deps --force-recreate queue
    fi
fi

echo
info "Estado final:"
docker compose ps

echo
echo "============================================================"
echo " CodeRED Platform actualizado correctamente"
echo "============================================================"
echo "Backup del .env: $BACKUP_DIR/.env.$STAMP"
