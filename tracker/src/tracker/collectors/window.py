"""Collector de ventana activa (X11) con enriquecimiento heuristico.

Para cada ventana intentamos extraer informacion adicional util para el
scoring posterior:
  - VSCode/Cursor: el repo extraido del titulo (p.ej. "trackActivity")
  - Terminales (gnome-terminal, ghostty, alacritty, kitty, tilix, xterm):
    leemos /proc/<pid>/cwd del proceso de la ventana (o de su descendiente
    mas profundo, util cuando el pid es el container del terminal).
  - Chrome: detectamos el "site" cuando es GitHub/Jira para enriquecer la
    URL implicita.

Estos enriquecimientos viajan en `metadata` y `repo_name`. La logica de
scoring lo aprovecha desde el MappingResolver.
"""
from __future__ import annotations

import logging
from typing import Iterable

from tracker.collectors.base import Collector
from tracker.models import Signal
from tracker.utils.title_parsers import (
    infer_repo_from_path,
    parse_chrome_title,
    parse_vscode_title,
)
from tracker.utils.x11 import (
    X11NotAvailable,
    find_descendant_cwd,
    get_active_window,
    read_pid_cwd,
)

logger = logging.getLogger(__name__)

_CODE_APPS = {"code", "cursor", "codium", "vscodium"}
_TERMINAL_APPS = {
    "gnome-terminal", "com.mitchellh.ghostty", "ghostty", "alacritty",
    "kitty", "tilix", "xterm", "konsole",
}
_BROWSER_APPS = {"google-chrome", "chromium", "brave-browser"}


class WindowCollector(Collector):
    name = "window"

    def __init__(self, interval_seconds: int = 15, backend: str = "xdotool"):
        self.interval_seconds = interval_seconds
        self.backend = backend
        self._last_signature: tuple[str, str] | None = None
        self._unavailable_logged = False

    def collect(self) -> Iterable[Signal]:
        try:
            win = get_active_window(backend=self.backend)
        except X11NotAvailable as exc:
            if not self._unavailable_logged:
                logger.warning("window collector disabled: %s", exc)
                self._unavailable_logged = True
            return []

        signature = (win.app, win.title)
        if signature == self._last_signature:
            return []
        self._last_signature = signature

        metadata: dict = {}
        if win.wm_class:
            metadata["wm_class"] = win.wm_class
        if win.pid:
            metadata["pid"] = win.pid

        repo_name: str | None = None
        app_lower = (win.app or "").lower()

        # ─── VSCode / Cursor: extraer repo del titulo ───
        if app_lower in _CODE_APPS:
            parsed = parse_vscode_title(win.title or "")
            if parsed["repo"]:
                repo_name = parsed["repo"]
                metadata["editor_repo"] = parsed["repo"]
            if parsed["file"]:
                metadata["editor_file"] = parsed["file"]

        # ─── Terminales: leer cwd via /proc ───
        if app_lower in _TERMINAL_APPS and win.pid:
            cwd = find_descendant_cwd(win.pid) or read_pid_cwd(win.pid)
            if cwd:
                metadata["cwd_hint"] = cwd
                inferred = infer_repo_from_path(cwd)
                if inferred:
                    repo_name = repo_name or inferred
                    metadata["cwd_repo"] = inferred

        # ─── Browser: parse del titulo (GitHub/Jira hint) ───
        if app_lower in _BROWSER_APPS:
            parsed = parse_chrome_title(win.title or "")
            if parsed["site_or_url_hint"]:
                metadata["url_hint"] = parsed["site_or_url_hint"]

        return [
            Signal(
                source="window",
                app=win.app,
                title=win.title,
                repo_name=repo_name,
                metadata=metadata,
            )
        ]
