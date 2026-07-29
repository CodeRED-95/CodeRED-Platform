#!/usr/bin/env bash
set -Eeuo pipefail
export LANG="${LANG:-C.UTF-8}"
export LC_ALL="${LC_ALL:-C.UTF-8}"

PROJECT_DIR="${PROJECT_DIR:-$PWD}"
STAMP="$(date +%Y%m%d-%H%M%S)"

ok(){ echo "[OK] $*"; }
info(){ echo "[INFO] $*"; }
warn(){ echo "[WARN] $*"; }
die(){ echo "[ERROR] $*" >&2; exit 1; }
step(){ echo; echo "[$1/10] $2"; }

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

step 2 "Respaldando configuración"
cp .env ".env.backup-$STAMP"
ok "Backup creado: .env.backup-$STAMP"

step 3 "Actualizando repositorio"
if ! git diff --quiet || ! git diff --cached --quiet; then
    warn "Hay cambios locales sin confirmar. Se intentará actualizar solo si Git puede hacer fast-forward limpio."
    git status --short
fi
OLD_HEAD="$(git rev-parse HEAD)"
git pull --ff-only || die "git pull falló. Resuelva cambios locales o sincronización remota y reintente."
NEW_HEAD="$(git rev-parse HEAD)"
ok "Repositorio actualizado: $OLD_HEAD -> $NEW_HEAD"

step 4 "Revisando variables nuevas"
sync_postgres_env
ensure_agent_env
ok "Variables PostgreSQL, CodeRED Agent y n8n verificadas sin mostrar secretos."

step 5 "Construyendo imágenes"
BUILD_SERVICES=()
if [[ "$OLD_HEAD" != "$NEW_HEAD" ]]; then
    if changed '(^composer.lock$|^docker/php/Dockerfile$|^docker-compose.yml$|^compose.yml$|^app/|^bootstrap/|^config/|^routes/)'; then BUILD_SERVICES+=(app queue scheduler); fi
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

step 6 "Levantando servicios"
docker compose up -d --remove-orphans
wait_for_postgres
if [[ "${N8N_REBUILD_REQUIRED:-0}" == "1" ]]; then
    docker compose up -d --force-recreate --no-deps codered-n8n
fi
ok "Servicios levantados sin borrar volumenes."

step 7 "Ejecutando migraciones"
docker compose exec -T app php artisan migrate --force

step 8 "Limpiando cachés"
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan queue:restart

step 9 "Verificando salud"
docker compose ps
docker compose exec -T app php artisan about
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

step 10 "Actualización completada"
ok "CodeRED Platform actualizado correctamente."
echo "Backup del .env: .env.backup-$STAMP"