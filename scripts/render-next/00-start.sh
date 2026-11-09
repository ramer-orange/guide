#!/bin/sh
set -eu

cd /var/www/html

php scripts/check-php-runtime.php
node frontend/scripts/check-node-runtime.mjs
php-fpm -v | head -n 1


port="${PORT:-10000}"
case "$port" in
    ''|*[!0-9]*)
        echo "PORT must be a numeric TCP port" >&2
        exit 1
        ;;
esac

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    bootstrap/cache \
    /run/nginx \
    /run/php

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

php artisan storage:link || true
# Render は起動時に migrate する。AWS（ECS）では専用の Migration タスクだけが
# migrate するため、RUN_MIGRATIONS_ON_START=false で無効にする。
if [ "${RUN_MIGRATIONS_ON_START:-true}" = "true" ]; then
    php artisan migrate --force
fi
php artisan config:cache

sed -i "s/__PORT__/${port}/g" /etc/nginx/http.d/render-next.conf
nginx -t

exec /usr/bin/supervisord -n -c /etc/supervisord.conf
