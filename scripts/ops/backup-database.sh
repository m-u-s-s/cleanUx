#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/var/www/cleanux}"
BACKUP_DIR="${BACKUP_DIR:-$PROJECT_DIR/storage/app/backups/database}"
TIMESTAMP="$(date +%F-%H%M%S)"

mkdir -p "$BACKUP_DIR"

: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"
: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"

mysqldump \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --user="$DB_USERNAME" \
  --password="$DB_PASSWORD" \
  --single-transaction \
  --quick \
  --lock-tables=false \
  "$DB_DATABASE" | gzip > "$BACKUP_DIR/cleanux-db-$TIMESTAMP.sql.gz"

echo "Backup DB créé: $BACKUP_DIR/cleanux-db-$TIMESTAMP.sql.gz"
