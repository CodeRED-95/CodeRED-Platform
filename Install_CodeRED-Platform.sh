#!/usr/bin/env bash
set -Eeuo pipefail

REPO_URL="https://github.com/CodeRED-95/CodeRED-Platform.git"
PROJECT_DIR="${PROJECT_DIR:-$HOME/CodeRED-Platform}"
ENV_FILE="$PROJECT_DIR/.env"
STAMP="$(date +%Y%m%d-%H%M%S)"

ok(){ echo "[OK] $*"; }
info(){ echo "[INFO] $*"; }
warn(){ echo "[AVISO] $*"; }
die(){ echo "[ERROR] $*" >&2; exit 1; }

trap 'code=$?; echo "[ERROR] Fallo en la línea $LINENO" >&2; echo "[ERROR] Comando: ${BASH_COMMAND}" >&2; echo "[ERROR] Código de salida: $code" >&2; echo "[INFO] Revise el mensaje anterior. Si se modificó .env, restaure el backup .env.backup-* y reintente." >&2; exit $code' ERR

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
        [[ "$a" == *[[:space:]]* || "$a" == *"#"* || "$a" == *"="* || "$a" == *"\""* || "$a" == *"'"* ]] && { warn "La contraseña contiene caracteres incompatibles con el .env. No uses espacios, comillas, # ni =."; continue; }
        REPLY="$a"; return
    done
}

set_env() {
    local key="$1" value="$2" quote="${3:-false}" tmp
    [[ "$value" == *$'\n'* || "$value" == *$'\r'* ]] && { echo "[ERROR] El valor de $key contiene saltos de línea." >&2; return 1; }
    if [[ "$quote" == "true" ]]; then value="${value//\\/\\\\}"; value="${value//\"/\\\"}"; value="\"${value}\""; fi
    tmp="$(mktemp)"
    awk -v k="$key" -v v="$value" 'BEGIN{done=0} index($0,k"=")==1 {print k"="v; done=1; next} {print} END{if(!done) print k"="v}' "$ENV_FILE" > "$tmp"
    mv "$tmp" "$ENV_FILE"
}

