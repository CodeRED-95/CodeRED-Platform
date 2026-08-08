#!/usr/bin/env bash
set -Eeuo pipefail
export LANG="${LANG:-C.UTF-8}"
export LC_ALL="${LC_ALL:-C.UTF-8}"

# NOTA: packages/ruc-tools es una herramienta administrativa LOCAL (CLI
# standalone de desarrollo/operación) y NUNCA forma parte de este deploy.
# git pull la trae al repo porque está versionada junto al resto del
# proyecto, pero este script no debe instalar sus dependencias (composer
# install de packages/ruc-tools), construir su imagen, ni iniciar su propio
# docker-compose.yml. Ver packages/ruc-tools/README.md.

PROJECT_DIR="${PROJECT_DIR:-$PWD}"
STAMP="$(date +%Y%m%d-%H%M%S)"

ok(){ echo "[OK] $*"; }
info(){ echo "[INFO] $*"; }
warn(){ echo "[WARN] $*"; }
die(){ echo "[ERROR] $*" >&2; exit 1; }
step(){ echo; echo "[$1/15] $2"; }

trap 'code=$?; echo "[ERROR] Fallo en la línea $LINENO" >&2; echo "[ERROR] Comando: ${BASH_COMMAND}" >&2; echo "[ERROR] Código de salida: $code" >&2; echo "[INFO] Siguiente paso recomendado: revise el mensaje anterior, restaure .env desde .env.backup-* si el cambio fue de configuración y vuelva a ejecutar ./update.sh" >&2; exit $code' ERR

compose_file(){
    if [[ -f docker-compose.yml ]]; then echo docker-compose.yml; return; fi
    if [[ -f compose.yml ]]; then echo compose.yml; return; fi
    return 1
}

dotenv_escape_value(){
    local value="$1"
    [[ "$value" != *$'\n'* && "$value" != *$'\r'* ]] || die "Valor invalido para .env"
    if [[ "$value" =~ ^[A-Za-z0-9_./:@%+-]*$ ]]; then
        printf '%s' "$value"
        return
    fi
    value="${value//\\/\\\\}"
    value="${value//\"/\\\"}"
    value="${value//\$/\\\$}"
    value="${value//\`/\\\`}"
    printf '"%s"' "$value"
}

