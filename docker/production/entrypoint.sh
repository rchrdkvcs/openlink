#!/bin/sh
set -e

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

php artisan package:discover --ansi

if [ "${OPENLINK_OPTIMIZE:-true}" = "true" ]; then
    php artisan config:cache --ansi
    php artisan route:cache --ansi
    php artisan view:cache --ansi
fi

if [ "${OPENLINK_MIGRATE:-false}" = "true" ]; then
    php artisan migrate --force --ansi
fi

exec "$@"
