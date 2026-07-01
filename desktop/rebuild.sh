#!/usr/bin/env bash
# Actualiza el repo y recompila + reinstala la app de escritorio (Tauri).
# Uso: bash desktop/rebuild.sh
#
# El dashboard (PHP/JS) se autoactualiza solo con git pull (ver
# scripts/hooks/post-merge). El binario de la app de escritorio NO — es un
# ejecutable compilado, así que cambios en desktop/src-tauri/ (config de la
# ventana, bandeja, etc.) solo llegan reconstruyéndolo. Este script hace eso.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DESKTOP_DIR="$REPO_ROOT/desktop"
BUNDLE_DIR="$DESKTOP_DIR/src-tauri/target/release/bundle"

echo "[rebuild] git pull..."
git -C "$REPO_ROOT" pull

echo "[rebuild] cargo tauri build (puede tardar varios minutos)..."
cd "$DESKTOP_DIR" && cargo tauri build

DEB="$(find "$BUNDLE_DIR/deb" -name '*.deb' -print -quit 2>/dev/null || true)"
APPIMAGE="$(find "$BUNDLE_DIR/appimage" -name '*.AppImage' -print -quit 2>/dev/null || true)"

if [ -n "$DEB" ]; then
    echo "[rebuild] Instalando $(basename "$DEB")..."
    sudo dpkg -i "$DEB" || sudo apt-get install -f -y
    echo "[rebuild] Listo. Cierra trackActivity del todo (bandeja → Salir) y vuelve a abrirlo para usar la nueva versión."
elif [ -n "$APPIMAGE" ]; then
    echo "[rebuild] AppImage generado en: $APPIMAGE"
    echo "[rebuild] Sustituye a mano tu copia anterior por este fichero y vuelve a abrirlo."
else
    echo "[rebuild] No se encontró ningún paquete generado — revisa la salida de 'cargo tauri build' arriba." >&2
    exit 1
fi
