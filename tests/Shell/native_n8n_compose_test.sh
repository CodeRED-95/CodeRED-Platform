#!/usr/bin/env bash
set -Eeuo pipefail

fail(){ echo "[FAIL] $*" >&2; exit 1; }
assert_contains(){ grep -qE "$2" "$1" || fail "$1 does not contain $2"; }
assert_not_contains(){ ! grep -qE "$2" "$1" || fail "$1 contains forbidden $2"; }
assert_contains_literal(){ grep -qF "$2" "$1" || fail "$1 does not contain literal $2"; }

assert_contains docker-compose.yml '^  codered-n8n:'
assert_contains docker-compose.yml 'dockerfile: docker/n8n/Dockerfile'
assert_contains docker-compose.yml 'image: codered-n8n:2\.31\.4'
assert_contains docker-compose.yml 'pull_policy: never'
assert_contains docker-compose.yml '127\.0\.0\.1:5678:5678'
assert_contains docker-compose.yml 'codered_n8n_data:/home/node/\.n8n'
assert_contains docker-compose.yml 'CODERED_AGENT_LOCAL_URL:.*codered-agent:5680'
assert_contains docker-compose.yml '^  codered_n8n_data:'
assert_contains docker-compose.yml 'POSTGRES_DB: \$\{POSTGRES_DB\}'
assert_contains docker-compose.yml 'POSTGRES_USER: \$\{POSTGRES_USER\}'
assert_contains docker-compose.yml 'POSTGRES_PASSWORD: \$\{POSTGRES_PASSWORD\}'
assert_contains_literal docker-compose.yml 'pg_isready -h 127.0.0.1 -U \"$${POSTGRES_USER}\" -d \"$${POSTGRES_DB}\" || exit 1'
assert_not_contains docker-compose.yml 'pg_isready .*codered'
assert_contains .env.example '^POSTGRES_DB=codered$'
assert_contains .env.example '^POSTGRES_USER=codered$'
assert_contains .env.example '^POSTGRES_PASSWORD='
assert_contains Install_CodeRED-Platform.sh 'sync_postgres_env'
assert_contains Install_CodeRED-Platform.sh 'wait_for_postgres'
assert_contains update.sh 'sync_postgres_env'
assert_contains update.sh 'wait_for_postgres'
assert_contains docker/n8n/Dockerfile '^FROM node:24-alpine AS extension-build$'
assert_contains docker/n8n/Dockerfile '^RUN npm ci$'
assert_contains docker/n8n/Dockerfile '^RUN npm run build$'
assert_contains docker/n8n/Dockerfile '^RUN npm test$'
assert_contains docker/n8n/Dockerfile '^RUN npm prune --omit=dev$'
assert_contains docker/n8n/Dockerfile '/opt/n8n-nodes-codered'
assert_not_contains Install_CodeRED-Platform.sh '/opt/n8n'
assert_not_contains update.sh '/opt/n8n'
assert_not_contains CodeRED.sh '/opt/n8n'
assert_not_contains .env.example 'N8N_ENV_FILE=/opt/n8n/.env'

printf '[OK] native n8n compose tests passed.\n'
