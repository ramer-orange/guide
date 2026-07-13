#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")/.."

backup_bucket="${BACKUP_BUCKET:-guide-production-backups}"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_file="$(mktemp "/tmp/guide-postgres-${timestamp}-XXXXXX.sql.gz")"
trap 'rm -f "$backup_file"' EXIT

docker compose -f docker-compose.prod.yml exec -T postgres sh -c \
    'PGPASSWORD="$POSTGRES_PASSWORD" pg_dump --clean --if-exists --no-owner --no-privileges -U "$POSTGRES_USER" "$POSTGRES_DB"' \
    | gzip > "$backup_file"

gzip -t "$backup_file"
aws s3 cp "$backup_file" "s3://${backup_bucket}/postgres/guide-${timestamp}.sql.gz" \
    --only-show-errors

echo "PostgreSQL backup uploaded: s3://${backup_bucket}/postgres/guide-${timestamp}.sql.gz"
