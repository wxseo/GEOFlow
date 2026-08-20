#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR=/var/www/html
DATA_DIR=/data
PGDATA=${DATA_DIR}/postgres
REDIS_DATA_DIR=${DATA_DIR}/redis
STORAGE_DIR=${DATA_DIR}/storage
ENV_FILE=${DATA_DIR}/.env
POSTGRES_BIN=/usr/lib/postgresql/15/bin

log() {
  printf '[geoflow-baota] %s\n' "$*"
}

fail() {
  printf '[geoflow-baota] error: %s\n' "$*" >&2
  exit 1
}

valid_identifier() {
  [[ "$1" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]]
}

read_env_value() {
  local key="$1"

  [ -f "${ENV_FILE}" ] || return 0
  sed -n "s/^${key}=//p" "${ENV_FILE}" | tail -n 1
}

persist_env_value() {
  local key="$1"
  local value="$2"
  local temporary_file

  temporary_file="$(mktemp "${DATA_DIR}/.env.XXXXXX")"
  awk -v key="${key}" -v value="${value}" '
    BEGIN { found = 0 }
    index($0, key "=") == 1 {
      print key "=" value
      found = 1
      next
    }
    { print }
    END {
      if (! found) {
        print key "=" value
      }
    }
  ' "${ENV_FILE}" > "${temporary_file}"
  mv "${temporary_file}" "${ENV_FILE}"
}

stop_bootstrap_services() {
  if [ -f /run/geoflow/redis-bootstrap.pid ]; then
    REDISCLI_AUTH="${REDIS_PASSWORD:-}" redis-cli -h 127.0.0.1 -p 6379 shutdown >/dev/null 2>&1 || true
    rm -f /run/geoflow/redis-bootstrap.pid
  fi

  if gosu postgres "${POSTGRES_BIN}/pg_ctl" -D "${PGDATA}" status >/dev/null 2>&1; then
    gosu postgres "${POSTGRES_BIN}/pg_ctl" -D "${PGDATA}" -m fast -w stop >/dev/null 2>&1 || true
  fi
}

cd "${APP_DIR}"

export DB_DATABASE="${DB_DATABASE:-geo_flow}"
export DB_USERNAME="${DB_USERNAME:-geo_user}"
export DB_PASSWORD="${DB_PASSWORD:-$(read_env_value DB_PASSWORD)}"
export DB_PASSWORD="${DB_PASSWORD:-$(php -r 'echo bin2hex(random_bytes(24));')}"
export DB_HOST=127.0.0.1
export DB_PORT=5432
export REDIS_HOST=127.0.0.1
export REDIS_PORT=6379
export REVERB_APP_SECRET="${REVERB_APP_SECRET:-$(read_env_value REVERB_APP_SECRET)}"
export REVERB_APP_SECRET="${REVERB_APP_SECRET:-$(php -r 'echo bin2hex(random_bytes(32));')}"

APP_URL_PARTS="$(php -r '
$parts = parse_url(getenv("APP_URL") ?: "http://localhost:8080");
$scheme = $parts["scheme"] ?? "http";
$host = $parts["host"] ?? "localhost";
$port = $parts["port"] ?? ($scheme === "https" ? 443 : 80);
echo $scheme." ".$host." ".$port;
')"
read -r APP_URL_SCHEME APP_URL_HOST APP_URL_PORT <<< "${APP_URL_PARTS}"
export REVERB_SCHEME="${REVERB_SCHEME:-${APP_URL_SCHEME}}"
export REVERB_HOST="${REVERB_HOST:-${APP_URL_HOST}}"
export REVERB_PORT="${REVERB_PORT:-${APP_URL_PORT}}"
export SESSION_SECURE_COOKIE="${SESSION_SECURE_COOKIE:-$([[ "${APP_URL_SCHEME}" = "https" ]] && printf true || printf false)}"

valid_identifier "${DB_DATABASE}" || fail 'DB_DATABASE only supports letters, numbers and underscores, and cannot start with a number.'
valid_identifier "${DB_USERNAME}" || fail 'DB_USERNAME only supports letters, numbers and underscores, and cannot start with a number.'
[[ "${REVERB_SCHEME}" = "http" || "${REVERB_SCHEME}" = "https" ]] || fail 'REVERB_SCHEME must be http or https.'

mkdir -p \
  "${PGDATA}" \
  "${REDIS_DATA_DIR}" \
  "${STORAGE_DIR}/app/public/uploads/images" \
  "${STORAGE_DIR}/app/private" \
  "${STORAGE_DIR}/app/tmp" \
  "${STORAGE_DIR}/framework/cache/data" \
  "${STORAGE_DIR}/framework/sessions" \
  "${STORAGE_DIR}/framework/views" \
  "${STORAGE_DIR}/logs" \
  bootstrap/cache \
  /run/geoflow \
  /run/postgresql

