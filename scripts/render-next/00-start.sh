#!/bin/sh
set -eu

cd /var/www/html

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
php artisan migrate --force
php artisan config:cache
php artisan view:cache

sed -i "s/__PORT__/${port}/g" /etc/nginx/http.d/render-next.conf
nginx -t

exec /usr/bin/supervisord -n -c /etc/supervisord.conf