get_env() { grep -E "^$1=" "$ENV_FILE" 2>/dev/null | head -n1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/' || true; }

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
    read_value "URL pública del agente" "$(get_env CODERED_AGENT_PUBLIC_URL || true)" false; AGENT_URL="${REPLY:-https://agent.codered.host}"
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

wait_for_postgres() {
    local db_user="$1" attempts="${2:-40}"
    command -v docker >/dev/null || die "Docker no está disponible."
    docker inspect codered-postgres >/dev/null 2>&1 || die "No se encontró el contenedor codered-postgres."
    [[ "$(docker inspect -f '{{.State.Running}}' codered-postgres 2>/dev/null)" == "true" ]] || die "El contenedor codered-postgres no está en ejecución."
    info "Esperando a que codered-postgres esté disponible..."
    for ((i=1; i<=attempts; i++)); do
        if docker exec codered-postgres pg_isready -U "$db_user" -d postgres >/dev/null 2>&1; then
            ok "PostgreSQL está disponible."
            return
        fi
        sleep 3
    done
    die "PostgreSQL no estuvo disponible dentro del tiempo esperado."
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
    local n8n_env_file="$1" n8n_dir
    n8n_dir="$(dirname "$n8n_env_file")"
    if [[ -f "$n8n_dir/docker-compose.yml" || -f "$n8n_dir/compose.yml" ]]; then
        (cd "$n8n_dir" && docker compose config >/dev/null) || die "docker compose config falló para n8n."
        ok "docker compose config de n8n validado sin imprimir secretos."
    else
        warn "No se encontró compose de n8n en $n8n_dir; se omite validación Docker Compose de n8n."
    fi
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
recreate_n8n_if_compose_present() {
    local n8n_env_file="$1" n8n_dir
    n8n_dir="$(dirname "$n8n_env_file")"
    if [[ -f "$n8n_dir/docker-compose.yml" || -f "$n8n_dir/compose.yml" ]]; then
        (cd "$n8n_dir" && docker compose up -d --force-recreate) || die "No se pudo recrear codered-n8n."
        (cd "$n8n_dir" && docker compose ps)
        if docker logs --since 5m codered-n8n 2>&1 | grep -F 'password authentication failed for user "n8n"' >/dev/null; then
            die "codered-n8n reportó fallo de autenticación PostgreSQL para n8n."
        fi
        ok "codered-n8n recreado sin fallo de autenticación PostgreSQL detectado."
    else
        warn "No se recreó codered-n8n porque no se encontró compose en $n8n_dir."
    fi
}
configure_n8n_env() {
    local password="$1" n8n_env_file="${N8N_ENV_FILE:-/opt/n8n/.env}"
    password="$(normalize_env_secret "$password")"
    validate_n8n_db_password "$password" || die "La contraseña PostgreSQL n8n es inválida."
    set_env_value_raw "$n8n_env_file" DB_TYPE postgresdb
    set_env_value_raw "$n8n_env_file" DB_POSTGRESDB_HOST codered-postgres
    set_env_value_raw "$n8n_env_file" DB_POSTGRESDB_PORT 5432
    set_env_value_raw "$n8n_env_file" DB_POSTGRESDB_DATABASE n8n
    set_env_value_raw "$n8n_env_file" DB_POSTGRESDB_USER n8n
    set_env_value_raw "$n8n_env_file" DB_POSTGRESDB_PASSWORD "$password"
    validate_n8n_env_password_value "$n8n_env_file" "$password"
    validate_n8n_compose_config "$n8n_env_file"
    ok "Archivo de configuración de n8n actualizado."
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
    db_user="$(get_env DB_USERNAME)"
    db_user="${db_user:-codered}"
    wait_for_postgres "$db_user"

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
    recreate_n8n_if_compose_present "${N8N_ENV_FILE:-/opt/n8n/.env}"
    verify_n8n_network

    validation="$(docker exec -e PGPASSWORD="$password" codered-postgres psql -h 127.0.0.1 -U n8n -d n8n -Atqc "SELECT current_user || ':' || current_database();" 2>/dev/null || true)"
    [[ "$validation" == "n8n:n8n" ]] || die "No se pudo validar la conexión PostgreSQL como usuario n8n."
    ok "Conexión PostgreSQL n8n validada."
    verify_n8n_schema_objects "$password"
    ok "Configuración PostgreSQL de n8n completada."
}
validate_env_file() {
    local invalid
    invalid="$(awk '/\r$/ {print NR ": retorno CR"; next} /^[[:space:]]*($|#)/ {next} !/^[A-Za-z_][A-Za-z0-9_]*=/ {print NR ": clave inválida"; next} {key=$0; sub(/=.*/, "", key); value=substr($0,index($0,"=")+1); if ((key=="DB_PASSWORD" || key=="DEV_ADMIN_PASSWORD" || key ~ /(_API_KEY|_TOKEN)$/) && value ~ /^"/) {print key; next} if (value ~ /^"([^"\\]|\\.)*"$/) next; if (value ~ /[[:space:]]/) print key}' "$ENV_FILE")"
    if [[ -n "$invalid" ]]; then while IFS= read -r key; do [[ -n "$key" ]] && echo "[ERROR] El archivo .env contiene un valor inválido en $key" >&2; done <<< "$invalid"; return 1; fi
    ok "Archivo .env válido"
}

echo "============================================================"
echo "        Instalador de CodeRED Platform"
echo "============================================================"
command -v git >/dev/null || die "Git no está instalado."
command -v docker >/dev/null || die "Docker no está instalado."
docker compose version >/dev/null 2>&1 || die "Docker Compose v2 no está disponible."
docker info >/dev/null 2>&1 || die "Docker no está iniciado o faltan permisos."

if [[ -e "$PROJECT_DIR" ]]; then die "Ya existe $PROJECT_DIR. Renómbralo o elimínalo antes de instalar."; fi

info "Clonando repositorio..."
git clone --depth=1 "$REPO_URL" "$PROJECT_DIR"
cd "$PROJECT_DIR"
[[ -f .env.example ]] || die "No se encontró .env.example."
cp .env.example .env
cp .env ".env.backup-$STAMP"
ok "Archivo .env creado. Backup: .env.backup-$STAMP"

echo; echo "1) Producción"; echo "2) Desarrollo"; read -r -p "Modo [1]: " mode; mode="${mode:-1}"
if [[ "$mode" == "2" ]]; then APP_ENV="local"; APP_DEBUG="true"; LOG_LEVEL="debug"; DEFAULT_URL="http://192.168.18.124:8090"; else APP_ENV="production"; APP_DEBUG="false"; LOG_LEVEL="info"; DEFAULT_URL="https://platform.codered.host"; fi

read_value "URL principal" "$DEFAULT_URL" true; APP_URL="${REPLY%/}"
read_value "Nombre de la base de datos" "$(get_env DB_DATABASE)" true; DB_DATABASE="$REPLY"
read_value "Usuario de la base de datos" "$(get_env DB_USERNAME)" true; DB_USERNAME="$REPLY"
read_password "Contraseña de PostgreSQL"; DB_PASSWORD="$REPLY"
read_value "Nombre del administrador" "Admin" true; ADMIN_NAME="$REPLY"
read_value "Correo del administrador" "admin@codered.host" true; ADMIN_EMAIL="$REPLY"
read_password "Contraseña del administrador"; ADMIN_PASSWORD="$REPLY"

