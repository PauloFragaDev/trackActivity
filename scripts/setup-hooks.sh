#!/usr/bin/env bash
# Instala los git hooks desde scripts/hooks/ en .git/hooks/.
# Se ejecuta sola tras `composer install` (ver dashboard/composer.json);
# no falla si no hay .git (build desde tarball/CI sin historial).
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC_DIR="$REPO_ROOT/scripts/hooks"
DST_DIR="$REPO_ROOT/.git/hooks"

if [ ! -d "$DST_DIR" ]; then
    echo "  (sin .git/hooks — no es un clon git, se omite la instalación de hooks)"
    exit 0
fi

for hook in "$SRC_DIR"/*; do
    name="$(basename "$hook")"
    cp "$hook" "$DST_DIR/$name"
    chmod +x "$DST_DIR/$name"
    echo "  instalado: $name"
done

echo "Hooks instalados. Cada 'git pull' reconstruirá automáticamente lo necesario."
