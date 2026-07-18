#!/usr/bin/env sh

set -eu

cd "$(dirname "$0")"

if [ -f /.dockerenv ] && [ "$(pwd)" = "/var/www/html" ]; then
    exec composer check
fi

docker compose up -d app postgres redis
exec docker compose exec -T app composer check
