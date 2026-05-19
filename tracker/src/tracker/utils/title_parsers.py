"""Parsers puros (sin I/O) que extraen info estructurada de titulos de ventanas.

Sin dependencias externas: faciles de testear y de iterar cuando los apps
cambian su formato de titulo.
"""
from __future__ import annotations

import re

# Caracteres comunes con los que vscode/chrome separan partes del titulo.
_SEPARATORS_RE = re.compile(r"\s+[-–—]\s+")

# Indicadores de cambios sin guardar al inicio
_UNSAVED_PREFIX_RE = re.compile(r"^[●•○\s]+")

_VSCODE_SUFFIXES = (
    "Visual Studio Code",
    "Visual Studio Code - Insiders",
    "Cursor",
    "Codium",
    "VSCodium",
    "Code",
)

_CHROME_SUFFIXES = (
    "Google Chrome",
    "Chromium",
    "Microsoft Edge",
    "Brave",
)


def parse_vscode_title(title: str) -> dict[str, str | None]:
    """Devuelve {'file': ..., 'repo': ...} extraidos del titulo.

    Formatos contemplados:
      "file.py - repo - Visual Studio Code"
      "● file.py - repo - Visual Studio Code"   (con indicador sin guardar)
      "file.py — repo — Cursor"                 (em-dash)
      "repo - Visual Studio Code"               (sin archivo abierto)
    """
    if not title:
        return {"file": None, "repo": None}

    clean = _UNSAVED_PREFIX_RE.sub("", title).strip()
    parts = [p.strip() for p in _SEPARATORS_RE.split(clean)]

    if not parts or parts[-1] not in _VSCODE_SUFFIXES:
        return {"file": None, "repo": None}

    if len(parts) >= 3:
        return {"file": parts[0], "repo": parts[-2]}
    if len(parts) == 2:
        # Solo "repo - Visual Studio Code"
        return {"file": None, "repo": parts[0]}
    return {"file": None, "repo": None}


def parse_chrome_title(title: str) -> dict[str, str | None]:
    """Devuelve {'page': ..., 'site_or_url_hint': ...} para titulos tipo
    "PAGE TITLE - Google Chrome" o GitHub-style.
    """
    if not title:
        return {"page": None, "site_or_url_hint": None}

    parts = [p.strip() for p in _SEPARATORS_RE.split(title)]
    if not parts or parts[-1] not in _CHROME_SUFFIXES:
        return {"page": None, "site_or_url_hint": None}

    # GitHub: "...· Issue #N · org/repo · GitHub - Google Chrome"
    # Quitamos el sufijo Chrome y trabajamos con el resto.
    rest = " - ".join(parts[:-1])
    # Si menciona "GitHub" o "Issue" o "Pull request" detectamos el repo
    site = None
    if "GitHub" in rest:
        site = _extract_github_repo(rest)
    elif re.search(r"\b[A-Z][A-Z0-9]+-\d+\b", rest):
        site = "Jira"

    return {
        "page": rest,
        "site_or_url_hint": site,
    }


def _extract_github_repo(text: str) -> str | None:
    # Busca "org/repo" rodeado por separadores o final
    m = re.search(r"\b([\w.-]+/[\w.-]+)\b", text)
    return m.group(1) if m else None


def infer_repo_from_path(path: str) -> str | None:
    """Infieren el nombre del repo desde una ruta absoluta tomando el componente
    inmediatamente debajo de los roots de desarrollo conocidos.

    Por ahora heuristica simple: si la ruta contiene "/Projects/X/..." o
    "/var/www/html/X/...", devuelve X. Si no, devuelve el ultimo componente.
    """
    if not path:
        return None
    parts = path.strip("/").split("/")
    for marker in ("Projects", "Work", "html"):
        if marker in parts:
            i = parts.index(marker)
            if i + 1 < len(parts):
                return parts[i + 1]
    # fallback
    return parts[-1] if parts else None
