#!/usr/bin/env bash
set -Eeuo pipefail

source scripts/lib/n8n_custom.sh

fail(){ echo "[FAIL] $*" >&2; exit 1; }
assert_file(){ [[ -f "$1" ]] || fail "missing file $1"; }
assert_dir(){ [[ -d "$1" ]] || fail "missing dir $1"; }
assert_contains(){ grep -qE "$2" "$1" || fail "$1 does not contain $2"; }
assert_not_contains(){ ! grep -qE "$2" "$1" || fail "$1 contains forbidden $2"; }

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
project="$work/project"
n8n_dir="$work/opt-n8n"
mkdir -p "$project/packages"
cp -a packages/n8n-nodes-codered "$project/packages/"
printf 'APP_URL=https://platform.test\nN8N_SHARED_SECRET=legacy-secret\nN8N_VERSION=2.31.4\nCODERED_AGENT_LOCAL_API_TOKEN=%s\nCODERED_AGENT_LOCAL_URL=http://codered-agent:5680\n' "$(printf 'a%.0s' {1..64})" > "$project/.env"
mkdir -p "$n8n_dir/data"
printf 'persistent' > "$n8n_dir/data/workflows.sqlite"

ensure_n8n_files "$project" "$n8n_dir" >/dev/null
ensure_n8n_env "$project/.env" "$n8n_dir/.env" >/dev/null

assert_file "$n8n_dir/Dockerfile"
assert_file "$n8n_dir/docker-compose.yml"
assert_file "$n8n_dir/n8n-nodes-codered/package.json"
assert_file "$n8n_dir/data/workflows.sqlite"
assert_contains "$n8n_dir/Dockerfile" '^FROM node:24-alpine AS extension-build$'
assert_contains "$n8n_dir/Dockerfile" '^FROM docker\.n8n\.io/n8nio/n8n:2\.31\.4$'
assert_contains "$n8n_dir/Dockerfile" '^COPY n8n-nodes-codered/package\*\.json \./$'
assert_not_contains "$n8n_dir/Dockerfile" 'COPY[[:space:]]+\.\./'
assert_not_contains "$n8n_dir/Dockerfile" 'COPY[[:space:]]+/'
assert_contains "$n8n_dir/docker-compose.yml" 'image: codered-n8n:2\.31\.4'
assert_contains "$n8n_dir/docker-compose.yml" 'pull_policy: never'
assert_contains "$n8n_dir/docker-compose.yml" 'dockerfile: Dockerfile'
assert_contains "$n8n_dir/docker-compose.yml" 'CODERED_AGENT_LOCAL_URL:.*codered-agent:5680'
assert_contains "$n8n_dir/docker-compose.yml" 'CODERED_AGENT_LOCAL_API_TOKEN:'
assert_contains "$n8n_dir/docker-compose.yml" 'N8N_CUSTOM_EXTENSIONS: /opt/n8n-nodes-codered'
assert_contains "$n8n_dir/.env" '^CODERED_AGENT_LOCAL_API_TOKEN=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa$'
assert_contains "$n8n_dir/.env" '^CODERED_AGENT_LOCAL_URL=http://codered-agent:5680$'
assert_contains "$n8n_dir/.env" '^CODERED_API_BASE_URL=https://platform.test$'
validate_n8n_build_context "$n8n_dir"
validate_n8n_token_env "$n8n_dir/.env"

rm "$n8n_dir/Dockerfile"
if validate_n8n_build_context "$n8n_dir" >/dev/null 2>&1; then
    fail 'validate_n8n_build_context should fail when Dockerfile is missing'
fi

printf '[OK] n8n custom install tests passed.\n'
