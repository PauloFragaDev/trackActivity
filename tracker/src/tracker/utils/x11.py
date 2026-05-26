"""Wrappers para utilidades X11 (xdotool, wmctrl, screensaver) y /proc."""
from __future__ import annotations

import logging
import os
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

    pid: int | None = None
    try:
        raw = _run(["xdotool", "getwindowpid", wid])
        pid = int(raw) if raw else None
    except (X11NotAvailable, ValueError):
        pid = None

    return WindowInfo(app=app, title=title, wm_class=cls, pid=pid)


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


# ──────────────────────────────────────────────
# /proc helpers
# ──────────────────────────────────────────────

def read_pid_cwd(pid: int) -> str | None:
    """Devuelve la cwd del proceso `pid`, o None si no se puede leer."""
    try:
        return os.readlink(f"/proc/{int(pid)}/cwd")
    except (FileNotFoundError, PermissionError, ProcessLookupError, OSError):
        return None


def _read_proc_comm(pid: int) -> str | None:
    """Nombre corto del proceso (`/proc/<pid>/comm`), o None."""
    try:
        with open(f"/proc/{int(pid)}/comm", encoding="utf-8") as f:
            return f.read().strip() or None
    except (FileNotFoundError, PermissionError, ProcessLookupError, OSError):
        return None


def _read_proc_cmdline(pid: int) -> str | None:
    """Cmdline completa, con NULs reemplazados por espacios."""
    try:
        with open(f"/proc/{int(pid)}/cmdline", "rb") as f:
            raw = f.read()
    except (FileNotFoundError, PermissionError, ProcessLookupError, OSError):
        return None
    cleaned = raw.replace(b"\0", b" ").strip().decode("utf-8", errors="replace")
    return cleaned or None


# Procesos que envuelven (no son lo que el usuario está ejecutando realmente).
# Los saltamos al buscar el "proceso en foreground" del descendiente.
_FOREGROUND_SKIP = frozenset({
    # shells
    "bash", "zsh", "fish", "sh", "dash", "ksh", "tcsh", "csh",
    # multiplexers
    "tmux", "screen",
    # wrappers
    "sudo", "nohup", "env", "time", "doas", "pkexec",
})


def find_descendant_info(pid: int, max_depth: int = 4) -> tuple[str | None, str | None]:
    """Recorre el árbol de procesos descendientes (BFS) del `pid` y devuelve:

      - `cwd`: la cwd no trivial más profunda accesible.
      - `cmdline`: la cmdline del descendiente más profundo que no sea una
        shell/multiplexer/wrapper — útil para saber qué se está ejecutando
        en primer plano dentro de un terminal (claude, vim, pytest…).

    Si el árbol solo contiene shells, `cmdline` queda en None.
    """
    visited: set[int] = set()
    frontier: list[tuple[int, int]] = [(int(pid), 0)]
    best_cwd: str | None = None
    best_cmd: str | None = None
    root_pid = int(pid)

    while frontier:
        current, depth = frontier.pop(0)
        if current in visited or depth > max_depth:
            continue
        visited.add(current)

        cwd = read_pid_cwd(current)
        if cwd and cwd not in ("/", os.path.expanduser("~")):
            best_cwd = cwd

        # No registramos el cmdline del propio terminal (root_pid); buscamos
        # qué corre DENTRO. Y saltamos shells y wrappers conocidos.
        if current != root_pid:
            comm = _read_proc_comm(current)
            if comm and comm.lower() not in _FOREGROUND_SKIP:
                cmd = _read_proc_cmdline(current)
                if cmd:
                    best_cmd = cmd

        # BFS sobre hijos (suma de todos los threads del proceso).
        try:
            task_dir = f"/proc/{current}/task"
            for tid in os.listdir(task_dir):
                children_file = f"{task_dir}/{tid}/children"
                try:
                    raw = open(children_file).read().strip()
                except (FileNotFoundError, PermissionError, OSError):
                    continue
                for child in raw.split():
                    try:
                        frontier.append((int(child), depth + 1))
                    except ValueError:
                        continue
        except (FileNotFoundError, PermissionError, OSError):
            continue

    return best_cwd, best_cmd


def find_descendant_cwd(pid: int, max_depth: int = 4) -> str | None:
    """Compat: devuelve solo la cwd del descendiente más profundo."""
    return find_descendant_info(pid, max_depth)[0]
