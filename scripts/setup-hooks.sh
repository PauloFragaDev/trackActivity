#!/usr/bin/env bash
# Instala los git hooks desde scripts/hooks/ en .git/hooks/.
# Ejecutar una vez tras un clone limpio: bash scripts/setup-hooks.sh
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC_DIR="$REPO_ROOT/scripts/hooks"
DST_DIR="$REPO_ROOT/.git/hooks"

for hook in "$SRC_DIR"/*; do
    name="$(basename "$hook")"
    cp "$hook" "$DST_DIR/$name"
    chmod +x "$DST_DIR/$name"
    echo "  instalado: $name"
done

echo "Hooks instalados. Cada 'git pull' reconstruirá automáticamente lo necesario."
