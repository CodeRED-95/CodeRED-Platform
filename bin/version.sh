#!/usr/bin/env bash
# Lee la versión de CodeRED Platform desde la fuente única de verdad:
# composer.json > extra.version
#
# Pensado para scripts (update.sh, instaladores, CI) y para consultarla a mano
# sin levantar Laravel ni entrar en un contenedor:
#
#   ./bin/version.sh          -> 3.5.0
#   ./bin/version.sh --source -> /ruta/al/composer.json
#
# No depende de PHP ni de jq: los usa si están disponibles y, si no, extrae el
# valor con herramientas POSIX.
set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
COMPOSER_JSON="${CODERED_COMPOSER_JSON:-$(dirname -- "$SCRIPT_DIR")/composer.json}"

if [[ "${1:-}" == "--source" ]]; then
    echo "$COMPOSER_JSON"
    exit 0
fi

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
    cat <<'USAGE'
Uso: bin/version.sh [--source|--help]

  (sin argumentos)  Imprime la versión actual (MAJOR.MINOR.PATCH).
  --source          Imprime la ruta del archivo que define la versión.

Para incrementarla:
  docker compose exec -T app php artisan app:bump-version {major|minor|patch}
USAGE
    exit 0
fi

if [[ ! -f "$COMPOSER_JSON" ]]; then
    echo "No se encontró composer.json en: $COMPOSER_JSON" >&2
    exit 1
fi

read_version() {
    if command -v php >/dev/null 2>&1; then
        php -r '
            $data = json_decode(file_get_contents($argv[1]), true);
            echo $data["extra"]["version"] ?? "";
        ' "$COMPOSER_JSON" 2>/dev/null && return 0
    fi

    if command -v jq >/dev/null 2>&1; then
        jq -r '.extra.version // empty' "$COMPOSER_JSON" 2>/dev/null && return 0
    fi

    # Respaldo POSIX: se acota al bloque "extra" para no capturar la versión de
    # una dependencia con el mismo nombre de clave.
    sed -n '/"extra"[[:space:]]*:/,/}/p' "$COMPOSER_JSON" \
        | grep -o '"version"[[:space:]]*:[[:space:]]*"[^"]*"' \
        | head -1 \
        | sed 's/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/'
}

VERSION="$(read_version | tr -d '[:space:]')"

if [[ -z "$VERSION" ]]; then
    echo "No se pudo leer extra.version de $COMPOSER_JSON" >&2
    exit 1
fi

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([-+][0-9A-Za-z.-]+)*$ ]]; then
    echo "La versión leída no sigue SemVer MAJOR.MINOR.PATCH: $VERSION" >&2
    exit 1
fi

echo "$VERSION"
