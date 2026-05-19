"""Wrappers para utilidades X11 (xdotool, wmctrl, screensaver)."""
from __future__ import annotations

import logging
import shutil
import subprocess

from tracker.models import WindowInfo

logger = logging.getLogger(__name__)


class X11NotAvailable(RuntimeError):
    """No hay binarios X11 disponibles o no se puede contactar el display."""


def _run(cmd: list[str], timeout: float = 1.0) -> str:
    try:
        out = subprocess.run(
            cmd,
            check=True,
            capture_output=True,
            text=True,
            timeout=timeout,
        )
    except FileNotFoundError as exc:
        raise X11NotAvailable(f"binary not found: {cmd[0]}") from exc
    except subprocess.CalledProcessError as exc:
        raise X11NotAvailable(f"{' '.join(cmd)} exited with {exc.returncode}") from exc
    except subprocess.TimeoutExpired as exc:
        raise X11NotAvailable(f"{' '.join(cmd)} timed out") from exc
    return out.stdout.strip()


def check_xdotool() -> bool:
    return shutil.which("xdotool") is not None


def check_wmctrl() -> bool:
    return shutil.which("wmctrl") is not None


def get_active_window_xdotool() -> WindowInfo:
    wid = _run(["xdotool", "getactivewindow"])
    title = _run(["xdotool", "getwindowname", wid])
    # `xdotool getwindowclassname` no existe en todas las versiones (p.ej. la
    # de Debian/Ubuntu estable). xprop WM_CLASS es la vía portable.
    cls = _xprop_class(wid)
    app = cls.lower() if cls else "unknown"
    return WindowInfo(app=app, title=title, wm_class=cls)


def _xprop_class(wid: str) -> str:
    """Devuelve la clase WM_CLASS de la ventana, o cadena vacía si no se puede."""
    try:
        out = _run(["xprop", "-id", wid, "WM_CLASS"])
    except X11NotAvailable:
        return ""

    # Formato típico: `WM_CLASS(STRING) = "instance", "Class"`
    parts = out.split("=", 1)
    if len(parts) != 2:
        return ""
    return parts[1].replace('"', "").strip().split(",")[-1].strip()


def get_active_window(backend: str = "xdotool") -> WindowInfo:
    if backend == "xdotool":
        return get_active_window_xdotool()
    raise X11NotAvailable(f"backend no soportado: {backend}")


def get_idle_seconds() -> int:
    """Segundos transcurridos desde el último input del usuario (X11)."""
    try:
        from Xlib import display
        from Xlib.ext import screensaver  # noqa: F401  (registra extensión)
    except ImportError as exc:
        raise X11NotAvailable("python-xlib no instalado") from exc

    try:
        d = display.Display()
        root = d.screen().root
        info = root.screensaver_query_info()
        return info.idle // 1000  # ms -> s
    except Exception as exc:  # noqa: BLE001
        raise X11NotAvailable(f"XScreenSaverQueryInfo failed: {exc}") from exc
