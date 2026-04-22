#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/var/www/cleanux}"
TARGET_DIR="${TARGET_DIR:-$PROJECT_DIR/storage/app/backups/storage}"
TIMESTAMP="$(date +%F-%H%M%S)"

mkdir -p "$TARGET_DIR"
tar -czf "$TARGET_DIR/cleanux-storage-$TIMESTAMP.tar.gz" -C "$PROJECT_DIR" storage/app public/storage

echo "Backup storage créé: $TARGET_DIR/cleanux-storage-$TIMESTAMP.tar.gz"
