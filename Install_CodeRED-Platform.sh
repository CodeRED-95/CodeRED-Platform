#!/usr/bin/env bash
# CodeRED Platform Installer
# Uso: ./Install_CodeRED-Platform.sh [directorio_destino]
#
# Ejemplos:
#   ./Install_CodeRED-Platform.sh                    # Usa ~/CodeRED-Platform
#   ./Install_CodeRED-Platform.sh /opt/CodeRED       # Usa /opt/CodeRED
#   PROJECT_DIR=/data/codered ./Install_CodeRED-Platform.sh

set -Eeuo pipefail
export LANG="${LANG:-C.UTF-8}"
export LC_ALL="${LC_ALL:-C.UTF-8}"

REPO_URL="https://github.com/CodeRED-95/CodeRED-Platform.git"

# Si se proporciona argumento, usarlo; si no, usar variable de entorno; si no, usar default
if [[ $# -gt 0 ]]; then
    PROJECT_DIR="$(cd "$(dirname "$1")" && pwd -P)/$(basename "$1")"
else
    PROJECT_DIR="${PROJECT_DIR:-$HOME/CodeRED-Platform}"
fi

ENV_FILE="$PROJECT_DIR/.env"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd -P)"
STAMP="$(date +%Y%m%d-%H%M%S)"
CLONE_REPO=false

ok(){ echo "[OK] $*"; }
info(){ echo "[INFO] $*"; }
warn(){ echo "[AVISO] $*"; }
die(){ echo "[ERROR] $*" >&2; exit 1; }

trap 'code=$?; echo "[ERROR] Fallo en la línea $LINENO" >&2; echo "[ERROR] Comando: ${BASH_COMMAND}" >&2; echo "[ERROR] Código de salida: $code" >&2; echo "[INFO] Revise el mensaje anterior. Si se modificó .env, restaure el backup .env.backup-* y reintente." >&2; exit $code' ERR

# ---------------------------------------------------------------------------
# DOMINIO DE LA PLATAFORMA
# ---------------------------------------------------------------------------
# Dominio productivo actual: codered.lat (migrado desde codered.host).
# Para una futura migración de dominio basta cambiar CODERED_DOMAIN aquí (o
# exportarlo antes de ejecutar el script); todos los defaults se derivan de él.
# CODERED_LEGACY_DOMAINS se mantiene aceptado en CORS/Sanctum para no cortar
# clientes ya desplegados (extensión Chrome, integraciones n8n) durante la
# transición. Vaciarlo cuando el dominio nuevo esté validado al 100%.
CODERED_DOMAIN="${CODERED_DOMAIN:-codered.lat}"
CODERED_LEGACY_DOMAINS="${CODERED_LEGACY_DOMAINS:-codered.host}"

# Extrae el host (sin esquema, sin puerto, sin path) de una URL.
url_host(){
    local url="${1:-}"
    url="${url#*://}"
    url="${url%%/*}"
    url="${url%%\?*}"
    url="${url##*@}"
    printf '%s' "${url%%:*}" | tr '[:upper:]' '[:lower:]'
}

# Dominio de cookie derivado de una URL: ".ejemplo.com" para
# https://sub.ejemplo.com, y "null" para localhost, IPs o dominios sin
# subdominio (donde un Domain= explícito solo rompería la cookie).
cookie_domain_for_url(){
    local host labels
    host="$(url_host "${1:-}")"
    [[ -n "$host" ]] || { printf 'null'; return; }
    # IPv4 literal o host sin punto (localhost) -> sin atributo Domain
    if [[ "$host" =~ ^[0-9.]+$ || "$host" != *.* ]]; then printf 'null'; return; fi
    labels="$(awk -F. '{print NF}' <<<"$host")"
    if (( labels < 3 )); then printf 'null'; return; fi
    printf '.%s' "$(awk -F. '{print $(NF-1)"."$NF}' <<<"$host")"
}

confirm() {
    local q="$1" d="${2:-n}" a
    while true; do
        if [[ "$d" == "s" ]]; then read -r -p "$q [S/n]: " a; a="${a:-s}"; else read -r -p "$q [s/N]: " a; a="${a:-n}"; fi
        case "${a,,}" in s|si|sí|y|yes) return 0 ;; n|no) return 1 ;; *) warn "Responde s o n." ;; esac
    done
}

read_value() {
    local q="$1" def="${2:-}" req="${3:-false}" v
    while true; do
        if [[ -n "$def" ]]; then read -r -p "$q [$def]: " v; v="${v:-$def}"; else read -r -p "$q: " v; fi
        if [[ "$req" == "true" && -z "$v" ]]; then warn "Este campo es obligatorio."; continue; fi
        REPLY="$v"; return
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
        REPLY="$(normalize_env_secret "$a")"; return
    done
}

