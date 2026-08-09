#!/usr/bin/env bash
set -Eeuo pipefail
source <(sed -n '1,/^echo "============================================================"/p' Install_CodeRED-Platform.sh | sed '$d')
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
ENV_FILE="$work/.env"
printf 'APP_NAME=Laravel\nDEV_ADMIN_NAME=Admin\nDEV_ADMIN_PASSWORD=old\n' > "$ENV_FILE"
set_env APP_NAME 'CodeRED Platform' true
set_env VITE_APP_NAME 'CodeRED Platform' true
set_env DEV_ADMIN_NAME 'Administrador CodeRED' true
set_env DEV_ADMIN_PASSWORD 'ClaveSegura123!'
set_env DB_PASSWORD 'PostgresSegura123!'
set_env DEV_ADMIN_EMAIL 'admin@codered.lat'
set_env APP_URL 'https://platform.codered.lat'
set_env APP_DEBUG 'false'
set_env RUC_BACKUP_MAX_UPLOAD_MB '5000'
grep -qx 'APP_NAME="CodeRED Platform"' "$ENV_FILE"
grep -qx 'DEV_ADMIN_NAME="Administrador CodeRED"' "$ENV_FILE"
[[ "$(get_env DEV_ADMIN_PASSWORD)" == 'ClaveSegura123!' ]]
[[ "$(get_env DB_PASSWORD)" == 'PostgresSegura123!' ]]
grep -qx 'DEV_ADMIN_EMAIL=admin@codered.lat' "$ENV_FILE"
grep -qx 'APP_URL=https://platform.codered.lat' "$ENV_FILE"
validate_env_file

# Helpers de dominio (migración codered.host -> codered.lat). SESSION_DOMAIN se
# deriva de APP_URL en vez de hardcodearse, para que una futura migración de
# dominio no requiera editar el instalador.
[[ "$(url_host 'https://platform.codered.lat/admin')" == 'platform.codered.lat' ]]
[[ "$(url_host 'http://192.168.18.124:8090')" == '192.168.18.124' ]]
[[ "$(url_host 'http://localhost:8090')" == 'localhost' ]]
[[ "$(cookie_domain_for_url 'https://platform.codered.lat')" == '.codered.lat' ]]
[[ "$(cookie_domain_for_url 'https://n8n.codered.lat/')" == '.codered.lat' ]]
[[ "$(cookie_domain_for_url 'http://192.168.18.124:8090')" == 'null' ]]
[[ "$(cookie_domain_for_url 'http://localhost:8090')" == 'null' ]]
[[ "$(cookie_domain_for_url 'https://codered.lat')" == 'null' ]]
[[ "$CODERED_DOMAIN" == 'codered.lat' ]]

printf 'BROKEN VALUE\n' >> "$ENV_FILE"
if validate_env_file >/dev/null 2>&1; then
    echo 'La validación debía rechazar una línea .env inválida.' >&2
    exit 1
fi
echo 'Installer ENV tests: OK'
