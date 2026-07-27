#!/usr/bin/env bash
set -Eeuo pipefail

PROJECT_DIR="${PROJECT_DIR:-$PWD}"
STAMP="$(date +%Y%m%d-%H%M%S)"

ok(){ echo "[OK] $*"; }
info(){ echo "[INFO] $*"; }
warn(){ echo "[AVISO] $*"; }
die(){ echo "[ERROR] $*" >&2; exit 1; }
step(){ echo; echo "[$1/10] $2"; }

trap 'code=$?; echo "[ERROR] Fallo en la línea $LINENO" >&2; echo "[ERROR] Comando: ${BASH_COMMAND}" >&2; echo "[ERROR] Código de salida: $code" >&2; echo "[INFO] Siguiente paso recomendado: revise el mensaje anterior, restaure .env desde .env.backup-* si el cambio fue de configuración y vuelva a ejecutar ./update.sh" >&2; exit $code' ERR

compose_file(){
    if [[ -f docker-compose.yml ]]; then echo docker-compose.yml; return; fi
    if [[ -f compose.yml ]]; then echo compose.yml; return; fi
    return 1
}

get_env(){
    local key="$1"
    grep -E "^${key}=" .env 2>/dev/null | head -n1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/' || true
}

set_env(){
    local key="$1" value="$2" quote="${3:-false}" tmp
    [[ "$value" == *$'\n'* || "$value" == *$'\r'* ]] && die "Valor inválido para $key"
    if [[ "$quote" == "true" ]]; then
        value="${value//\\/\\\\}"; value="${value//\"/\\\"}"; value="\"${value}\""
    fi
    tmp="$(mktemp)"
    awk -v k="$key" -v v="$value" 'BEGIN{done=0} index($0,k"=")==1 {print k"="v; done=1; next} {print} END{if(!done) print k"="v}' .env > "$tmp"
    mv "$tmp" .env
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

ensure_agent_env(){
    [[ -n "$(get_env CODERED_AGENT_NAME)" ]] || set_env CODERED_AGENT_NAME "CodeRED n8n Agent" true
    [[ -n "$(get_env CODERED_AGENT_PUBLIC_URL)" ]] || set_env CODERED_AGENT_PUBLIC_URL "https://agent.codered.host"
    [[ -n "$(get_env CODERED_AGENT_ENVIRONMENT)" ]] || set_env CODERED_AGENT_ENVIRONMENT "production"
    [[ -n "$(get_env CODERED_AGENT_PORT)" ]] || set_env CODERED_AGENT_PORT "5680"
    [[ -n "$(get_env CODERED_AGENT_DATA_PATH)" ]] || set_env CODERED_AGENT_DATA_PATH "/data"
    [[ -n "$(get_env CODERED_AGENT_HEARTBEAT_SECONDS)" ]] || set_env CODERED_AGENT_HEARTBEAT_SECONDS "30"
    [[ -n "$(get_env CODERED_AGENT_DISCOVERY_SECONDS)" ]] || set_env CODERED_AGENT_DISCOVERY_SECONDS "300"
    [[ -n "$(get_env CODERED_AGENT_REQUEST_TIMEOUT_MS)" ]] || set_env CODERED_AGENT_REQUEST_TIMEOUT_MS "15000"
    [[ -n "$(get_env CODERED_AGENT_LOG_LEVEL)" ]] || set_env CODERED_AGENT_LOG_LEVEL "info"

    if need_secret CODERED_AGENT_ENCRYPTION_KEY; then set_env CODERED_AGENT_ENCRYPTION_KEY "$(generate_secret)"; ok "Clave de cifrado del agente generada correctamente."; fi
    if need_secret CODERED_AGENT_LOCAL_API_TOKEN; then set_env CODERED_AGENT_LOCAL_API_TOKEN "$(generate_secret)"; ok "Token de API local del agente generado correctamente."; fi
    local enc token
    enc="$(get_env CODERED_AGENT_ENCRYPTION_KEY)"; token="$(get_env CODERED_AGENT_LOCAL_API_TOKEN)"
    [[ "$enc" =~ ^[0-9a-f]{64}$ ]] || die "CODERED_AGENT_ENCRYPTION_KEY debe tener 64 caracteres hexadecimales."
    [[ "$token" =~ ^[0-9a-f]{64}$ ]] || die "CODERED_AGENT_LOCAL_API_TOKEN debe tener 64 caracteres hexadecimales."
    [[ "$enc" != "$token" ]] || die "Los secretos del agente deben ser diferentes."
}

changed(){ git diff --name-only HEAD@{1} HEAD 2>/dev/null | grep -Eq "$1"; }

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
ensure_agent_env
ok "Variables de CodeRED Agent verificadas sin mostrar secretos."

step 5 "Construyendo imágenes"
BUILD_SERVICES=()
if [[ "$OLD_HEAD" != "$NEW_HEAD" ]]; then
    if changed '(^composer.lock$|^docker/php/Dockerfile$|^docker-compose.yml$|^compose.yml$|^app/|^bootstrap/|^config/|^routes/)'; then BUILD_SERVICES+=(app queue scheduler); fi
    if changed '(^packages/codered-agent/|^docker-compose.yml$|^compose.yml$)'; then BUILD_SERVICES+=(codered-agent); fi
fi
if ((${#BUILD_SERVICES[@]})); then
    docker compose build "${BUILD_SERVICES[@]}"
else
    info "No se detectaron cambios que requieran reconstrucción selectiva."
fi

step 6 "Levantando servicios"
docker compose up -d --remove-orphans
ok "Servicios levantados sin borrar volúmenes."

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
    if curl --fail --silent http://127.0.0.1:5680/v1/health >/dev/null; then
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