dotenv_escape_value() {
    local value="$1"
    [[ "$value" != *$'\n'* && "$value" != *$'\r'* ]] || { echo "[ERROR] El valor contiene saltos de linea." >&2; return 1; }
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

set_env() {
    local key="$1" value="$2" _quote="${3:-false}" encoded tmp
    [[ "$key" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || { echo "[ERROR] Clave .env invalida: $key" >&2; return 1; }
    encoded="$(dotenv_escape_value "$value")" || return 1
    tmp="$(mktemp)"
    awk -v k="$key" -v v="$encoded" 'BEGIN{done=0} index($0,k"=")==1 {if(!done){print k"="v; done=1}; next} {print} END{if(!done) print k"="v}' "$ENV_FILE" > "$tmp"
    mv "$tmp" "$ENV_FILE"
    chmod 600 "$ENV_FILE" 2>/dev/null || true
}

get_env() {
    local key="$1" value
    value="$(grep -E "^${key}=" "$ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- || true)"
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

sync_postgres_env() {
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

generate_secret(){
    command -v openssl >/dev/null || die "openssl es requerido para generar secretos de CodeRED Agent."
    local value
    value="$(openssl rand -hex 32)"
    [[ "$value" =~ ^[0-9a-f]{64}$ ]] || die "openssl generó un secreto con formato inválido."
    printf '%s' "$value"
}

configure_agent(){
    if ! confirm "¿Desea habilitar CodeRED Agent?" s; then
        info "CodeRED Agent quedará sin secretos configurados. Puede habilitarlo luego con ./CodeRED.sh o ./update.sh."
        return
    fi
    read_value "Nombre del agente" "$(get_env CODERED_AGENT_NAME || true)" false; AGENT_NAME="${REPLY:-CodeRED n8n Agent}"
    read_value "URL pública del agente" "$(get_env CODERED_AGENT_PUBLIC_URL || true)" false; AGENT_URL="${REPLY:-https://agent.$CODERED_DOMAIN}"
    read_value "Entorno" "$(get_env CODERED_AGENT_ENVIRONMENT || true)" false; AGENT_ENV="${REPLY:-production}"
    read_value "Puerto" "$(get_env CODERED_AGENT_PORT || true)" false; AGENT_PORT="${REPLY:-5680}"
    read_value "Data path" "$(get_env CODERED_AGENT_DATA_PATH || true)" false; AGENT_DATA="${REPLY:-/data}"
    read_value "Heartbeat en segundos" "$(get_env CODERED_AGENT_HEARTBEAT_SECONDS || true)" false; AGENT_HEARTBEAT="${REPLY:-30}"
    read_value "Discovery en segundos" "$(get_env CODERED_AGENT_DISCOVERY_SECONDS || true)" false; AGENT_DISCOVERY="${REPLY:-300}"
    read_value "Timeout HTTP en ms" "$(get_env CODERED_AGENT_REQUEST_TIMEOUT_MS || true)" false; AGENT_TIMEOUT="${REPLY:-15000}"
    read_value "Log level" "$(get_env CODERED_AGENT_LOG_LEVEL || true)" false; AGENT_LOG="${REPLY:-info}"

    set_env CODERED_AGENT_NAME "$AGENT_NAME" true
    set_env CODERED_AGENT_PUBLIC_URL "${AGENT_URL%/}"
    set_env CODERED_AGENT_ENVIRONMENT "$AGENT_ENV"
    set_env CODERED_AGENT_PORT "$AGENT_PORT"
    set_env CODERED_AGENT_DATA_PATH "$AGENT_DATA"
    set_env CODERED_AGENT_HEARTBEAT_SECONDS "$AGENT_HEARTBEAT"
    set_env CODERED_AGENT_DISCOVERY_SECONDS "$AGENT_DISCOVERY"
    set_env CODERED_AGENT_REQUEST_TIMEOUT_MS "$AGENT_TIMEOUT"
    set_env CODERED_AGENT_LOG_LEVEL "$AGENT_LOG"

    local enc token
    enc="$(get_env CODERED_AGENT_ENCRYPTION_KEY)"
    token="$(get_env CODERED_AGENT_LOCAL_API_TOKEN)"
    if [[ -n "$enc" && ! "$enc" =~ ^[0-9a-f]{64}$ ]]; then warn "La clave de cifrado existente tiene formato inválido y no se sobrescribirá sin confirmación."; if confirm "¿Regenerar clave de cifrado?" n; then enc=""; fi; fi
    if [[ -n "$token" && ! "$token" =~ ^[0-9a-f]{64}$ ]]; then warn "El token local existente tiene formato inválido y no se sobrescribirá sin confirmación."; if confirm "¿Regenerar token local?" n; then token=""; fi; fi
    if [[ -z "$enc" ]]; then enc="$(generate_secret)"; set_env CODERED_AGENT_ENCRYPTION_KEY "$enc"; ok "Clave de cifrado: generada correctamente"; else ok "Clave de cifrado: preservada"; fi
    if [[ -z "$token" ]]; then token="$(generate_secret)"; set_env CODERED_AGENT_LOCAL_API_TOKEN "$token"; ok "Token de API local: generado correctamente"; else ok "Token de API local: preservado"; fi
    [[ "$(get_env CODERED_AGENT_ENCRYPTION_KEY)" != "$(get_env CODERED_AGENT_LOCAL_API_TOKEN)" ]] || die "Los secretos del agente deben ser diferentes."
}

ask_yes_no() {
    local q="$1" d="${2:-s}" a
    while true; do
        if [[ "$d" == "s" ]]; then read -r -p "$q [S/n]: " a; a="${a:-s}"; else read -r -p "$q [s/N]: " a; a="${a:-n}"; fi
        case "${a,,}" in s|si|sí|y|yes) return 0 ;; n|no) return 1 ;; *) warn "Responde s o n." ;; esac
    done
}

prompt_secret_with_confirmation() {
    local prompt="$1" first second normalized_first normalized_second
    while true; do
        read -r -s -p "$prompt: " first; echo
        read -r -s -p "Confirme la contraseña para PostgreSQL n8n: " second; echo
        normalized_first="$(normalize_env_secret "$first")"
        normalized_second="$(normalize_env_secret "$second")"
        validate_n8n_db_password "$normalized_first" || { warn "La contraseña no puede estar vacía ni contener saltos de línea."; continue; }
        [[ "$normalized_first" != "$normalized_second" ]] && { warn "Las contraseñas no coinciden."; continue; }
        REPLY="$normalized_first"
        return
    done
}

postgres_diagnostics() {
    warn "PostgreSQL no quedo healthy. Diagnostico sanitizado:"
    docker compose ps codered-postgres || true
    docker compose logs --tail=200 codered-postgres || true
    docker inspect codered-postgres --format '{{json .State.Health}}' || true
}

wait_for_postgres() {
    local attempts="${1:-60}" status
    command -v docker >/dev/null || die "Docker no esta disponible."
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

postgres_role_exists() {
    local db_user="$1"
    [[ "$(docker exec codered-postgres psql -U "$db_user" -d postgres -Atqc "SELECT 1 FROM pg_roles WHERE rolname = 'n8n';" 2>/dev/null | tr -d '[:space:]')" == "1" ]]
}

postgres_database_exists() {
    local db_user="$1"
    [[ "$(docker exec codered-postgres psql -U "$db_user" -d postgres -Atqc "SELECT 1 FROM pg_database WHERE datname = 'n8n';" 2>/dev/null | tr -d '[:space:]')" == "1" ]]
}

normalize_env_secret() {
    local value="$1"
    if [[ ${#value} -ge 2 ]]; then
        if [[ "$value" == \"*\" && "$value" == *\" ]]; then
            value="${value:1:${#value}-2}"
        elif [[ "$value" == \'*\' && "$value" == *\' ]]; then
            value="${value:1:${#value}-2}"
        fi
    fi
    printf '%s' "$value"
}

validate_n8n_db_password() {
    local value="$1"
    [[ -n "$value" ]] || return 1
    [[ "$value" != *$'\n'* && "$value" != *$'\r'* ]] || return 1
}

set_env_value_raw() {
    local file="$1" key="$2" value="$3" tmp backup
    [[ "$value" != *$'\n'* && "$value" != *$'\r'* ]] || die "Valor inválido para $key."
    mkdir -p "$(dirname "$file")"
    touch "$file"
    chmod 600 "$file" 2>/dev/null || true
    backup="${file}.backup-${STAMP}"
    cp "$file" "$backup"
    chmod 600 "$backup" 2>/dev/null || true
    tmp="$(mktemp)"
    awk -v k="$key" -v v="$value" '
        BEGIN{done=0}
        index($0,k"=")==1 {if(!done){print k"="v; done=1}; next}
        {print}
        END{if(!done) print k"="v}
    ' "$file" > "$tmp"
    mv "$tmp" "$file"
    chmod 600 "$file" 2>/dev/null || true
}


secret_hash() {
    if command -v sha256sum >/dev/null 2>&1; then
        printf '%s' "$1" | sha256sum | awk '{print $1}'
    else
        printf '%s' "$1" | openssl dgst -sha256 -r | awk '{print $1}'
    fi
}

validate_n8n_env_password_value() {
    local file="$1" expected="$2" actual expected_hash actual_hash count
    count="$(grep -c '^DB_POSTGRESDB_PASSWORD=' "$file" 2>/dev/null || true)"
    [[ "$count" == "1" ]] || die "El archivo de n8n no contiene exactamente una línea DB_POSTGRESDB_PASSWORD."
    actual="$(grep '^DB_POSTGRESDB_PASSWORD=' "$file" | head -n1 | cut -d= -f2-)"
    expected_hash="$(secret_hash "$expected")"
    actual_hash="$(secret_hash "$actual")"
    [[ "$expected_hash" == "$actual_hash" ]] || die "El valor escrito para DB_POSTGRESDB_PASSWORD no coincide con el valor normalizado."
    ok "Valor DB_POSTGRESDB_PASSWORD validado sin mostrar secretos."
}

validate_n8n_compose_config() {
    (cd "$PROJECT_DIR" && docker compose config >/dev/null) || die "docker compose config fallo."
    ok "docker compose config validado sin imprimir secretos."
}


verify_n8n_schema_objects() {
    local password="$1" result
    result="$(docker exec -e PGPASSWORD="$password" codered-postgres psql -h 127.0.0.1 -U n8n -d n8n -Atqc "SELECT coalesce(to_regclass('public.migrations')::text,''), coalesce(to_regclass('public.workflow_entity')::text,''), coalesce(to_regclass('public.credentials_entity')::text,''), coalesce(to_regclass('public.execution_entity')::text,''), coalesce(to_regclass('public.workflow_statistics_delta')::text,'');" 2>/dev/null || true)"
    if [[ "$result" == *"workflow_entity"* && "$result" == *"credentials_entity"* ]]; then
        ok "Tablas principales de n8n detectadas."
    else
        warn "Las tablas de n8n todavía no aparecen; n8n puede crearlas al terminar su arranque inicial."
    fi
}
recreate_n8n_service() {
    (cd "$PROJECT_DIR" && docker compose build codered-n8n) || die "No se pudo construir la imagen local codered-n8n:2.31.4."
    (cd "$PROJECT_DIR" && docker compose up -d --force-recreate --no-deps codered-n8n) || die "No se pudo recrear codered-n8n."
    (cd "$PROJECT_DIR" && docker compose ps codered-n8n)
    if docker logs --since 5m codered-n8n 2>&1 | grep -F 'password authentication failed for user "n8n"' >/dev/null; then
        die "codered-n8n reporto fallo de autenticacion PostgreSQL para n8n."
    fi
    ok "codered-n8n recreado desde el compose principal."
}
ensure_n8n_env_defaults() {
    local password
    password="$(get_env N8N_DB_PASSWORD)"
    if [[ -z "$password" ]]; then
        password="$(generate_secret)"
        set_env N8N_DB_PASSWORD "$password"
        ok "Password PostgreSQL de n8n generado correctamente."
    fi
    configure_n8n_env "$password"
}

configure_n8n_env() {
    local password="$1"
    password="$(normalize_env_secret "$password")"
    validate_n8n_db_password "$password" || die "La contrasena PostgreSQL n8n es invalida."
    set_env N8N_DB_DATABASE n8n
    set_env N8N_DB_USERNAME n8n
    set_env N8N_DB_PASSWORD "$password"
    [[ -n "$(get_env N8N_HOST)" ]] || set_env N8N_HOST "n8n.$CODERED_DOMAIN"
    [[ -n "$(get_env N8N_EDITOR_BASE_URL)" ]] || set_env N8N_EDITOR_BASE_URL "https://n8n.$CODERED_DOMAIN/"
    [[ -n "$(get_env N8N_WEBHOOK_URL)" ]] || set_env N8N_WEBHOOK_URL "https://n8n.$CODERED_DOMAIN/"
    [[ -n "$(get_env N8N_VERSION)" ]] || set_env N8N_VERSION "2.31.4"
    [[ -n "$(get_env CODERED_AGENT_LOCAL_URL)" ]] || set_env CODERED_AGENT_LOCAL_URL "http://codered-agent:5680"
    [[ -n "$(get_env N8N_TOKEN_REQUEST_WEBHOOK_URL)" ]] || set_env N8N_TOKEN_REQUEST_WEBHOOK_URL "https://n8n.$CODERED_DOMAIN/webhook/codered-events"
    if [[ -z "$(get_env N8N_ENCRYPTION_KEY)" ]]; then set_env N8N_ENCRYPTION_KEY "$(generate_secret)"; ok "Clave de cifrado de n8n generada correctamente."; fi
    if [[ -z "$(get_env N8N_TOKEN_REQUEST_WEBHOOK_SECRET)" ]]; then set_env N8N_TOKEN_REQUEST_WEBHOOK_SECRET "$(generate_secret)"; ok "Secreto de webhook n8n generado correctamente."; fi
    validate_n8n_compose_config
    ok "Variables n8n actualizadas en el .env principal."
}
verify_n8n_network() {
    if ! docker inspect codered-n8n >/dev/null 2>&1; then
        warn "El contenedor codered-n8n no existe todavía; se omite verificación de red."
        return
    fi
    local postgres_networks n8n_networks shared
    postgres_networks="$(docker inspect -f '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}' codered-postgres)"
    n8n_networks="$(docker inspect -f '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}' codered-n8n)"
    shared="$(comm -12 <(printf '%s\n' "$postgres_networks" | sort) <(printf '%s\n' "$n8n_networks" | sort) | head -n1 || true)"
    if [[ -n "$shared" ]]; then
        ok "codered-n8n comparte la red Docker '$shared' con codered-postgres."
    else
        warn "codered-n8n no comparte red con codered-postgres. Asegure una red común antes de iniciar n8n."
    fi
}

configure_n8n_postgres() {
    local password="$1" db_user role_exists validation
    password="$(normalize_env_secret "$password")"
    validate_n8n_db_password "$password" || die "La contraseña PostgreSQL n8n es inválida."
    info "Configurando PostgreSQL para n8n..."
    db_user="$(get_env POSTGRES_USER)"
    db_user="${db_user:-$(get_env DB_USERNAME)}"
    wait_for_postgres

    if postgres_role_exists "$db_user"; then
        info "El usuario PostgreSQL n8n ya existe; se actualizará su contraseña."
        role_exists=true
    else
        role_exists=false
    fi

    if ! docker exec -i -e N8N_DB_PASSWORD="$password" codered-postgres sh -s -- "$db_user" <<'SH' >/dev/null
set -eu
DB_USER="$1"
PASSWORD_SQL="$(printf '%s' "$N8N_DB_PASSWORD" | sed "s/'/''/g")"
psql -v ON_ERROR_STOP=1 -U "$DB_USER" -d postgres <<SQL
DO \$\$
DECLARE
    password_value text := '$PASSWORD_SQL';
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_roles
        WHERE rolname = 'n8n'
    ) THEN
        EXECUTE format('CREATE ROLE n8n LOGIN PASSWORD %L', password_value);
    ELSE
        EXECUTE format('ALTER ROLE n8n WITH LOGIN PASSWORD %L', password_value);
    END IF;
END
\$\$;
SQL
SH
    then
        die "No se pudo crear o actualizar el usuario PostgreSQL n8n."
    fi
    if [[ "$role_exists" == "true" ]]; then ok "Contraseña del usuario PostgreSQL n8n actualizada."; else ok "Usuario PostgreSQL n8n creado."; fi

    if postgres_database_exists "$db_user"; then
        info "La base de datos n8n ya existe; se conservarán sus datos."
    else
        docker exec codered-postgres psql -v ON_ERROR_STOP=1 -U "$db_user" -d postgres -c "CREATE DATABASE n8n OWNER n8n;" >/dev/null || die "No se pudo crear la base de datos n8n."
        ok "Base de datos n8n creada."
    fi

    if ! docker exec codered-postgres psql -v ON_ERROR_STOP=1 -U "$db_user" -d postgres <<'SQL' >/dev/null
ALTER DATABASE n8n OWNER TO n8n;
GRANT ALL PRIVILEGES ON DATABASE n8n TO n8n;
SQL
    then
        die "No se pudo configurar propietario o privilegios de la base n8n."
    fi

    if ! docker exec codered-postgres psql -v ON_ERROR_STOP=1 -U "$db_user" -d n8n <<'SQL' >/dev/null
ALTER SCHEMA public OWNER TO n8n;
GRANT ALL ON SCHEMA public TO n8n;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO n8n;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO n8n;
GRANT ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA public TO n8n;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO n8n;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO n8n;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON FUNCTIONS TO n8n;
SQL
    then
        die "No se pudo configurar privilegios del esquema public para n8n."
    fi
    ok "Propietario y privilegios de la base n8n configurados."

    configure_n8n_env "$password"
    recreate_n8n_service
    verify_n8n_network

    validation="$(docker exec -e PGPASSWORD="$password" codered-postgres psql -h 127.0.0.1 -U n8n -d n8n -Atqc "SELECT current_user || ':' || current_database();" 2>/dev/null || true)"
    [[ "$validation" == "n8n:n8n" ]] || die "No se pudo validar la conexión PostgreSQL como usuario n8n."
    ok "Conexión PostgreSQL n8n validada."
    verify_n8n_schema_objects "$password"
    ok "Configuración PostgreSQL de n8n completada."
}
validate_env_file() {
    local invalid
    invalid="$(awk '
        /\r$/ {print NR ": retorno CR"; next}
        /^[[:space:]]*($|#)/ {next}
        !/^[A-Za-z_][A-Za-z0-9_]*=/ {print NR ": clave invalida"; next}
        {
            key=$0; sub(/=.*/, "", key);
            value=substr($0,index($0,"=")+1);
            if (value ~ /^"([^"\\]|\\.)*"$/) next;
            if (value ~ /[[:space:]]/) print key;
        }
    ' "$ENV_FILE")"
    if [[ -n "$invalid" ]]; then
        while IFS= read -r key; do [[ -n "$key" ]] && echo "[ERROR] El archivo .env contiene un valor invalido en $key" >&2; done <<< "$invalid"
        return 1
    fi
    ok "Archivo .env valido"
}

echo "============================================================"
echo "        Instalador de CodeRED Platform"
echo "============================================================"
echo "Directorio de instalación: $PROJECT_DIR"
echo "============================================================"

command -v git >/dev/null || die "Git no está instalado."
command -v docker >/dev/null || die "Docker no está instalado."
docker compose version >/dev/null 2>&1 || die "Docker Compose v2 no está disponible."
docker info >/dev/null 2>&1 || die "Docker no está iniciado o faltan permisos."

# Verificar si el directorio existe y contiene código
if [[ -d "$PROJECT_DIR" ]]; then
    if [[ -f "$PROJECT_DIR/.env" || -f "$PROJECT_DIR/docker-compose.yml" || -f "$PROJECT_DIR/artisan" ]]; then
        if confirm "Ya existe un proyecto en $PROJECT_DIR. ¿Deseas continuar con la configuración?" n; then
            info "Usando proyecto existente en $PROJECT_DIR"
            CLONE_REPO=false
            cd "$PROJECT_DIR"
        else
            die "Instalación cancelada. Renombra o elimina $PROJECT_DIR antes de instalar."
        fi
    else
        info "Directorio $PROJECT_DIR existe pero está vacío. Se clonará el repositorio."
        CLONE_REPO=true
    fi
else
    info "Directorio $PROJECT_DIR no existe. Se creará y se clonará el repositorio."
    mkdir -p "$PROJECT_DIR"
    CLONE_REPO=true
fi

# Clonar repositorio si es necesario
if [[ "$CLONE_REPO" == "true" ]]; then
    info "Clonando repositorio..."
    git clone --depth=1 "$REPO_URL" "$PROJECT_DIR"
    cd "$PROJECT_DIR"
fi

[[ -f .env.example ]] || die "No se encontró .env.example en $PROJECT_DIR"

# Solo copiar .env si no existe
if [[ ! -f .env ]]; then
    cp .env.example .env
    cp .env ".env.backup-$STAMP"
    ok "Archivo .env creado. Backup: .env.backup-$STAMP"
else
    ok "Archivo .env ya existe, se preservará"
    cp .env ".env.backup-$STAMP"
    ok "Backup creado: .env.backup-$STAMP"
fi

echo; echo "1) Producción"; echo "2) Desarrollo"; read -r -p "Modo [1]: " mode; mode="${mode:-1}"
if [[ "$mode" == "2" ]]; then APP_ENV="local"; APP_DEBUG="true"; LOG_LEVEL="debug"; DEFAULT_URL="http://192.168.18.124:8090"; else APP_ENV="production"; APP_DEBUG="false"; LOG_LEVEL="info"; DEFAULT_URL="https://platform.$CODERED_DOMAIN"; fi

read_value "URL principal" "$DEFAULT_URL" true; APP_URL="${REPLY%/}"
read_value "Nombre de la base de datos" "$(get_env DB_DATABASE)" true; DB_DATABASE="$REPLY"
read_value "Usuario de la base de datos" "$(get_env DB_USERNAME)" true; DB_USERNAME="$REPLY"
read_password "Contraseña de PostgreSQL"; DB_PASSWORD="$REPLY"
read_value "Nombre del administrador" "Admin" true; ADMIN_NAME="$REPLY"
read_value "Correo del administrador" "admin@$CODERED_DOMAIN" true; ADMIN_EMAIL="$REPLY"
read_password "Contraseña del administrador"; ADMIN_PASSWORD="$REPLY"

set_env APP_NAME "CodeRED Platform" true; set_env VITE_APP_NAME "CodeRED Platform" true; set_env APP_ENV "$APP_ENV"; set_env APP_DEBUG "$APP_DEBUG"; set_env APP_URL "$APP_URL"; set_env CODERED_PLATFORM_URL "$APP_URL"; set_env LOG_LEVEL "$LOG_LEVEL"
set_env DB_CONNECTION pgsql; set_env DB_HOST postgres; set_env DB_PORT 5432; set_env DB_DATABASE "$DB_DATABASE"; set_env DB_USERNAME "$DB_USERNAME"; set_env DB_PASSWORD "$DB_PASSWORD"; sync_postgres_env; set_env DEV_ADMIN_NAME "$ADMIN_NAME" true; set_env DEV_ADMIN_EMAIL "$ADMIN_EMAIL"; set_env DEV_ADMIN_PASSWORD "$ADMIN_PASSWORD"
set_env QUEUE_CONNECTION "redis"; set_env REDIS_QUEUE_RETRY_AFTER "172900"; set_env RUC_ENABLED "true"; set_env RUC_IMPORT_DISK "local"; set_env RUC_IMPORT_INCOMING_DIRECTORY "private/ruc/incoming"; set_env RUC_IMPORT_WORKING_DIRECTORY "private/ruc/working"; set_env RUC_IMPORT_ARCHIVE_DIRECTORY "private/ruc/archive"; set_env RUC_IMPORT_ERRORS_DIRECTORY "private/ruc/errors"; set_env RUC_IMPORT_QUEUE "ruc-imports"; set_env RUC_IMPORT_CHUNK_SIZE "10000"; set_env RUC_IMPORT_COPY_BATCH_SIZE "100000"; set_env RUC_IMPORT_PROGRESS_INTERVAL "10000"; set_env RUC_IMPORT_CHECKPOINT_INTERVAL "50000"; set_env RUC_IMPORT_TIMEOUT "86400"; set_env RUC_IMPORT_LOCK_SECONDS "172800"; set_env RUC_IMPORT_ENCODING "ISO-8859-1"; set_env RUC_IMPORT_DELIMITER "|"; set_env RUC_IMPORT_MAX_SIZE_MB "30000"; set_env RUC_IMPORT_RESUME_ENABLED "true"; set_env RUC_IMPORT_ARCHIVE_FILES "true"; set_env RUC_IMPORT_STRATEGY "insert_ignore"
# Sesión / CSRF / CORS. Todo se deriva del APP_URL real en vez de hardcodear un
# dominio, para que una migración de dominio no requiera tocar este script.
# SESSION_DOMAIN solo se fija sobre HTTPS: con http:// la cookie con Domain=
# y Secure no viajaría y provocaría 419 "Page Expired" en Livewire/POST.
APP_HOST="$(url_host "$APP_URL")"
if [[ "$APP_URL" == https://* ]]; then
    set_env SESSION_DOMAIN "$(cookie_domain_for_url "$APP_URL")"
    set_env SESSION_SECURE_COOKIE "true"
else
    set_env SESSION_DOMAIN "null"
    set_env SESSION_SECURE_COOKIE "false"
fi

# Orígenes permitidos: host real de APP_URL primero, luego los dominios legacy
# aún soportados durante la transición, luego los orígenes de desarrollo y la
# extensión Chrome.
STATEFUL_DOMAINS="$APP_HOST"
ALLOWED_ORIGINS="$APP_URL"
for legacy_domain in $CODERED_LEGACY_DOMAINS; do
    [[ -n "$legacy_domain" ]] || continue
    [[ "$APP_HOST" == *".$legacy_domain" || "$APP_HOST" == "$legacy_domain" ]] && continue
    STATEFUL_DOMAINS="$STATEFUL_DOMAINS,platform.$legacy_domain"
    ALLOWED_ORIGINS="$ALLOWED_ORIGINS,https://platform.$legacy_domain"
done
STATEFUL_DOMAINS="$STATEFUL_DOMAINS,localhost:8090,127.0.0.1:8090,192.168.18.124:8090,chrome-extension://jpfcfljmbaijaajjdhblinjgblnfpign"
ALLOWED_ORIGINS="$ALLOWED_ORIGINS,http://192.168.18.124:8090,http://localhost:8090,chrome-extension://jpfcfljmbaijaajjdhblinjgblnfpign"
set_env SANCTUM_STATEFUL_DOMAINS "$STATEFUL_DOMAINS"
set_env API_ALLOWED_ORIGINS "$ALLOWED_ORIGINS"

if confirm "¿Activar PeruDevs para consultas DNI?" n; then read_value "URL PeruDevs" "https://api.perudevs.com/api/v1/dni/complete" true; set_env DNI_PERUDEVS_BASE_URL "${REPLY%/}"; read_password "Token/API key PeruDevs"; set_env DNI_PERUDEVS_API_KEY "$REPLY"; set_env DNI_PERUDEVS_ENABLED "true"; else set_env DNI_PERUDEVS_ENABLED "false"; set_env DNI_PERUDEVS_API_KEY ""; fi

configure_agent
ensure_n8n_env_defaults

# Configurar claves de encriptación de Token Request si no existen
if [[ -z "$(get_env TOKEN_REQUEST_DATA_ENCRYPTION_KEY)" ]]; then
    info "Generando clave de encriptación de datos de solicitudes de token..."
    enc_key="base64:$(openssl rand -base64 32)"
    set_env TOKEN_REQUEST_DATA_ENCRYPTION_KEY "$enc_key"
    ok "Clave de encriptación de datos generada correctamente."
fi

if [[ -z "$(get_env TOKEN_REQUEST_BLIND_INDEX_KEY)" ]]; then
    info "Generando clave de blind index para solicitudes de token..."
    blind_key="$(php -r "echo hash_hmac('sha256', 'blind-index', bin2hex(random_bytes(32)), false);" 2>/dev/null || echo "$(openssl rand -hex 32)")"
    set_env TOKEN_REQUEST_BLIND_INDEX_KEY "$blind_key"
    ok "Clave de blind index generada correctamente."
fi

validate_env_file || die "Corrige las claves indicadas antes de continuar."
unset DB_PASSWORD ADMIN_PASSWORD REPLY || true

info "Construyendo e iniciando contenedores..."
docker compose up -d --build
wait_for_postgres
configure_n8n_postgres "$(get_env N8N_DB_PASSWORD)"
info "Esperando Laravel..."
for _ in {1..40}; do if docker compose exec -T app php artisan about >/dev/null 2>&1; then break; fi; sleep 3; done
docker compose exec -T app php artisan about >/dev/null 2>&1 || die "Laravel no respondió a tiempo."
docker compose exec -T app mkdir -p storage/app/private/ruc/incoming storage/app/private/ruc/working storage/app/private/ruc/archive storage/app/private/ruc/errors
if [[ -z "$(get_env APP_KEY)" ]]; then docker compose exec -T app php artisan key:generate --force; fi
docker compose exec -T app php artisan migrate --force
if ! docker compose exec -T app php artisan db:seed --force; then
    echo "[ERROR] Falló la ejecución de los seeders de Laravel." >&2
    echo "[INFO] Revise database/seeders y los registros anteriores." >&2
    echo "[INFO] La instalación puede reanudarse después de corregir el seeder." >&2
    exit 1
fi
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan storage:link >/dev/null 2>&1 || true

if [[ -n "$(get_env CODERED_AGENT_ENCRYPTION_KEY)" && -n "$(get_env CODERED_AGENT_LOCAL_API_TOKEN)" ]]; then
    info "Construyendo CodeRED Agent..."
    docker compose build codered-agent
    docker compose up -d codered-agent
    docker compose ps codered-agent
    if curl --fail --silent http://127.0.0.1:5680/healthz >/dev/null; then ok "CodeRED Agent saludable."; else warn "CodeRED Agent no respondió al healthcheck. Últimos logs:"; docker compose logs --tail=100 codered-agent || true; fi
fi

echo; info "Verificando servicios sin reiniciarlos..."
for service in app nginx postgres redis queue scheduler shalom-extractor codered-agent codered-n8n; do if docker compose ps --status running --services | grep -qx "$service"; then ok "$service activo"; else warn "$service todavía no aparece activo"; fi; done

echo; echo "============================================================"; echo " CodeRED Platform instalada correctamente"; echo "============================================================"; echo "URL: $APP_URL"; echo "Administrador: $ADMIN_EMAIL"; echo "Directorio: $PROJECT_DIR"; echo; docker compose ps