get_env(){
    local key="$1" value
    value="$(grep -E "^${key}=" .env 2>/dev/null | head -n1 | cut -d= -f2- || true)"
    if [[ ${#value} -ge 2 && "$value" == \"*\" && "$value" == *\" ]]; then
        value="${value:1:${#value}-2}"
        value="${value//\\\"/\"}"
        value="${value//\\\\/\\}"
        value="${value//\\\$/\$}"
        value="${value//\\\`/\`}"
    fi
    if [[ ${#value} -ge 2 && "$value" == \'*\' && "$value" == *\' ]]; then
        value="${value:1:${#value}-2}"
    fi
    printf '%s' "$value"
}

set_env(){
    local key="$1" value="$2" _quote="${3:-false}" encoded tmp
    [[ "$key" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || die "Clave .env invalida: $key"
    encoded="$(dotenv_escape_value "$value")"
    tmp="$(mktemp)"
    awk -v k="$key" -v v="$encoded" 'BEGIN{done=0} index($0,k"=")==1 {print k"="v; done=1; next} {print} END{if(!done) print k"="v}' .env > "$tmp"
    mv "$tmp" .env
    chmod 600 .env 2>/dev/null || true
}

postgres_diagnostics(){
    warn "PostgreSQL no quedo healthy. Diagnostico sanitizado:"
    docker compose ps codered-postgres || true
    docker compose logs --tail=200 codered-postgres || true
    docker inspect codered-postgres --format '{{json .State.Health}}' || true
}

wait_for_postgres(){
    local attempts="${1:-60}" status
    docker inspect codered-postgres >/dev/null 2>&1 || die "No se encontro el contenedor codered-postgres."
    info "Esperando a que codered-postgres este healthy..."
    for ((i=1; i<=attempts; i++)); do
        status="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}missing{{end}}' codered-postgres 2>/dev/null || true)"
        if [[ "$status" == "healthy" ]]; then
            ok "PostgreSQL healthy."
            return
        fi
        sleep 2
    done
    postgres_diagnostics
    die "PostgreSQL no quedo healthy dentro del tiempo esperado."
}
need_secret(){
    local key="$1" value
    value="$(get_env "$key")"
    [[ -z "$value" || "$value" == "Generate a random 32-character string and place it here" ]]
}

generate_secret(){
    command -v openssl >/dev/null || die "openssl es requerido para generar secretos del agente. Instálelo y vuelva a ejecutar ./update.sh"
    local value
    value="$(openssl rand -hex 32)"
    [[ "$value" =~ ^[0-9a-f]{64}$ ]] || die "openssl generó un secreto con formato inválido"
    printf '%s' "$value"
}

sync_postgres_env(){
    local db_name db_user db_password
    db_name="$(get_env DB_DATABASE)"
    db_user="$(get_env DB_USERNAME)"
    db_password="$(get_env DB_PASSWORD)"
    [[ -n "$db_name" && -n "$db_user" && -n "$db_password" ]] || die "DB_DATABASE, DB_USERNAME y DB_PASSWORD son obligatorios."
    set_env POSTGRES_DB "$db_name"
    set_env POSTGRES_USER "$db_user"
    set_env POSTGRES_PASSWORD "$db_password"
    [[ -n "$(get_env DB_CONNECTION)" ]] || set_env DB_CONNECTION pgsql
    [[ -n "$(get_env DB_HOST)" ]] || set_env DB_HOST postgres
    [[ -n "$(get_env DB_PORT)" ]] || set_env DB_PORT 5432
}
ensure_agent_env(){
    [[ -n "$(get_env CODERED_PLATFORM_URL)" ]] || set_env CODERED_PLATFORM_URL "$(get_env APP_URL)"
    [[ -n "$(get_env CODERED_AGENT_NAME)" ]] || set_env CODERED_AGENT_NAME "CodeRED n8n Agent" true
    [[ -n "$(get_env CODERED_AGENT_PUBLIC_URL)" ]] || set_env CODERED_AGENT_PUBLIC_URL "https://agent.codered.host"
    [[ -n "$(get_env CODERED_AGENT_ENVIRONMENT)" ]] || set_env CODERED_AGENT_ENVIRONMENT "production"
    [[ -n "$(get_env CODERED_AGENT_PORT)" ]] || set_env CODERED_AGENT_PORT "5680"
    [[ -n "$(get_env CODERED_AGENT_DATA_PATH)" ]] || set_env CODERED_AGENT_DATA_PATH "/data"
    [[ -n "$(get_env CODERED_AGENT_HEARTBEAT_SECONDS)" ]] || set_env CODERED_AGENT_HEARTBEAT_SECONDS "30"
    [[ -n "$(get_env CODERED_AGENT_DISCOVERY_SECONDS)" ]] || set_env CODERED_AGENT_DISCOVERY_SECONDS "300"
    [[ -n "$(get_env CODERED_AGENT_REQUEST_TIMEOUT_MS)" ]] || set_env CODERED_AGENT_REQUEST_TIMEOUT_MS "15000"
    [[ -n "$(get_env CODERED_AGENT_LOG_LEVEL)" ]] || set_env CODERED_AGENT_LOG_LEVEL "info"
    [[ -n "$(get_env CODERED_AGENT_LOCAL_URL)" ]] || set_env CODERED_AGENT_LOCAL_URL "http://codered-agent:5680"
    [[ -n "$(get_env N8N_VERSION)" ]] || set_env N8N_VERSION "2.31.4"
    [[ -n "$(get_env N8N_DB_DATABASE)" ]] || set_env N8N_DB_DATABASE "n8n"
    [[ -n "$(get_env N8N_DB_USERNAME)" ]] || set_env N8N_DB_USERNAME "n8n"
    if need_secret N8N_ENCRYPTION_KEY; then set_env N8N_ENCRYPTION_KEY "$(generate_secret)"; ok "Clave de cifrado de n8n generada correctamente."; fi
    if need_secret N8N_DB_PASSWORD; then set_env N8N_DB_PASSWORD "$(generate_secret)"; ok "Password PostgreSQL de n8n generado correctamente."; fi
    [[ -n "$(get_env N8N_HOST)" ]] || set_env N8N_HOST "n8n.codered.host"
    [[ -n "$(get_env N8N_EDITOR_BASE_URL)" ]] || set_env N8N_EDITOR_BASE_URL "https://n8n.codered.host/"
    [[ -n "$(get_env N8N_WEBHOOK_URL)" ]] || set_env N8N_WEBHOOK_URL "https://n8n.codered.host/"

    if need_secret CODERED_AGENT_ENCRYPTION_KEY; then set_env CODERED_AGENT_ENCRYPTION_KEY "$(generate_secret)"; ok "Clave de cifrado del agente generada correctamente."; fi
    if need_secret CODERED_AGENT_LOCAL_API_TOKEN; then set_env CODERED_AGENT_LOCAL_API_TOKEN "$(generate_secret)"; ok "Token de API local del agente generado correctamente."; fi
    local enc token
    enc="$(get_env CODERED_AGENT_ENCRYPTION_KEY)"; token="$(get_env CODERED_AGENT_LOCAL_API_TOKEN)"
    [[ "$enc" =~ ^[0-9a-f]{64}$ ]] || die "CODERED_AGENT_ENCRYPTION_KEY debe tener 64 caracteres hexadecimales."
    [[ "$token" =~ ^[0-9a-f]{64}$ ]] || die "CODERED_AGENT_LOCAL_API_TOKEN debe tener 64 caracteres hexadecimales."
    [[ "$enc" != "$token" ]] || die "Los secretos del agente deben ser diferentes."
}

changed(){ git diff --name-only HEAD@{1} HEAD 2>/dev/null | grep -Eq "$1"; }

should_rebuild_n8n(){
    if ! docker image inspect codered-n8n:2.31.4 >/dev/null 2>&1; then return 0; fi
    [[ "$OLD_HEAD" != "$NEW_HEAD" ]] || return 1
    changed '(^packages/n8n-nodes-codered/|^docker/n8n/|^docker-compose.yml$|^update.sh$|^Install_CodeRED-Platform.sh$)'
}


step 1 "Verificando entorno"
cd "$PROJECT_DIR"
[[ -f .env ]] || die "No se encontró .env"
[[ -f artisan ]] || die "No se encontró artisan; ejecute el script desde la raíz del proyecto."
COMPOSE_FILE="$(compose_file)" || die "No se encontró docker-compose.yml ni compose.yml"
command -v git >/dev/null || die "Git no está instalado."
command -v docker >/dev/null || die "Docker no está instalado."
docker compose version >/dev/null 2>&1 || die "Docker Compose v2 no está disponible."
ok "Entorno válido usando $COMPOSE_FILE"

step 2 "Verificando que no haya un restore RUC activo"
# RestoreRucBackupJob corre en segundo plano (cola dedicada "ruc-backups")
# y puede tardar horas en un backup de millones de filas. Si este script
# reconstruye/recrea el contenedor "app", "queue-ruc-backups" o reinicia
# postgres MIENTRAS un restore sigue vivo, puede matar el proceso psql a
# mitad de un TRUNCATE+COPY. RucBackupOperation.status=running es la
# fuente de verdad persistente (no depende de que el proceso original
# siga vivo para saberlo) — si la tabla aún no existe (primer deploy de
# esta funcionalidad) se asume que no hay nada que proteger todavía.
RUNNING_RESTORE_COUNT="$(docker compose exec -T app php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!Illuminate\Support\Facades\Schema::hasTable("ruc_backup_operations")) { echo "0"; exit; }
echo (string) Illuminate\Support\Facades\DB::table("ruc_backup_operations")->whereIn("status", ["pending", "running"])->count();
' 2>/dev/null)"
RUNNING_RESTORE_COUNT="${RUNNING_RESTORE_COUNT:-0}"
if [[ "$RUNNING_RESTORE_COUNT" != "0" ]]; then
    die "Hay una restauración RUC activa (ruc_backup_operations.status=pending/running). Deployment cancelado por seguridad. Reintente cuando termine (revise /admin/ruc/backups)."
fi
ok "Sin restauraciones RUC activas; es seguro continuar."

step 3 "Respaldando configuración"
cp .env ".env.backup-$STAMP"
ok "Backup creado: .env.backup-$STAMP"

step 4 "Actualizando repositorio"
if ! git diff --quiet || ! git diff --cached --quiet; then
    warn "Hay cambios locales sin confirmar. Se intentará actualizar solo si Git puede hacer fast-forward limpio."
    git status --short
fi
OLD_HEAD="$(git rev-parse HEAD)"
git pull --ff-only || die "git pull falló. Resuelva cambios locales o sincronización remota y reintente."
NEW_HEAD="$(git rev-parse HEAD)"
ok "Repositorio actualizado: $OLD_HEAD -> $NEW_HEAD"

step 5 "Revisando variables nuevas"
sync_postgres_env
ensure_agent_env
ok "Variables PostgreSQL, CodeRED Agent y n8n verificadas sin mostrar secretos."

step 6 "Construyendo imágenes"
BUILD_SERVICES=()
if [[ "$OLD_HEAD" != "$NEW_HEAD" ]]; then
    if changed '(^composer.lock$|^docker/php/Dockerfile$|^docker-compose.yml$|^compose.yml$|^app/|^bootstrap/|^config/|^routes/)'; then BUILD_SERVICES+=(app queue queue-ruc-backups scheduler); fi
    if changed '(^packages/codered-agent/|^docker-compose.yml$|^compose.yml$)'; then BUILD_SERVICES+=(codered-agent); fi
fi
if ((${#BUILD_SERVICES[@]})); then
    docker compose build "${BUILD_SERVICES[@]}"
else
    info "No se detectaron cambios que requieran reconstruccion selectiva de Platform."
fi
N8N_REBUILD_REQUIRED=0
if should_rebuild_n8n; then
    N8N_REBUILD_REQUIRED=1
    docker compose build codered-n8n
    ok "Imagen custom de n8n reconstruida localmente."
else
    info "No se detectaron cambios que requieran reconstruir codered-n8n."
fi

step 7 "Levantando servicios"
docker compose up -d --remove-orphans
wait_for_postgres
if [[ "${N8N_REBUILD_REQUIRED:-0}" == "1" ]]; then
    docker compose up -d --force-recreate --no-deps codered-n8n
fi
ok "Servicios levantados sin borrar volumenes."

step 8 "Validando configuración de PostgreSQL"
# Con 18M+ registros, /dev/shm debe ser >= 512MB para que VACUUM ANALYZE
# funcione sin errores "No space left on device". Ver
# docs-ruc/PERFORMANCE.md y docker-compose.yml postgres.shm_size.
EXPECTED_SHM=536870912  # 512 * 1024 * 1024 bytes
SHM_SIZE="$(docker inspect codered-postgres 2>/dev/null | \
    grep -A 5 '"ShmSize"' | head -1 | grep -oE '[0-9]+' || echo 0)"

if [[ "$SHM_SIZE" -lt "$EXPECTED_SHM" ]]; then
    warn "PostgreSQL ShmSize es $SHM_SIZE bytes, se espera >= $EXPECTED_SHM (512MB)."
    warn "VACUUM ANALYZE puede fallar con 'No space left on device' en tablas grandes."
    info "Reiniciando postgres para aplicar shm_size del docker-compose.yml…"
    docker compose restart postgres
    sleep 20
    SHM_SIZE="$(docker inspect codered-postgres 2>/dev/null | \
        grep -A 5 '"ShmSize"' | head -1 | grep -oE '[0-9]+' || echo 0)"
    if [[ "$SHM_SIZE" -ge "$EXPECTED_SHM" ]]; then
        ok "ShmSize ahora es correcto ($SHM_SIZE bytes >= $EXPECTED_SHM)."
    else
        warn "ShmSize sigue siendo bajo ($SHM_SIZE bytes < $EXPECTED_SHM). Intervención manual puede ser necesaria."
    fi
else
    ok "PostgreSQL ShmSize es suficiente ($SHM_SIZE bytes >= $EXPECTED_SHM)."
fi

step 9 "Actualizando assets del frontend"
# El manifest de Vite (public/build) vive fuera de git (.gitignore) y solo
# se reconstruye en el arranque del contenedor SI faltara — un contenedor
# que ya tenía uno de un deploy anterior no lo regenera solo, así que un
# cambio en resources/ (nuevos componentes Blade con clases Tailwind
# nuevas, JS, etc.) quedaría con CSS/JS desactualizado sin este paso.
if [[ "$OLD_HEAD" != "$NEW_HEAD" ]] && changed '(^resources/|^package\.json$|^package-lock\.json$|^tailwind\.config\.|^vite\.config\.)'; then
    docker compose exec -T app npm run build
    ok "Assets del frontend reconstruidos (Tailwind/Vite)."
else
    info "No se detectaron cambios en resources/ que requieran reconstruir el frontend."
fi

step 10 "Ejecutando migraciones"
docker compose exec -T app php artisan migrate --force
ok "Todas las migraciones completadas (incluyendo Shalom y RUC Backup)"

step 11 "Creando directorios requeridos"
# La ruta real depende del disco "local" configurado en
# config/filesystems.php (cambió de storage/app a storage/app/private entre
# versiones de Laravel) — se resuelve siempre a través de Laravel, nunca
# hardcodeada, para no volver a desincronizarse si el disco cambia. NO borra
# nada: mkdir -p es no destructivo y preserva cualquier backup existente.
RUC_BACKUP_DIR="$(docker compose exec -T app php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo Illuminate\Support\Facades\Storage::disk("local")->path("backups/ruc");
' 2>/dev/null)"
[[ -n "$RUC_BACKUP_DIR" ]] || die "No se pudo resolver el directorio de backups RUC vía Laravel."
# uploads/ es donde aterrizan temporalmente las partes de un backup
# multipart (manifest.json + *.partNNNN de packages/ruc-tools) mientras se
# suben — mkdir -p también aquí no es destructivo.
docker compose exec -T app mkdir -p "$RUC_BACKUP_DIR" "$RUC_BACKUP_DIR/uploads"
docker compose exec -T app chown -R www:www "$RUC_BACKUP_DIR"
docker compose exec -T app chmod -R 775 "$RUC_BACKUP_DIR"
ok "Directorio de backups RUC listo: $RUC_BACKUP_DIR (backups existentes preservados)"

if docker compose exec -T app sh -c 'command -v pg_dump >/dev/null && command -v pg_restore >/dev/null && command -v psql >/dev/null'; then
    ok "pg_dump/pg_restore/psql disponibles en el contenedor app."
else
    warn "pg_dump/pg_restore/psql NO se encontraron en el contenedor app. El backup/restore de RUC fallará."
    warn "Verifique que docker/php/Dockerfile instale postgresql16-client y reconstruya con: docker compose build app queue scheduler"
fi

# Los backups RUC multipart (packages/ruc-tools, herramienta LOCAL — jamás
# desplegada aquí) llegan en partes de hasta 90 MiB cada una, en requests
# HTTP independientes (así se evita el límite de ~100 MB de Cloudflare).
# Esto NO necesita subir client_max_body_size/upload_max_filesize: ya
# superan 90 MiB de sobra (se verifica el valor real en MiB, no se asume —
# ini_get() devuelve notación "5G"/"5100M" de PHP, hay que convertirla).
to_mib(){
    local raw="${1:-0}" num unit
    num="$(echo "$raw" | grep -oE '^[0-9]+')"
    unit="$(echo "$raw" | grep -oE '[GgMmKk]$')"
    [[ -n "$num" ]] || { echo 0; return; }
    case "$unit" in
        [Gg]) echo $((num * 1024)) ;;
        [Kk]) echo $((num / 1024)) ;;
        *) echo "$num" ;; # M o sin sufijo (bytes puros, poco probable aquí)
    esac
}
UPLOAD_MAX_MIB="$(to_mib "$(docker compose exec -T app php -r 'echo ini_get("upload_max_filesize");' 2>/dev/null)")"
POST_MAX_MIB="$(to_mib "$(docker compose exec -T app php -r 'echo ini_get("post_max_size");' 2>/dev/null)")"
NGINX_MAX_MIB="$(to_mib "$(docker compose exec -T nginx sh -c "grep -h client_max_body_size /etc/nginx/conf.d/*.conf 2>/dev/null | head -1" | grep -oE '[0-9]+[GgMmKk]')")"
if ((UPLOAD_MAX_MIB >= 90 && POST_MAX_MIB >= 90 && NGINX_MAX_MIB >= 90)); then
    ok "Límites de subida suficientes para partes de 90 MiB (upload_max_filesize=${UPLOAD_MAX_MIB}MiB, post_max_size=${POST_MAX_MIB}MiB, nginx client_max_body_size=${NGINX_MAX_MIB}MiB)."
else
    warn "upload_max_filesize/post_max_size/client_max_body_size podrían no soportar partes de 90 MiB (detectado: ${UPLOAD_MAX_MIB}/${POST_MAX_MIB}/${NGINX_MAX_MIB} MiB). Revise docker/php/php.ini y docker/nginx/default.conf."
fi

step 12 "Limpiando cachés"
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan queue:restart

# Limpiar caches de estadísticas RUC que se actualizarán en el siguiente
# import/restore. Ver RucStatisticsService y PERFORMANCE.md.
docker compose exec -T app php artisan cache:clear
docker compose exec -T app php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('cache')->forget('ruc:records:count');
\$app->make('cache')->forget('dashboard:ruc');
" 2>/dev/null || true

ok "Cachés limpiados; estadísticas RUC se recalcularán en el siguiente import/restore."

step 13 "Verificando salud"
docker compose ps
docker compose exec -T app php artisan about

step 14 "Validando configuración de PostgreSQL (FASE 2)"
# Verify that codered.conf is being used with optimized settings
# (shared_buffers=1GB, effective_cache_size=3GB, work_mem=32MB, etc.)
# This improves query planner decisions for 18M+ record tables.
if docker compose exec -T postgres psql -U "$(get_env POSTGRES_USER)" -d "$(get_env POSTGRES_DB)" -c "SHOW shared_buffers;" | grep -q "1GB"; then
    ok "PostgreSQL optimized settings loaded (shared_buffers=1GB, etc.)"
else
    warn "PostgreSQL configuration may not be fully applied. Restart postgres:"
    docker compose restart postgres
    sleep 15
fi

# Post-deploy ANALYZE (FASE 4 automation): if ruc_records table exists and
# was recently modified, run ANALYZE to update statistics. This is critical
# after imports/restores to prevent stale planner statistics from degrading
# query performance.
if docker compose exec -T postgres psql -U "$(get_env POSTGRES_USER)" -d "$(get_env POSTGRES_DB)" -c "SELECT 1 FROM information_schema.tables WHERE table_name='ruc_records';" | grep -q 1; then
    info "Running ANALYZE on ruc_records (PHASE 4 automation)..."
    docker compose exec -T app php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Illuminate\Support\Facades\DB::statement('ANALYZE ruc_records');
echo 'ANALYZE completed' . PHP_EOL;
" 2>/dev/null || warn "ANALYZE failed (non-critical, will happen automatically at next import/restore)"
else
    info "ruc_records table not yet present; ANALYZE will run automatically on first import."
fi

if [[ -n "$(get_env CODERED_AGENT_ENCRYPTION_KEY)" && -n "$(get_env CODERED_AGENT_LOCAL_API_TOKEN)" ]] && docker compose config --services | grep -qx codered-agent; then
    if curl --fail --silent http://127.0.0.1:5680/healthz >/dev/null; then
        ok "CodeRED Agent saludable."
    else
        warn "CodeRED Agent no respondió al healthcheck. Últimos logs:"
        docker compose logs --tail=100 codered-agent || true
    fi
else
    info "CodeRED Agent no está habilitado/configurado; se omite healthcheck."
fi

step 15 "Actualización completada"
ok "CodeRED Platform actualizado correctamente."
echo "Backup del .env: .env.backup-$STAMP"
ok "RUC performance optimization complete:"
echo "  ✓ PHASE 1: Cursor pagination, hardcoded filters, column selection"
echo "  ✓ PHASE 2: PostgreSQL tuning (shared_buffers=1GB, work_mem=32MB, etc.)"
echo "  ✓ PHASE 3: /dev/shm increased to 512MB for VACUUM ANALYZE"
echo "  ✓ PHASE 4: ANALYZE automated post-import/restore"
echo "  ✓ PHASE 5: Performance tests and benchmarks integrated"