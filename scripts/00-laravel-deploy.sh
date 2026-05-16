#!/usr/bin/env bash
set -e

cd /var/www/html

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    bootstrap/cache

chmod -R ug+rwX storage bootstrap/cache

php artisan storage:link || true
php artisan migrate --force
php artisan db:seed --class=ProductionDemoSeeder --force
php artisan config:cache
php artisan view:cache
