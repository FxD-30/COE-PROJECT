#!/bin/sh
set -e

# No .env file is baked into the image: configuration comes from environment
# variables (see docker-compose.yml). Without an APP_KEY, generate one for this
# container so a plain `docker compose up` works on a fresh clone.
if [ -z "$APP_KEY" ]; then
    APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
    export APP_KEY
    echo "APP_KEY is not set; generated a temporary key. Run ./setup.sh to create a persistent one."
fi

# SQLite database lives in the image's database/ directory; create it on first start.
if [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    touch database/database.sqlite
    chown www-data:www-data database/database.sqlite
fi

php artisan migrate --force

# Seeders are idempotent, so this is safe on every start.
if [ "$SEED_DEMO_DATA" = "true" ]; then
    php artisan db:seed --force
fi

php artisan config:cache
php artisan route:cache

exec "$@"
