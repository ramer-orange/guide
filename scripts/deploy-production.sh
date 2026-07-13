#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")/.."

compose=(docker compose -f docker-compose.prod.yml)

if [ ! -f production.env ]; then
    echo "production.env is required before deployment." >&2
    exit 1
fi

"${compose[@]}" up -d --build

for _ in $(seq 1 60); do
    if "${compose[@]}" exec -T app php -v > /dev/null 2>&1; then
        break
    fi
    sleep 5
done

"${compose[@]}" exec -T app php artisan migrate --force --isolated
"${compose[@]}" exec -T app php artisan optimize

curl --fail --silent --show-error --retry 30 --retry-delay 5 --retry-all-errors http://127.0.0.1/up > /dev/null
