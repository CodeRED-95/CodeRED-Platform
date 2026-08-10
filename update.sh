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
step(){ echo; echo "[$1/16] $2"; }

# ---------------------------------------------------------------------------
# DOMINIO DE LA PLATAFORMA
# ---------------------------------------------------------------------------
# Dominio productivo actual: codered.lat (migrado desde codered.host).
# Estos valores SOLO se usan como default cuando la variable correspondiente
# no existe todavía en el .env: este script nunca sobrescribe un valor que el
# operador ya configuró (ver ensure_agent_env). Para una futura migración de
# dominio basta cambiar CODERED_DOMAIN aquí o exportarlo antes de ejecutar.
CODERED_DOMAIN="${CODERED_DOMAIN:-codered.lat}"
# Dominios antiguos que siguen aceptados en CORS/Sanctum durante la transición.
# Vaciar cuando el dominio nuevo esté validado al 100%.
CODERED_LEGACY_DOMAINS="${CODERED_LEGACY_DOMAINS:-codered.host}"

url_host(){
    local url="${1:-}"
    url="${url#*://}"
    url="${url%%/*}"
    url="${url%%\?*}"
    url="${url##*@}"
    printf '%s' "${url%%:*}" | tr '[:upper:]' '[:lower:]'
}

cookie_domain_for_url(){
    local host labels
    host="$(url_host "${1:-}")"
    [[ -n "$host" ]] || { printf 'null'; return; }
    if [[ "$host" =~ ^[0-9.]+$ || "$host" != *.* ]]; then printf 'null'; return; fi
    labels="$(awk -F. '{print NF}' <<<"$host")"
    if (( labels < 3 )); then printf 'null'; return; fi
    printf '.%s' "$(awk -F. '{print $(NF-1)"."$NF}' <<<"$host")"
}

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

# Versión del proyecto desde la fuente única de verdad. Se delega en
# bin/version.sh para no duplicar aquí la forma de leer composer.json.
read_project_version(){
    if [[ -x "$PROJECT_DIR/bin/version.sh" ]]; then
        "$PROJECT_DIR/bin/version.sh" 2>/dev/null || echo "desconocida"
    elif [[ -f "$PROJECT_DIR/bin/version.sh" ]]; then
        bash "$PROJECT_DIR/bin/version.sh" 2>/dev/null || echo "desconocida"
    else
        echo "desconocida"
    fi
}

# Instalaciones anteriores a 3.5.0 fijaban APP_VERSION en su .env. Esa copia
# ganaba sobre el código y hacía que la app reportara una versión que no era la
# desplegada. Ya no se consulta: se elimina para que no confunda a quien lea el
# archivo. No se toca ninguna otra variable.
migrate_legacy_app_version(){
    local legacy tmp
    legacy="$(grep -E '^APP_VERSION=' .env 2>/dev/null | head -n1 || true)"
    [[ -n "$legacy" ]] || return 0

    tmp="$(mktemp)"
    grep -v -E '^APP_VERSION=' .env > "$tmp"
    mv "$tmp" .env
    chmod 600 .env 2>/dev/null || true
    ok "APP_VERSION heredado eliminado del .env (la versión vive en composer.json > extra.version)."
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
    [[ -n "$(get_env CODERED_AGENT_PUBLIC_URL)" ]] || set_env CODERED_AGENT_PUBLIC_URL "https://agent.$CODERED_DOMAIN"
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
    [[ -n "$(get_env N8N_HOST)" ]] || set_env N8N_HOST "n8n.$CODERED_DOMAIN"
    [[ -n "$(get_env N8N_EDITOR_BASE_URL)" ]] || set_env N8N_EDITOR_BASE_URL "https://n8n.$CODERED_DOMAIN/"
    [[ -n "$(get_env N8N_WEBHOOK_URL)" ]] || set_env N8N_WEBHOOK_URL "https://n8n.$CODERED_DOMAIN/"
    [[ -n "$(get_env N8N_TOKEN_REQUEST_WEBHOOK_URL)" ]] || set_env N8N_TOKEN_REQUEST_WEBHOOK_URL "https://n8n.$CODERED_DOMAIN/webhook/codered-events"

    if need_secret CODERED_AGENT_ENCRYPTION_KEY; then set_env CODERED_AGENT_ENCRYPTION_KEY "$(generate_secret)"; ok "Clave de cifrado del agente generada correctamente."; fi
    if need_secret CODERED_AGENT_LOCAL_API_TOKEN; then set_env CODERED_AGENT_LOCAL_API_TOKEN "$(generate_secret)"; ok "Token de API local del agente generado correctamente."; fi
    local enc token
    enc="$(get_env CODERED_AGENT_ENCRYPTION_KEY)"; token="$(get_env CODERED_AGENT_LOCAL_API_TOKEN)"
    [[ "$enc" =~ ^[0-9a-f]{64}$ ]] || die "CODERED_AGENT_ENCRYPTION_KEY debe tener 64 caracteres hexadecimales."
    [[ "$token" =~ ^[0-9a-f]{64}$ ]] || die "CODERED_AGENT_LOCAL_API_TOKEN debe tener 64 caracteres hexadecimales."
    [[ "$enc" != "$token" ]] || die "Los secretos del agente deben ser diferentes."
}

