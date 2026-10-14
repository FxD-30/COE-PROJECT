#!/bin/sh
set -e

# SQLite database lives in the image's database/ directory; create it on first start.
if [ "$DB_CONNECTION" = "sqlite" ] || [ -z "$DB_CONNECTION" ]; then
    touch database/database.sqlite
    chown www-data:www-data database/database.sqlite
fi

php artisan migrate --force
php artisan config:cache
php artisan route:cache

exec "$@"
