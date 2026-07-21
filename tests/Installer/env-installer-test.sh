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
set_env DEV_ADMIN_EMAIL 'admin@codered.host'
set_env APP_URL 'https://platform.codered.host'
set_env APP_DEBUG 'false'
set_env RUC_IMPORT_TIMEOUT '7200'
grep -qx 'APP_NAME="CodeRED Platform"' "$ENV_FILE"
grep -qx 'DEV_ADMIN_NAME="Administrador CodeRED"' "$ENV_FILE"
grep -qx 'DEV_ADMIN_PASSWORD=ClaveSegura123!' "$ENV_FILE"
grep -qx 'DB_PASSWORD=PostgresSegura123!' "$ENV_FILE"
grep -qx 'DEV_ADMIN_EMAIL=admin@codered.host' "$ENV_FILE"
grep -qx 'APP_URL=https://platform.codered.host' "$ENV_FILE"
validate_env_file
printf 'DEV_ADMIN_PASSWORD="incorrecta"\n' >> "$ENV_FILE"
if validate_env_file >/dev/null 2>&1; then
    echo 'La validación debía rechazar una contraseña entre comillas.' >&2
    exit 1
fi
echo 'Installer ENV tests: OK'