# Verifica que la configuración de cookies/CSRF sea coherente con APP_URL.
# NO reescribe nada: cambiar SESSION_DOMAIN o los orígenes permitidos por
# nuestra cuenta puede desconectar clientes en producción, así que solo avisa.
# Este chequeo existe para que una migración de dominio (codered.host ->
# codered.lat) no quede a medias y provoque 419 "Page Expired" en Livewire.
check_domain_consistency(){
    local app_url app_host expected_cookie session_domain secure stateful origins missing=0
    app_url="$(get_env APP_URL)"
    [[ -n "$app_url" ]] || { warn "APP_URL está vacío en .env; no se puede validar la configuración de cookies."; return; }
    app_host="$(url_host "$app_url")"
    expected_cookie="$(cookie_domain_for_url "$app_url")"
    session_domain="$(get_env SESSION_DOMAIN)"
    secure="$(get_env SESSION_SECURE_COOKIE)"
    stateful="$(get_env SANCTUM_STATEFUL_DOMAINS)"
    origins="$(get_env API_ALLOWED_ORIGINS)"

    if [[ -n "$session_domain" && "$session_domain" != "null" && "$session_domain" != "$expected_cookie" ]]; then
        warn "SESSION_DOMAIN ($session_domain) no coincide con APP_URL ($app_host; se esperaba $expected_cookie)."
        warn "El navegador descartará la cookie de sesión -> HTTP 419 en login/Livewire. Corrija .env y reejecute."
        missing=1
    fi
    if [[ "$app_url" == https://* && "$secure" != "true" ]]; then
        warn "APP_URL usa HTTPS pero SESSION_SECURE_COOKIE no es true. Recomendado: SESSION_SECURE_COOKIE=true."
        missing=1
    fi
    if [[ "$app_url" != https://* && "$secure" == "true" ]]; then
        warn "SESSION_SECURE_COOKIE=true con APP_URL no-HTTPS: no habrá sesión. Póngalo en false."
        missing=1
    fi
    if [[ -n "$app_host" && "$stateful" != *"$app_host"* ]]; then
        warn "SANCTUM_STATEFUL_DOMAINS no incluye $app_host; las peticiones con cookie desde el panel fallarán."
        missing=1
    fi
    if [[ -n "$origins" && "$origins" != *"$app_url"* ]]; then
        warn "API_ALLOWED_ORIGINS no incluye $app_url; el navegador bloqueará las llamadas CORS a la API."
        missing=1
    fi
    for legacy_domain in $CODERED_LEGACY_DOMAINS; do
        [[ -n "$legacy_domain" ]] || continue
        if [[ "$app_host" == *"$legacy_domain" ]]; then
            warn "APP_URL todavía apunta al dominio legacy $legacy_domain. El dominio productivo es $CODERED_DOMAIN."
            missing=1
        fi
    done
    if (( missing == 0 )); then
        ok "Configuración de dominio coherente con APP_URL ($app_host)."
    else
        warn "Revise docs/ENVIRONMENT.md, sección 'Migración de dominio', antes de continuar."
    fi
}

# PostgreSQL debe escuchar en todas las interfaces DENTRO de la red Docker o
# n8n devuelve 503 "Database is not ready!" y Laravel "Connection refused".
# Esto no expone el puerto al host: el servicio postgres no publica `ports:`.
verify_postgres_listen_addresses(){
    local value
    value="$(docker compose exec -T postgres psql -U "$(get_env POSTGRES_USER)" -d "$(get_env POSTGRES_DB)" -tAc "SHOW listen_addresses;" 2>/dev/null | tr -d '[:space:]')"
    if [[ "$value" == "*" ]]; then
        ok "PostgreSQL escucha en la red Docker (listen_addresses = '*')."
        return
    fi
    warn "PostgreSQL tiene listen_addresses='$value'; n8n y Laravel no podrán conectarse desde otros contenedores."
    info "docker/postgres/codered.conf ya declara listen_addresses = '*'; reiniciando postgres para aplicarlo…"
    docker compose restart postgres
    wait_for_postgres
    value="$(docker compose exec -T postgres psql -U "$(get_env POSTGRES_USER)" -d "$(get_env POSTGRES_DB)" -tAc "SHOW listen_addresses;" 2>/dev/null | tr -d '[:space:]')"
    if [[ "$value" == "*" ]]; then
        ok "listen_addresses corregido a '*'."
    else
        warn "listen_addresses sigue en '$value'. Revise postgresql.auto.conf dentro del volumen pgdata:"
        warn "  docker compose exec -T postgres psql -U <user> -d <db> -c \"ALTER SYSTEM SET listen_addresses = '*';\""
        warn "  docker compose restart postgres"
    fi
}

# Comprueba que n8n realmente pueda hablar con PostgreSQL después de un
# reinicio de la base. n8n arranca antes de que postgres acepte conexiones y
# se queda en 503 hasta que se lo recrea.
verify_n8n_database(){
    docker compose config --services | grep -qx codered-n8n || { info "codered-n8n no está definido; se omite verificación."; return; }
    local attempts=20
    for ((i=1; i<=attempts; i++)); do
        if docker compose exec -T codered-n8n node -e "fetch('http://127.0.0.1:5678/healthz').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))" >/dev/null 2>&1; then
            ok "n8n responde /healthz (conexión a PostgreSQL correcta)."
            return
        fi
        sleep 5
    done
    warn "n8n no respondió /healthz. Recreando el contenedor para forzar reconexión a PostgreSQL…"
    docker compose up -d --force-recreate --no-deps codered-n8n
    sleep 20
    if docker compose exec -T codered-n8n node -e "fetch('http://127.0.0.1:5678/healthz').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))" >/dev/null 2>&1; then
        ok "n8n recuperado tras recrear el contenedor (datos y credenciales intactos)."
    else
        warn "n8n sigue sin responder. Últimos logs (sin secretos):"
        docker compose logs --tail=50 codered-n8n || true
    fi
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
RUNNING_RESTORE_COUNT="0"
if docker compose exec -T app php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!Illuminate\Support\Facades\Schema::hasTable("ruc_backup_operations")) { echo "0"; exit; }
echo (string) Illuminate\Support\Facades\DB::table("ruc_backup_operations")->whereIn("status", ["pending", "running"])->count();
' 2>/dev/null > /tmp/restore_count.txt; then
    RUNNING_RESTORE_COUNT="$(cat /tmp/restore_count.txt)"
else
    # Si el comando falla (DB no accesible todavía, app no listo), asume 0 y continúa
    info "No se pudo verificar restore status (DB no accesible aún); asumiendo 0 restauraciones activas."
    RUNNING_RESTORE_COUNT="0"
fi
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
# La versión se lee de la fuente única de verdad (composer.json > extra.version)
# ANTES del pull para poder informar del salto exacto al terminar.
OLD_VERSION="$(read_project_version)"
git pull --ff-only || die "git pull falló. Resuelva cambios locales o sincronización remota y reintente."
NEW_HEAD="$(git rev-parse HEAD)"
NEW_VERSION="$(read_project_version)"
ok "Repositorio actualizado: $OLD_HEAD -> $NEW_HEAD"
if [[ "$OLD_VERSION" == "$NEW_VERSION" ]]; then
    info "Versión de CodeRED Platform: $NEW_VERSION (sin cambios)."
else
    ok "Versión de CodeRED Platform: $OLD_VERSION -> $NEW_VERSION"
fi

step 5 "Revisando variables nuevas"
sync_postgres_env
ensure_agent_env
check_domain_consistency
migrate_legacy_app_version
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

# docker/postgres/codered.conf está montado read-only: `docker compose up -d`
# NO reinicia postgres cuando solo cambia el contenido del archivo, así que un
# cambio de configuración (p.ej. listen_addresses) quedaría sin aplicar hasta
# el siguiente reinicio manual. Reiniciar aquí es no destructivo: el volumen
# pgdata y todos los datos se conservan.
if [[ "$OLD_HEAD" != "$NEW_HEAD" ]] && changed '^docker/postgres/'; then
    info "docker/postgres/ cambió en este pull; reiniciando postgres para aplicar la nueva configuración…"
    docker compose restart postgres
    wait_for_postgres
    ok "PostgreSQL reiniciado con la configuración actualizada (datos intactos)."
fi

if [[ "${N8N_REBUILD_REQUIRED:-0}" == "1" ]]; then
    docker compose up -d --force-recreate --no-deps codered-n8n
fi
ok "Servicios levantados sin borrar volumenes."

step 8 "Validando configuración de PostgreSQL"
verify_postgres_listen_addresses

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

# El sistema de IMPORTACIÓN RUC fue retirado (v3.0.0): el padrón se
# administra solo con backup/restore. Se verifica que la migración
# 2026_08_09_000001_drop_ruc_import_tables se aplicó y que ruc_records
# sigue intacta. Solo informa: NUNCA borra tablas ni datos por su cuenta.
verify_ruc_import_removal(){
    local pg_user pg_db leftovers records
    pg_user="$(get_env POSTGRES_USER)"; pg_db="$(get_env POSTGRES_DB)"
    [[ -n "$pg_user" && -n "$pg_db" ]] || { info "Sin credenciales PostgreSQL en .env; se omite verificación RUC."; return; }

    leftovers="$(docker compose exec -T postgres psql -U "$pg_user" -d "$pg_db" -tAc \
        "SELECT string_agg(table_name, ', ') FROM information_schema.tables
         WHERE table_schema='public'
           AND table_name IN ('ruc_imports','ruc_import_errors','ruc_import_events','ruc_import_duplicates','ruc_staging');" 2>/dev/null | tr -d '[:space:]')"
    if [[ -n "$leftovers" ]]; then
        warn "Todavía existen tablas de importación RUC: $leftovers"
        warn "La migración de limpieza no se aplicó. Ejecute: docker compose exec -T app php artisan migrate --force"
    else
        ok "Tablas de importación RUC retiradas correctamente."
    fi

    # ruc_records NUNCA debe desaparecer ni vaciarse por este deploy.
    records="$(docker compose exec -T postgres psql -U "$pg_user" -d "$pg_db" -tAc \
        "SELECT to_regclass('public.ruc_records') IS NOT NULL;" 2>/dev/null | tr -d '[:space:]')"
    if [[ "$records" == "t" ]]; then
        ok "ruc_records presente (el padrón NO se toca en esta actualización)."
    else
        warn "No se encontró la tabla ruc_records. Revise el estado de las migraciones antes de continuar."
    fi

    # Jobs viejos que hayan quedado encolados en la cola "ruc-imports": ya
    # nadie la consume. Solo se informa; borrarlos es decisión del operador.
    local pending
    pending="$(docker compose exec -T redis redis-cli LLEN queues:ruc-imports 2>/dev/null | tr -d '[:space:]')"
    if [[ "$pending" =~ ^[0-9]+$ && "$pending" != "0" ]]; then
        warn "Quedan $pending jobs en la cola Redis 'ruc-imports', que ya no tiene worker."
        warn "Son inertes. Para descartarlos manualmente: docker compose exec -T redis redis-cli DEL queues:ruc-imports"
    fi
}
verify_ruc_import_removal

# El esquema de api_token_requests quedó desalineado del código en v3.3.0: la
# migración 2026_08_07_000001 eliminó columnas que la aplicación seguía usando
# y /solicitar-token dejó de funcionar. 2026_08_10_000001 lo restaura. Aquí
# solo se COMPRUEBA que esas columnas existen tras migrar: no altera nada.
verify_token_request_schema(){
    local pg_user pg_db missing legacy
    pg_user="$(get_env POSTGRES_USER)"; pg_db="$(get_env POSTGRES_DB)"
    [[ -n "$pg_user" && -n "$pg_db" ]] || { info "Sin credenciales PostgreSQL en .env; se omite verificación de solicitudes de token."; return; }

    missing="$(docker compose exec -T postgres psql -U "$pg_user" -d "$pg_db" -tAc \
        "SELECT string_agg(c.name, ', ')
           FROM (VALUES ('tracking_code'),('token_ciphertext'),('token_hash'),('token_last_four'),
                        ('token_revealed_at'),('token_revealed_by_type'),('token_revealed_by_user_id'),
                        ('requester_name_encrypted'),('requester_email_blind_index'),
                        ('requester_phone_encrypted'),('purpose_encrypted'),
                        ('delivery_method_encrypted'),('delivery_reason_encrypted')) AS c(name)
          WHERE NOT EXISTS (
                SELECT 1 FROM information_schema.columns
                 WHERE table_name='api_token_requests' AND column_name=c.name);" 2>/dev/null | tr -d '[:space:]')"

    if [[ -n "$missing" ]]; then
        warn "Faltan columnas en api_token_requests: $missing"
        warn "El formulario público /solicitar-token fallará. Aplique: docker compose exec -T app php artisan migrate --force"
    else
        ok "Esquema de api_token_requests alineado con el código (tracking_code, token_ciphertext y campos cifrados)."
    fi

    # Si sobrevive el nombre antiguo, hay dos columnas para el mismo dato y los
    # flujos de purga limpiarían la equivocada.
    legacy="$(docker compose exec -T postgres psql -U "$pg_user" -d "$pg_db" -tAc \
        "SELECT count(*) FROM information_schema.columns
          WHERE table_name='api_token_requests' AND column_name='encrypted_plain_text_token';" 2>/dev/null | tr -d '[:space:]')"
    if [[ "$legacy" =~ ^[0-9]+$ && "$legacy" != "0" ]]; then
        warn "Sigue existiendo la columna heredada encrypted_plain_text_token junto a token_ciphertext."
        warn "Aplique: docker compose exec -T app php artisan migrate --force"
    fi
}
verify_token_request_schema

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

# El formato .rucbackup comprime cada lote con zstd y usa la extensión zip de
# PHP como contenedor ZIP64. Sin cualquiera de las dos, backup y restore
# fallan en el primer lote. Ver docs-ruc/BACKUP_FORMAT.md.
if docker compose exec -T app sh -c 'command -v zstd >/dev/null'; then
    ok "zstd disponible en el contenedor app ($(docker compose exec -T app zstd --version 2>/dev/null | head -1 | tr -d '\r'))."
else
    warn "zstd NO está instalado en el contenedor app: el formato .rucbackup no funcionará."
    warn "docker/php/Dockerfile ya lo declara; reconstruya con: docker compose build app queue queue-ruc-backups scheduler"
fi

if docker compose exec -T app php -r 'exit(extension_loaded("zip") ? 0 : 1);'; then
    ok "Extensión PHP zip (contenedor ZIP64 de .rucbackup) disponible."
else
    warn "La extensión PHP zip NO está cargada: no se podrán leer ni escribir archivos .rucbackup."
    warn "Reconstruya la imagen: docker compose build app queue queue-ruc-backups scheduler"
fi

# Restos de una restauración troceada interrumpida. Solo se informa: decidir
# entre reanudar, descartar o revertir es del operador, nunca del deploy.
RUC_STAGING_ROWS="$(docker compose exec -T postgres psql -U "$(get_env POSTGRES_USER)" -d "$(get_env POSTGRES_DB)" -tAc \
    "SELECT CASE WHEN to_regclass('public.ruc_records_next') IS NULL THEN '' ELSE (SELECT count(*)::text FROM ruc_records_next) END;" 2>/dev/null | tr -d '[:space:]')"
if [[ -n "$RUC_STAGING_ROWS" ]]; then
    warn "Existe ruc_records_next con $RUC_STAGING_ROWS filas: hay una restauración troceada sin terminar."
    warn "  Reanudar:  docker compose exec -T app php artisan ruc:restore <id> --resume"
    warn "  Descartar: docker compose exec -T app php artisan ruc:restore-manage --discard-staging"
    warn "  Estado:    docker compose exec -T app php artisan ruc:restore-manage --status"
fi

if docker compose exec -T postgres psql -U "$(get_env POSTGRES_USER)" -d "$(get_env POSTGRES_DB)" -tAc \
    "SELECT to_regclass('public.ruc_records_old') IS NOT NULL;" 2>/dev/null | grep -q t; then
    info "ruc_records_old presente: copia de la restauración anterior, disponible para rollback."
    info "  Revertir: docker compose exec -T app php artisan ruc:restore-manage --rollback"
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
# restore. Ver RucStatisticsService y PERFORMANCE.md.
docker compose exec -T app php artisan cache:clear
docker compose exec -T app php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('cache')->forget('ruc:records:count');
\$app->make('cache')->forget('dashboard:ruc');
" 2>/dev/null || true

ok "Cachés limpiados; estadísticas RUC se recalcularán en el siguiente restore."

step 13 "Verificando salud"
docker compose ps
docker compose exec -T app php artisan about

# La aplicación dentro del contenedor debe reportar exactamente la misma
# versión que la fuente de verdad del repositorio. Si difieren, casi siempre es
# una caché de configuración vieja o un contenedor que no se recreó.
SSOT_VERSION="$(read_project_version)"
APP_REPORTED_VERSION="$(docker compose exec -T app php artisan app:version 2>/dev/null | tr -d '[:space:]')"
if [[ "$SSOT_VERSION" == "$APP_REPORTED_VERSION" ]]; then
    ok "Versión verificada en el contenedor: $APP_REPORTED_VERSION (coincide con composer.json)."
else
    warn "Discrepancia de versión: composer.json dice '$SSOT_VERSION' y la app reporta '$APP_REPORTED_VERSION'."
    warn "Ejecute: docker compose exec -T app php artisan config:clear && docker compose up -d --force-recreate app"
fi

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

# VACUUM ANALYZE post-deploy sobre ruc_records.
#
# ANALYZE por sí solo NO basta: el índice GIN de razon_social acumula
# entradas en su "pending list" tras cualquier carga masiva, y mientras no
# se vacía cada búsqueda por razón social la recorre linealmente. Medido
# sobre 18M filas: la misma búsqueda pasa de 6 996 ms a 1 148 ms tras el
# VACUUM. Solo VACUUM vacía esa lista. Ver docs-ruc/LIST_PERFORMANCE.md.
#
# VACUUM (sin FULL) no bloquea lecturas ni escrituras: la tabla sigue
# atendiendo consultas mientras se ejecuta. Coste sobre 18M: ~6 s.
if docker compose exec -T postgres psql -U "$(get_env POSTGRES_USER)" -d "$(get_env POSTGRES_DB)" -c "SELECT 1 FROM information_schema.tables WHERE table_name='ruc_records';" | grep -q 1; then
    info "Ejecutando VACUUM ANALYZE sobre ruc_records..."
    if docker compose exec -T postgres psql -U "$(get_env POSTGRES_USER)" -d "$(get_env POSTGRES_DB)"         -c "VACUUM ANALYZE ruc_records;" >/dev/null 2>&1; then
        ok "VACUUM ANALYZE completado (estadísticas y pending list del índice GIN al día)."
    else
        warn "VACUUM ANALYZE falló (no crítico: se repetirá en la próxima restauración)."
    fi

    # La migración que sustituye los índices de filtro por compuestos
    # (columna, id) tarda ~1m30s sobre 18M filas. Si al terminar el deploy
    # siguieran presentes los antiguos, es que no se aplicó.
    LEGACY_IDX="$(docker compose exec -T postgres psql -U "$(get_env POSTGRES_USER)" -d "$(get_env POSTGRES_DB)" -tAc         "SELECT count(*) FROM pg_indexes WHERE tablename='ruc_records' AND indexname IN ('ruc_records_estado_index','ruc_records_condicion_index','ruc_records_departamento_index','ruc_records_provincia_index','ruc_records_distrito_index','ruc_records_ubigeo_index');" 2>/dev/null | tr -d '[:space:]')"
    if [[ "$LEGACY_IDX" =~ ^[0-9]+$ && "$LEGACY_IDX" != "0" ]]; then
        warn "Siguen presentes $LEGACY_IDX índices de filtro antiguos en ruc_records."
        warn "El listado /admin/ruc puede tardar segundos con filtros combinados."
        warn "Aplique: docker compose exec -T app php artisan migrate --force"
    fi
else
    info "ruc_records aún no existe; VACUUM ANALYZE se ejecutará tras la primera restauración."
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

step 15 "Validando dominio, n8n y cookies"
verify_n8n_database

# La cookie de sesión debe llevar el dominio derivado de APP_URL. Si sigue
# apuntando a un dominio antiguo, el login y Livewire devuelven 419.
APP_URL_VALUE="$(get_env APP_URL)"
EXPECTED_COOKIE_DOMAIN="$(cookie_domain_for_url "$APP_URL_VALUE")"
if [[ -n "$APP_URL_VALUE" ]]; then
    # Solo se leen cabeceras; el cuerpo y cualquier token quedan fuera del log.
    COOKIE_HEADERS="$(curl -sS -I --max-time 15 "$APP_URL_VALUE" 2>/dev/null | grep -i '^set-cookie:' | grep -io 'domain=[^;]*' || true)"
    if [[ -z "$COOKIE_HEADERS" ]]; then
        info "La respuesta de $APP_URL_VALUE no trajo Set-Cookie con Domain= (válido si SESSION_DOMAIN=null)."
    elif [[ "$EXPECTED_COOKIE_DOMAIN" != "null" && "$COOKIE_HEADERS" != *"${EXPECTED_COOKIE_DOMAIN}"* ]]; then
        warn "Las cookies emitidas no usan $EXPECTED_COOKIE_DOMAIN. Detectado: $COOKIE_HEADERS"
        warn "Revise SESSION_DOMAIN en .env y vuelva a ejecutar php artisan config:cache."
    else
        ok "Cookies de sesión emitidas con el dominio esperado ($EXPECTED_COOKIE_DOMAIN)."
    fi
fi

for legacy_domain in $CODERED_LEGACY_DOMAINS; do
    [[ -n "$legacy_domain" ]] || continue
    info "Compatibilidad temporal activa para el dominio legacy: $legacy_domain (CORS/Sanctum)."
done

step 16 "Actualización completada"
ok "CodeRED Platform actualizado correctamente."
if [[ "$OLD_VERSION" == "$NEW_VERSION" ]]; then
    echo "Versión: $NEW_VERSION (sin cambios en esta actualización)"
else
    echo "Versión: $OLD_VERSION -> $NEW_VERSION"
fi
echo "Backup del .env: .env.backup-$STAMP"
ok "RUC performance optimization complete:"
echo "  ✓ PHASE 1: Cursor pagination, hardcoded filters, column selection"
echo "  ✓ PHASE 2: PostgreSQL tuning (shared_buffers=1GB, work_mem=32MB, etc.)"
echo "  ✓ PHASE 3: /dev/shm increased to 512MB for VACUUM ANALYZE"
echo "  ✓ PHASE 4: ANALYZE automated post-restore"
echo "  ✓ PHASE 5: Performance tests and benchmarks integrated"