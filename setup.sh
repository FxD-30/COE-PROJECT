#!/usr/bin/env bash
# Bring up the whole stack (Postgres, Laravel API, Nuxt frontend) from a fresh clone.
#
#   ./setup.sh            build and start everything, wait until it's healthy
#   ./setup.sh --reset    same, but first delete the database volume (all data is lost)
set -euo pipefail

cd "$(dirname "$0")"

info()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn()  { printf '\033[1;33mwarning:\033[0m %s\n' "$*"; }
fail()  { printf '\033[1;31merror:\033[0m %s\n' "$*" >&2; exit 1; }

# 1. Docker -------------------------------------------------------------------
command -v docker >/dev/null 2>&1 || fail "Docker is not installed. Get it from https://docs.docker.com/get-docker/"
docker info >/dev/null 2>&1 || fail "Docker is installed but not running (or you lack permission). Start Docker and try again."

if docker compose version >/dev/null 2>&1; then
    COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE=(docker-compose)
else
    fail "Docker Compose is not available. Install the Compose plugin: https://docs.docker.com/compose/install/"
fi

# 2. .env -----------------------------------------------------------------------
if [ ! -f .env ]; then
    info "Creating .env from .env.example"
    cp .env.example .env
fi

if ! grep -qE '^APP_KEY=.+' .env; then
    info "Generating APP_KEY"
    key="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
    if grep -q '^APP_KEY=' .env; then
        sed -i.bak "s|^APP_KEY=.*|APP_KEY=${key}|" .env && rm -f .env.bak
    else
        printf 'APP_KEY=%s\n' "$key" >> .env
    fi
fi

# shellcheck disable=SC1091
set -a; . ./.env; set +a
FRONTEND_PORT=${FRONTEND_PORT:-3000}
BACKEND_PORT=${BACKEND_PORT:-8000}
POSTGRES_PORT=${POSTGRES_PORT:-5432}

# 3. Optional reset -------------------------------------------------------------
if [ "${1:-}" = "--reset" ]; then
    warn "Removing containers and the database volume (all data will be deleted)"
    "${COMPOSE[@]}" down -v --remove-orphans
fi

# 4. Ports ----------------------------------------------------------------------
port_in_use() {
    if command -v ss >/dev/null 2>&1; then
        ss -ltnH "sport = :$1" 2>/dev/null | grep -q .
    elif command -v lsof >/dev/null 2>&1; then
        lsof -iTCP:"$1" -sTCP:LISTEN >/dev/null 2>&1
    else
        return 1
    fi
}

# Ports already held by this project's own containers are fine.
running=$("${COMPOSE[@]}" ps --services --status running 2>/dev/null || true)
for entry in "frontend:FRONTEND_PORT:$FRONTEND_PORT" "backend:BACKEND_PORT:$BACKEND_PORT" "postgres:POSTGRES_PORT:$POSTGRES_PORT"; do
    IFS=: read -r service var port <<<"$entry"
    if ! grep -qx "$service" <<<"$running" && port_in_use "$port"; then
        fail "Port $port is already in use (needed by $service). Free it, or set a different $var in .env and run ./setup.sh again."
    fi
done

# 5. Build and start ------------------------------------------------------------
info "Building and starting containers (the first build takes a few minutes)"
"${COMPOSE[@]}" up -d --build

info "Waiting for the API and frontend to become ready"
deadline=$((SECONDS + 300))
until curl -fsS "http://localhost:${BACKEND_PORT}/up" >/dev/null 2>&1 \
   && curl -fsS "http://localhost:${FRONTEND_PORT}/" >/dev/null 2>&1; do
    if [ $SECONDS -ge $deadline ]; then
        "${COMPOSE[@]}" ps
        fail "The stack did not become ready within 5 minutes. Check the logs: ${COMPOSE[*]} logs backend frontend"
    fi
    sleep 3
done

cat <<EOF

$(printf '\033[1;32m')Test Mart is running.$(printf '\033[0m')

  Frontend   http://localhost:${FRONTEND_PORT}
  API        http://localhost:${BACKEND_PORT}/api/products
  Postgres   localhost:${POSTGRES_PORT}  (db: ${POSTGRES_DB:-coe}, user: ${POSTGRES_USER:-coe})

  Logs       ${COMPOSE[*]} logs -f
  Stop       ${COMPOSE[*]} down
  Reset DB   ./setup.sh --reset
EOF
