#!/usr/bin/env bash
set -Eeuo pipefail

n8n_db="${N8N_DB_DATABASE:-n8n}"
n8n_user="${N8N_DB_USERNAME:-n8n}"
n8n_password="${N8N_DB_PASSWORD:-}"

if [[ -z "$n8n_password" ]]; then
  echo "[WARN] N8N_DB_PASSWORD is empty; skipping n8n database bootstrap." >&2
  exit 0
fi

if [[ ! "$n8n_db" =~ ^[A-Za-z_][A-Za-z0-9_]*$ || ! "$n8n_user" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]]; then
  echo "[ERROR] N8N_DB_DATABASE and N8N_DB_USERNAME must be safe PostgreSQL identifiers." >&2
  exit 1
fi

password_sql="${n8n_password//\'/\'\'}"

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<SQL
DO \$\$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '$n8n_user') THEN
    CREATE ROLE "$n8n_user" LOGIN PASSWORD '$password_sql';
  ELSE
    ALTER ROLE "$n8n_user" WITH LOGIN PASSWORD '$password_sql';
  END IF;
END
\$\$;
SQL

if ! psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres -tAc "SELECT 1 FROM pg_database WHERE datname = '$n8n_db'" | grep -qx 1; then
  createdb --username "$POSTGRES_USER" --owner "$n8n_user" "$n8n_db"
fi

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$n8n_db" <<SQL
ALTER DATABASE "$n8n_db" OWNER TO "$n8n_user";
GRANT ALL PRIVILEGES ON DATABASE "$n8n_db" TO "$n8n_user";
GRANT ALL ON SCHEMA public TO "$n8n_user";
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO "$n8n_user";
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO "$n8n_user";
SQL
