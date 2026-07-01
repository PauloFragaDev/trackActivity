#!/usr/bin/env bash
# Actualiza y redespliega el Kanban público en el VPS.
# Uso (en el VPS, dentro del checkout del repo): bash infra/deploy.sh
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

echo "[deploy] git pull..."
git -C "$REPO_ROOT" pull

echo "[deploy] docker compose build + up..."
cd "$REPO_ROOT/infra"
docker compose build
docker compose up -d

echo "[deploy] Listo. Logs: docker compose -f infra/docker-compose.yml logs -f app"
