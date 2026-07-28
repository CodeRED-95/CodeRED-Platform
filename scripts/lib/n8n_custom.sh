#!/usr/bin/env bash

export LANG="${LANG:-C.UTF-8}"
export LC_ALL="${LC_ALL:-C.UTF-8}"

N8N_VERSION_DEFAULT="2.31.4"
N8N_DIR_DEFAULT="/opt/n8n"
N8N_PACKAGE_RELATIVE="packages/n8n-nodes-codered"

n8n_ok(){ echo "[OK] $*"; }
n8n_info(){ echo "[INFO] $*"; }
n8n_warn(){ echo "[WARN] $*"; }
n8n_die(){ echo "[ERROR] $*" >&2; return 1; }

n8n_get_env_from_file(){
    local file="$1" key="$2"
    grep -E "^${key}=" "$file" 2>/dev/null | head -n1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/' || true
}

n8n_set_env_value_raw(){
    local file="$1" key="$2" value="$3" tmp
    [[ "$value" != *$'\n'* && "$value" != *$'\r'* ]] || { n8n_die "Valor invalido para $key."; return 1; }
    mkdir -p "$(dirname "$file")"
    touch "$file"
    chmod 600 "$file" 2>/dev/null || true
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

n8n_random_token(){
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex 32
    else
        node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
    fi
}

ensure_codered_agent_local_token(){
    local platform_env="${1:-.env}" token
    token="$(n8n_get_env_from_file "$platform_env" CODERED_AGENT_LOCAL_API_TOKEN)"
    if [[ -z "$token" || "$token" == "Generate a random 32-character string and place it here" ]]; then
        token="$(n8n_random_token)"
        n8n_set_env_value_raw "$platform_env" CODERED_AGENT_LOCAL_API_TOKEN "$token"
        n8n_ok "Token local de CodeRED Agent generado correctamente. No se mostro el valor." >&2
    fi
    if (( ${#token} < 32 )); then
        n8n_die "CODERED_AGENT_LOCAL_API_TOKEN debe tener al menos 32 caracteres."
        return 1
    fi
    printf '%s' "$token"
}

write_n8n_dockerfile(){
    local target="$1"
    cat > "$target" <<'DOCKERFILE'
FROM node:24-alpine AS extension-build
WORKDIR /src
COPY n8n-nodes-codered/package*.json ./
RUN npm ci
COPY n8n-nodes-codered ./
RUN npm run build
RUN npm test
RUN npm prune --omit=dev

FROM docker.n8n.io/n8nio/n8n:2.31.4
USER root
COPY --from=extension-build /src /opt/n8n-nodes-codered
RUN chown -R node:node /opt/n8n-nodes-codered
USER node
DOCKERFILE
}

write_n8n_compose(){
    local target="$1"
    cat > "$target" <<'YAML'
services:
  n8n:
    image: codered-n8n:2.31.4
    build:
      context: .
      dockerfile: Dockerfile
    pull_policy: never
    container_name: ${N8N_CONTAINER_NAME:-codered-n8n}
    restart: unless-stopped
    env_file:
      - .env
    ports:
      - "127.0.0.1:5678:5678"
    volumes:
      - ./data:/home/node/.n8n
    networks:
      - codered-network
    environment:
      CODERED_API_BASE_URL: ${CODERED_API_BASE_URL:-}
      CODERED_N8N_SHARED_SECRET: ${CODERED_N8N_SHARED_SECRET:-}
      CODERED_TELEGRAM_ADMIN_CHAT_ID: ${CODERED_TELEGRAM_ADMIN_CHAT_ID:-}
      CODERED_AGENT_LOCAL_URL: ${CODERED_AGENT_LOCAL_URL:-http://codered-agent:5680}
      CODERED_AGENT_LOCAL_API_TOKEN: ${CODERED_AGENT_LOCAL_API_TOKEN}
      NODE_FUNCTION_ALLOW_BUILTIN: crypto
      N8N_BLOCK_ENV_ACCESS_IN_NODE: "false"
      N8N_CUSTOM_EXTENSIONS: /opt/n8n-nodes-codered
      N8N_VERSION: ${N8N_VERSION:-2.31.4}
    healthcheck:
      test: ["CMD", "node", "-e", "fetch('http://127.0.0.1:5678/healthz').then(r => process.exit(r.ok ? 0 : 1)).catch(() => process.exit(1))"]
      interval: 30s
      timeout: 10s
      retries: 10
      start_period: 60s
    security_opt:
      - no-new-privileges:true

networks:
  codered-network:
    external: true
    name: ${CODERED_DOCKER_NETWORK:-codered-platform_default}
YAML
}

copy_n8n_package(){
    local project_dir="$1" target_dir="$2" src="$project_dir/$N8N_PACKAGE_RELATIVE"
    [[ -f "$src/package.json" ]] || { n8n_die "No se encontro $src/package.json."; return 1; }
    rm -rf "$target_dir/n8n-nodes-codered"
    mkdir -p "$target_dir/n8n-nodes-codered"
    cp -a "$src/." "$target_dir/n8n-nodes-codered/"
}

ensure_n8n_files(){
    local project_dir="${1:-$PWD}" n8n_dir="${2:-${N8N_DIR:-$N8N_DIR_DEFAULT}}" staging
    mkdir -p "$n8n_dir" "$n8n_dir/data"
    staging="$n8n_dir/.staging-$(date +%Y%m%d-%H%M%S)-$$"
    rm -rf "$staging"
    mkdir -p "$staging"
    copy_n8n_package "$project_dir" "$staging"
    write_n8n_dockerfile "$staging/Dockerfile"
    write_n8n_compose "$staging/docker-compose.yml"
    [[ -f "$staging/Dockerfile" ]] || { n8n_die "No se genero Dockerfile de n8n."; return 1; }
    [[ -f "$staging/docker-compose.yml" ]] || { n8n_die "No se genero docker-compose.yml de n8n."; return 1; }
    [[ -f "$staging/n8n-nodes-codered/package.json" ]] || { n8n_die "No se copio n8n-nodes-codered/package.json."; return 1; }
    if [[ -f "$n8n_dir/.env" ]]; then cp "$n8n_dir/.env" "$n8n_dir/.env.backup-$(date +%Y%m%d-%H%M%S)"; fi
    rm -rf "$n8n_dir/n8n-nodes-codered"
    cp "$staging/Dockerfile" "$n8n_dir/Dockerfile"
    cp "$staging/docker-compose.yml" "$n8n_dir/docker-compose.yml"
    mv "$staging/n8n-nodes-codered" "$n8n_dir/n8n-nodes-codered"
    rm -rf "$staging"
    n8n_ok "Archivos de n8n preparados en $n8n_dir."
}

ensure_n8n_env(){
    local platform_env="${1:-.env}" n8n_env="${2:-${N8N_ENV_FILE:-/opt/n8n/.env}}" token app_url shared n8n_version local_url
    token="$(ensure_codered_agent_local_token "$platform_env")" || return 1
    app_url="$(n8n_get_env_from_file "$platform_env" APP_URL)"
    shared="$(n8n_get_env_from_file "$platform_env" N8N_SHARED_SECRET)"
    n8n_version="$(n8n_get_env_from_file "$platform_env" N8N_VERSION)"
    local_url="$(n8n_get_env_from_file "$platform_env" CODERED_AGENT_LOCAL_URL)"
    n8n_version="${n8n_version:-$N8N_VERSION_DEFAULT}"
    local_url="${local_url:-http://codered-agent:5680}"
    n8n_set_env_value_raw "$n8n_env" CODERED_AGENT_LOCAL_URL "$local_url"
    n8n_set_env_value_raw "$n8n_env" CODERED_AGENT_LOCAL_API_TOKEN "$token"
    n8n_set_env_value_raw "$n8n_env" N8N_VERSION "$n8n_version"
    n8n_set_env_value_raw "$n8n_env" CODERED_API_BASE_URL "$app_url"
    n8n_set_env_value_raw "$n8n_env" CODERED_N8N_SHARED_SECRET "$shared"
    n8n_ok "Entorno de n8n actualizado sin mostrar secretos."
}

validate_n8n_build_context(){
    local n8n_dir="${1:-${N8N_DIR:-$N8N_DIR_DEFAULT}}"
    [[ -f "$n8n_dir/Dockerfile" ]] || { n8n_die "Falta $n8n_dir/Dockerfile."; return 1; }
    [[ -f "$n8n_dir/docker-compose.yml" ]] || { n8n_die "Falta $n8n_dir/docker-compose.yml."; return 1; }
    [[ -f "$n8n_dir/n8n-nodes-codered/package.json" ]] || { n8n_die "Falta $n8n_dir/n8n-nodes-codered/package.json."; return 1; }
    if grep -Eq 'COPY[[:space:]]+(\.\./|/)' "$n8n_dir/Dockerfile"; then
        n8n_die "Dockerfile de n8n intenta copiar desde fuera del contexto."
        return 1
    fi
    if ! grep -q 'pull_policy:[[:space:]]*never' "$n8n_dir/docker-compose.yml"; then
        n8n_die "docker-compose.yml de n8n debe usar pull_policy: never."
        return 1
    fi
}

validate_n8n_token_env(){
    local n8n_env="${1:-${N8N_ENV_FILE:-/opt/n8n/.env}}" token
    token="$(n8n_get_env_from_file "$n8n_env" CODERED_AGENT_LOCAL_API_TOKEN)"
    if [[ -z "$token" ]]; then
        n8n_die "CODERED_AGENT_LOCAL_API_TOKEN no esta configurado en $n8n_env."
        return 1
    fi
    if (( ${#token} < 32 )); then
        n8n_die "CODERED_AGENT_LOCAL_API_TOKEN en $n8n_env debe tener al menos 32 caracteres."
        return 1
    fi
}

validate_n8n_compose(){
    local n8n_dir="${1:-${N8N_DIR:-$N8N_DIR_DEFAULT}}" output
    validate_n8n_build_context "$n8n_dir" || return 1
    validate_n8n_token_env "$n8n_dir/.env" || return 1
    output="$(cd "$n8n_dir" && docker compose --env-file .env config 2>&1)" || { printf '%s\n' "$output" >&2; return 1; }
    if grep -F 'Defaulting to a blank string' <<< "$output" >/dev/null; then
        printf '%s\n' "$output" >&2
        n8n_die "docker compose config de n8n contiene variables vacias."
        return 1
    fi
    docker compose --env-file "$n8n_dir/.env" --project-directory "$n8n_dir" config --services | grep -qx n8n || { n8n_die "El servicio Compose n8n no existe."; return 1; }
    n8n_ok "docker compose config de n8n validado."
}

build_n8n_image(){
    local n8n_dir="${1:-${N8N_DIR:-$N8N_DIR_DEFAULT}}"
    validate_n8n_compose "$n8n_dir" || return 1
    (cd "$n8n_dir" && docker compose --env-file .env build --no-cache n8n) || { n8n_die "No se pudo construir la imagen local codered-n8n:2.31.4."; return 1; }
    docker image inspect codered-n8n:2.31.4 >/dev/null || { n8n_die "La imagen codered-n8n:2.31.4 no existe despues del build."; return 1; }
    n8n_ok "Imagen local codered-n8n:2.31.4 construida."
}

start_n8n(){
    local n8n_dir="${1:-${N8N_DIR:-$N8N_DIR_DEFAULT}}"
    (cd "$n8n_dir" && docker compose --env-file .env up -d --force-recreate --no-deps n8n) || { n8n_die "No se pudo iniciar codered-n8n."; return 1; }
    n8n_ok "codered-n8n iniciado."
}

wait_for_n8n_health(){
    local attempts="${1:-40}"
    for ((i=1; i<=attempts; i++)); do
        if docker inspect -f '{{.State.Health.Status}}' codered-n8n 2>/dev/null | grep -qx healthy; then
            n8n_ok "codered-n8n healthy."
            return 0
        fi
        sleep 3
    done
    docker logs --tail=150 codered-n8n || true
    n8n_die "codered-n8n no quedo healthy dentro del tiempo esperado."
}

ensure_build_and_start_n8n(){
    local project_dir="${1:-$PWD}" n8n_dir="${2:-${N8N_DIR:-$N8N_DIR_DEFAULT}}" platform_env="${3:-$project_dir/.env}"
    ensure_n8n_files "$project_dir" "$n8n_dir" || return 1
    ensure_n8n_env "$platform_env" "$n8n_dir/.env" || return 1
    validate_n8n_build_context "$n8n_dir" || return 1
    build_n8n_image "$n8n_dir" || return 1
    start_n8n "$n8n_dir" || return 1
}
