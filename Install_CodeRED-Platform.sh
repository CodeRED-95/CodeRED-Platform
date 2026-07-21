#!/usr/bin/env bash
set -Eeuo pipefail

REPO_URL="https://github.com/CodeRED-95/CodeRED-Platform.git"
PROJECT_DIR="${PROJECT_DIR:-$HOME/CodeRED-Platform}"
ENV_FILE="$PROJECT_DIR/.env"

ok(){ echo "[OK] $*"; }
info(){ echo "[INFO] $*"; }
warn(){ echo "[AVISO] $*"; }
die(){ echo "[ERROR] $*" >&2; exit 1; }

trap 'echo "[ERROR] Fallo en la línea $LINENO" >&2' ERR

confirm() {
    local q="$1" d="${2:-n}" a
    while true; do
        if [[ "$d" == "s" ]]; then
            read -r -p "$q [S/n]: " a
            a="${a:-s}"
        else
            read -r -p "$q [s/N]: " a
            a="${a:-n}"
        fi
        case "${a,,}" in
            s|si|sí|y|yes) return 0 ;;
            n|no) return 1 ;;
            *) warn "Responde s o n." ;;
        esac
    done
}

read_value() {
    local q="$1" def="${2:-}" req="${3:-false}" v
    while true; do
        if [[ -n "$def" ]]; then
            read -r -p "$q [$def]: " v
            v="${v:-$def}"
        else
            read -r -p "$q: " v
        fi
        if [[ "$req" == "true" && -z "$v" ]]; then
            warn "Este campo es obligatorio."
            continue
        fi
        REPLY="$v"
        return
    done
}