chown -R postgres:postgres "${PGDATA}"
chown postgres:postgres /run/postgresql
chown -R redis:redis "${REDIS_DATA_DIR}"
chown -R www-data:www-data "${STORAGE_DIR}" bootstrap/cache

PERSISTED_APP_KEY="$(read_env_value APP_KEY)"
if [ -n "${APP_KEY:-}" ]; then
  [[ "${APP_KEY}" == base64:* ]] || fail 'APP_KEY must start with base64: when explicitly configured.'
elif [ -n "${PERSISTED_APP_KEY}" ]; then
  APP_KEY="${PERSISTED_APP_KEY}"
  [[ "${APP_KEY}" == base64:* ]] || fail 'Persisted APP_KEY in /data/.env is invalid.'
  export APP_KEY
else
  APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
  export APP_KEY
  log 'Generated a persistent APP_KEY.'
fi

touch "${ENV_FILE}"
persist_env_value APP_KEY "${APP_KEY}"
persist_env_value DB_PASSWORD "${DB_PASSWORD}"
persist_env_value REVERB_APP_SECRET "${REVERB_APP_SECRET}"
PERSISTED_ADMIN_BASE_PATH="$(read_env_value ADMIN_BASE_PATH)"
persist_env_value ADMIN_BASE_PATH "${ADMIN_BASE_PATH:-${PERSISTED_ADMIN_BASE_PATH:-geo_admin}}"
chown www-data:www-data "${ENV_FILE}"
chmod 660 "${ENV_FILE}"
rm -f "${APP_DIR}/.env"
ln -s "${ENV_FILE}" "${APP_DIR}/.env"

cp /etc/geoflow/redis.conf /run/geoflow/redis.conf
if [ -n "${REDIS_PASSWORD:-}" ]; then
  [[ "${REDIS_PASSWORD}" != *[$'\r\n\t ']* ]] || fail 'REDIS_PASSWORD cannot contain whitespace.'
  printf 'requirepass %s\n' "${REDIS_PASSWORD}" >> /run/geoflow/redis.conf
fi
chown redis:redis /run/geoflow/redis.conf
chmod 600 /run/geoflow/redis.conf

if [ ! -s "${PGDATA}/PG_VERSION" ]; then
  log 'Initializing PostgreSQL data directory.'
  gosu postgres "${POSTGRES_BIN}/initdb" \
    --pgdata="${PGDATA}" \
    --username=postgres \
    --auth-local=trust \
    --auth-host=scram-sha-256 \
    --encoding=UTF8 \
    --locale=C.UTF-8 >/dev/null
  printf "listen_addresses = '127.0.0.1'\nport = 5432\n" >> "${PGDATA}/postgresql.conf"
fi

trap stop_bootstrap_services EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

log 'Starting PostgreSQL and Redis for application initialization.'
gosu postgres "${POSTGRES_BIN}/pg_ctl" -D "${PGDATA}" -w start >/dev/null
redis-server /run/geoflow/redis.conf --daemonize yes --pidfile /run/geoflow/redis-bootstrap.pid

gosu postgres psql \
  --username=postgres \
  --dbname=postgres \
  --set=ON_ERROR_STOP=1 \
  --set=db_user="${DB_USERNAME}" \
  --set=db_password="${DB_PASSWORD}" \
  --set=db_name="${DB_DATABASE}" <<'SQL'
SELECT format('CREATE ROLE %I LOGIN PASSWORD %L', :'db_user', :'db_password')
WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'db_user')
\gexec
SELECT format('ALTER ROLE %I WITH LOGIN PASSWORD %L', :'db_user', :'db_password')
\gexec
SELECT format('CREATE DATABASE %I OWNER %I', :'db_name', :'db_user')
WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = :'db_name')
\gexec
SQL

gosu postgres psql \
  --username=postgres \
  --dbname="${DB_DATABASE}" \
  --set=ON_ERROR_STOP=1 \
  --command='CREATE EXTENSION IF NOT EXISTS vector' >/dev/null

if [ "${AUTO_MIGRATE:-true}" = "true" ]; then
  log 'Running database migrations.'
  GEOFLOW_SECURITY_FRESH_INSTALL_CONFIRMED=true \
  GEOFLOW_SECURITY_UPGRADE_DRAIN_CONFIRMED=true \
    php artisan migrate --force --no-interaction
fi

if [ "${AUTO_INSTALL:-true}" = "true" ] && [ ! -f "${DATA_DIR}/.geoflow-installed" ]; then
  log 'Running first-install setup.'
  php artisan geoflow:install --no-interaction
  touch "${DATA_DIR}/.geoflow-installed"
fi

if [ "${AUTO_OPTIMIZE:-true}" = "true" ]; then
  log 'Optimizing Laravel caches.'
  php artisan optimize --no-interaction
fi

stop_bootstrap_services
trap - EXIT INT TERM

log 'Initialization complete; starting managed services.'
exec "$@"
