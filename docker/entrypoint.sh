#!/bin/sh
set -e

# APP_KEY va baza volume'da turadi: konteyner qayta yaratilsa ham sessiya va ma'lumot saqlanadi.
if [ ! -f /data/app.key ]; then
    php -r 'echo "base64:".base64_encode(random_bytes(32));' > /data/app.key
fi
export APP_KEY="$(cat /data/app.key)"

first_run=0
if [ ! -f "$DB_DATABASE" ]; then
    touch "$DB_DATABASE"
    first_run=1
fi

php artisan migrate --force

if [ "$first_run" = "1" ]; then
    php artisan db:seed --force
fi

exec "$@"