read_password() {
    local q="$1" a b
    while true; do
        read -r -s -p "$q: " a; echo
        read -r -s -p "Confirmar: " b; echo
        [[ -z "$a" ]] && { warn "La contraseña es obligatoria."; continue; }
        [[ "$a" != "$b" ]] && { warn "Las contraseñas no coinciden."; continue; }
        (( ${#a} < 12 )) && { warn "Usa al menos 12 caracteres."; continue; }
        [[ "$a" == *$'\n'* || "$a" == *$'\r'* ]] && { warn "No se permiten saltos de línea."; continue; }
        [[ "$a" == *[[:space:]]* || "$a" == *"#"* || "$a" == *"="* || "$a" == *"\""* || "$a" == *"'"* ]] && { warn "La contraseña contiene caracteres incompatibles con el .env. No uses espacios, comillas, # ni =."; continue; }
        REPLY="$a"
        return
    done
}

set_env() {
    local key="$1" value="$2" quote="${3:-false}" tmp
    [[ "$value" == *$'\n'* || "$value" == *$'\r'* ]] && { echo "[ERROR] El valor de $key contiene saltos de línea." >&2; return 1; }
    if [[ "$quote" == "true" ]]; then
        value="${value//\\/\\\\}"
        value="${value//\"/\\\"}"
        value="\"${value}\""
    fi
    tmp="$(mktemp)"
    awk -v k="$key" -v v="$value" '
        BEGIN{done=0}
        index($0,k"=")==1 {print k"="v; done=1; next}
        {print}
        END{if(!done) print k"="v}
    ' "$ENV_FILE" > "$tmp"
    mv "$tmp" "$ENV_FILE"
}

validate_env_file() {
    local invalid
    invalid="$(awk '/\r$/ {print NR ": retorno CR"; next} /^[[:space:]]*($|#)/ {next} !/^[A-Za-z_][A-Za-z0-9_]*=/ {print NR ": clave inválida"; next} {key=$0; sub(/=.*/, "", key); value=substr($0,index($0,"=")+1); if ((key=="DB_PASSWORD" || key=="DEV_ADMIN_PASSWORD" || key ~ /(_API_KEY|_TOKEN)$/) && value ~ /^"/) {print key; next} if (value ~ /^"([^"\\]|\\.)*"$/) next; if (value ~ /[[:space:]]/) print key}' "$ENV_FILE")"
    if [[ -n "$invalid" ]]; then
        while IFS= read -r key; do [[ -n "$key" ]] && echo "[ERROR] El archivo .env contiene un valor inválido en $key" >&2; done <<< "$invalid"
        return 1
    fi
    ok "Archivo .env válido"
}

get_env() {
    grep -E "^$1=" "$ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- || true
}

echo "============================================================"
echo "        Instalador de CodeRED Platform"
echo "============================================================"

command -v git >/dev/null || die "Git no está instalado."
command -v docker >/dev/null || die "Docker no está instalado."
docker compose version >/dev/null 2>&1 || die "Docker Compose v2 no está disponible."
docker info >/dev/null 2>&1 || die "Docker no está iniciado o faltan permisos."

if [[ -e "$PROJECT_DIR" ]]; then
    die "Ya existe $PROJECT_DIR. Renómbralo o elimínalo antes de instalar."
fi

info "Clonando repositorio..."
git clone --depth=1 "$REPO_URL" "$PROJECT_DIR"
cd "$PROJECT_DIR"

[[ -f .env.example ]] || die "No se encontró .env.example."
cp .env.example .env
ok "Archivo .env creado."

echo
echo "1) Producción"
echo "2) Desarrollo"
read -r -p "Modo [1]: " mode
mode="${mode:-1}"

if [[ "$mode" == "2" ]]; then
    APP_ENV="local"
    APP_DEBUG="true"
    LOG_LEVEL="debug"
    DEFAULT_URL="http://192.168.18.124:8090"
else
    APP_ENV="production"
    APP_DEBUG="false"
    LOG_LEVEL="info"
    DEFAULT_URL="https://platform.codered.host"
fi

read_value "URL principal" "$DEFAULT_URL" true
APP_URL="${REPLY%/}"

read_value "Nombre de la base de datos" "$(get_env DB_DATABASE)" true
DB_DATABASE="$REPLY"
read_value "Usuario de la base de datos" "$(get_env DB_USERNAME)" true
DB_USERNAME="$REPLY"
read_password "Contraseña de PostgreSQL"
DB_PASSWORD="$REPLY"

read_value "Nombre del administrador" "Administrador CodeRED" true
ADMIN_NAME="$REPLY"
read_value "Correo del administrador" "admin@codered.host" true
ADMIN_EMAIL="$REPLY"
read_password "Contraseña del administrador"
ADMIN_PASSWORD="$REPLY"

set_env APP_NAME "CodeRED Platform" true
set_env VITE_APP_NAME "CodeRED Platform" true
set_env APP_ENV "$APP_ENV"
set_env APP_DEBUG "$APP_DEBUG"
set_env APP_URL "$APP_URL"
set_env LOG_LEVEL "$LOG_LEVEL"
set_env DB_DATABASE "$DB_DATABASE"
set_env DB_USERNAME "$DB_USERNAME"
set_env DB_PASSWORD "$DB_PASSWORD"
set_env DEV_ADMIN_NAME "$ADMIN_NAME" true
set_env DEV_ADMIN_EMAIL "$ADMIN_EMAIL"
set_env DEV_ADMIN_PASSWORD "$ADMIN_PASSWORD"

set_env QUEUE_CONNECTION "redis"
set_env REDIS_QUEUE_RETRY_AFTER "7500"
set_env RUC_ENABLED "true"
set_env RUC_IMPORT_QUEUE "ruc-imports"
set_env RUC_IMPORT_CHUNK_SIZE "5000"
set_env RUC_IMPORT_TIMEOUT "7200"
set_env RUC_IMPORT_LOCK_SECONDS "21600"
set_env RUC_IMPORT_ENCODING "ISO-8859-1"
set_env RUC_IMPORT_DELIMITER "|"
set_env RUC_IMPORT_MAX_SIZE_MB "5000"

if [[ "$APP_URL" == https://*.codered.host ]]; then
    set_env SESSION_DOMAIN ".codered.host"
else
    set_env SESSION_DOMAIN "null"
fi

set_env SANCTUM_STATEFUL_DOMAINS "platform.codered.host,localhost:8090,127.0.0.1:8090,192.168.18.124:8090,chrome-extension://jpfcfljmbaijaajjdhblinjgblnfpign"
set_env API_ALLOWED_ORIGINS "https://platform.codered.host,http://192.168.18.124:8090,http://localhost:8090,chrome-extension://jpfcfljmbaijaajjdhblinjgblnfpign"

if confirm "¿Activar PeruDevs para consultas DNI?" n; then
    read_value "URL PeruDevs" "https://api.perudevs.com/api/v1/dni/complete" true
    set_env DNI_PERUDEVS_BASE_URL "${REPLY%/}"
    read_password "Token/API key PeruDevs"
    set_env DNI_PERUDEVS_API_KEY "$REPLY"
    set_env DNI_PERUDEVS_ENABLED "true"
else
    set_env DNI_PERUDEVS_ENABLED "false"
    set_env DNI_PERUDEVS_API_KEY ""
fi

validate_env_file || die "Corrige las claves indicadas antes de continuar."

unset DB_PASSWORD ADMIN_PASSWORD REPLY || true

info "Construyendo e iniciando contenedores..."
docker compose up -d --build

info "Esperando Laravel..."
for _ in {1..40}; do
    if docker compose exec -T app php artisan about >/dev/null 2>&1; then
        break
    fi
    sleep 3
done
docker compose exec -T app php artisan about >/dev/null 2>&1 || die "Laravel no respondió a tiempo."

if [[ -z "$(get_env APP_KEY)" ]]; then
    docker compose exec -T app php artisan key:generate --force
fi

docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan db:seed --force
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan storage:link >/dev/null 2>&1 || true

echo
info "Verificando servicios sin reiniciarlos..."
for service in app nginx postgres redis queue scheduler; do
    if docker compose ps --status running --services | grep -qx "$service"; then
        ok "$service activo"
    else
        warn "$service todavía no aparece activo"
    fi
done

echo
echo "============================================================"
echo " CodeRED Platform instalada correctamente"
echo "============================================================"
echo "URL: $APP_URL"
echo "Administrador: $ADMIN_EMAIL"
echo "Directorio: $PROJECT_DIR"
echo
docker compose ps
