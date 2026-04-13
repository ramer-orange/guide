#!/bin/sh
set -eu

cd /var/www/html

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

php artisan package:discover --ansi

if [ ! -L public/storage ]; then
    php artisan storage:link
fi

if [ -n "${APP_KEY:-}" ]; then
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan event:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
else
    echo "Skipping cache warmup because APP_KEY is not set."
fi

if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