set_env APP_NAME "CodeRED Platform" true; set_env VITE_APP_NAME "CodeRED Platform" true; set_env APP_ENV "$APP_ENV"; set_env APP_DEBUG "$APP_DEBUG"; set_env APP_URL "$APP_URL"; set_env CODERED_PLATFORM_URL "$APP_URL"; set_env LOG_LEVEL "$LOG_LEVEL"
set_env DB_DATABASE "$DB_DATABASE"; set_env DB_USERNAME "$DB_USERNAME"; set_env DB_PASSWORD "$DB_PASSWORD"; set_env DEV_ADMIN_NAME "$ADMIN_NAME" true; set_env DEV_ADMIN_EMAIL "$ADMIN_EMAIL"; set_env DEV_ADMIN_PASSWORD "$ADMIN_PASSWORD"
set_env QUEUE_CONNECTION "redis"; set_env REDIS_QUEUE_RETRY_AFTER "172900"; set_env RUC_ENABLED "true"; set_env RUC_IMPORT_DISK "local"; set_env RUC_IMPORT_INCOMING_DIRECTORY "private/ruc/incoming"; set_env RUC_IMPORT_WORKING_DIRECTORY "private/ruc/working"; set_env RUC_IMPORT_ARCHIVE_DIRECTORY "private/ruc/archive"; set_env RUC_IMPORT_ERRORS_DIRECTORY "private/ruc/errors"; set_env RUC_IMPORT_QUEUE "ruc-imports"; set_env RUC_IMPORT_CHUNK_SIZE "10000"; set_env RUC_IMPORT_COPY_BATCH_SIZE "100000"; set_env RUC_IMPORT_PROGRESS_INTERVAL "10000"; set_env RUC_IMPORT_CHECKPOINT_INTERVAL "50000"; set_env RUC_IMPORT_TIMEOUT "86400"; set_env RUC_IMPORT_LOCK_SECONDS "172800"; set_env RUC_IMPORT_ENCODING "ISO-8859-1"; set_env RUC_IMPORT_DELIMITER "|"; set_env RUC_IMPORT_MAX_SIZE_MB "30000"; set_env RUC_IMPORT_RESUME_ENABLED "true"; set_env RUC_IMPORT_ARCHIVE_FILES "true"; set_env RUC_IMPORT_STRATEGY "insert_ignore"
if [[ "$APP_URL" == https://*.codered.host ]]; then set_env SESSION_DOMAIN ".codered.host"; else set_env SESSION_DOMAIN "null"; fi
set_env SANCTUM_STATEFUL_DOMAINS "platform.codered.host,localhost:8090,127.0.0.1:8090,192.168.18.124:8090,chrome-extension://jpfcfljmbaijaajjdhblinjgblnfpign"
set_env API_ALLOWED_ORIGINS "https://platform.codered.host,http://192.168.18.124:8090,http://localhost:8090,chrome-extension://jpfcfljmbaijaajjdhblinjgblnfpign"

if confirm "¿Activar PeruDevs para consultas DNI?" n; then read_value "URL PeruDevs" "https://api.perudevs.com/api/v1/dni/complete" true; set_env DNI_PERUDEVS_BASE_URL "${REPLY%/}"; read_password "Token/API key PeruDevs"; set_env DNI_PERUDEVS_API_KEY "$REPLY"; set_env DNI_PERUDEVS_ENABLED "true"; else set_env DNI_PERUDEVS_ENABLED "false"; set_env DNI_PERUDEVS_API_KEY ""; fi

configure_agent
validate_env_file || die "Corrige las claves indicadas antes de continuar."
unset DB_PASSWORD ADMIN_PASSWORD REPLY || true

info "Construyendo e iniciando contenedores..."
docker compose up -d --build
if ask_yes_no "¿Deseas configurar la base de datos de n8n?" s; then
    prompt_secret_with_confirmation "Ingrese la contraseña para PostgreSQL n8n"
    N8N_DB_PASSWORD="$REPLY"
    configure_n8n_postgres "$N8N_DB_PASSWORD"
    unset N8N_DB_PASSWORD REPLY || true
else
    info "Configuración PostgreSQL de n8n omitida."
fi
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
for service in app nginx postgres redis queue scheduler; do if docker compose ps --status running --services | grep -qx "$service"; then ok "$service activo"; else warn "$service todavía no aparece activo"; fi; done

echo; echo "============================================================"; echo " CodeRED Platform instalada correctamente"; echo "============================================================"; echo "URL: $APP_URL"; echo "Administrador: $ADMIN_EMAIL"; echo "Directorio: $PROJECT_DIR"; echo; docker compose ps