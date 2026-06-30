#!/usr/bin/env bash
# Deploy manual completo: pull + composer + build + cache.
# Uso: bash deploy.sh
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")" && pwd)"
APP_DIR="$REPO_ROOT/dashboard"

echo "[deploy] git pull..."
git -C "$REPO_ROOT" pull

echo "[deploy] composer install..."
cd "$APP_DIR" && composer install --no-dev --optimize-autoloader --quiet

echo "[deploy] npm run build..."
cd "$APP_DIR" && npm run build

echo "[deploy] limpiando caché PHP..."
cd "$APP_DIR" && php artisan optimize:clear --quiet

echo "[deploy] Listo."
