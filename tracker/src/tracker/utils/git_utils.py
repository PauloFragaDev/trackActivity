"""Helpers para descubrir y leer repositorios Git via pygit2."""
from __future__ import annotations

import logging
import os
from collections.abc import Iterator
from dataclasses import dataclass
from pathlib import Path
from typing import Any

logger = logging.getLogger(__name__)

try:
    import pygit2  # type: ignore
except ImportError:  # pragma: no cover
    pygit2 = None  # type: ignore[assignment]


class PygitNotAvailable(RuntimeError):
    """pygit2 no esta instalado o libgit2 no disponible."""


@dataclass(slots=True)
class RepoState:
    name: str
    path: Path
    branch: str | None
    modified_files: int
    latest_commit: dict[str, Any] | None
    ahead: int | None
    behind: int | None

    @property
    def signature(self) -> tuple[str | None, int, str | None]:
        """Para deduplicacion: si esto no cambia entre ticks, no emitas signal nueva."""
        return (
            self.branch,
            self.modified_files,
            self.latest_commit["hash"] if self.latest_commit else None,
        )


def require_pygit2() -> None:
    if pygit2 is None:
        raise PygitNotAvailable(
            "pygit2 no esta instalado en el venv. Ejecuta: pip install pygit2"
        )


def discover_repositories(
    roots: list[Path],
    max_depth: int = 3,
) -> Iterator[Path]:
    """Descubre repositorios Git bajo cada root, sin recursar dentro de uno encontrado.

    - Si la propia root es un repo, se devuelve y no se recursa.
    - max_depth se mide desde la root (inclusive). max_depth=0 solo mira la root.
    - Ignora directorios ocultos (excepto .git que ya implica el repo padre).
    """
    seen: set[Path] = set()
    for root in roots:
        if not root.exists() or not root.is_dir():
            logger.debug("git: root no existe o no es dir: %s", root)
            continue
        yield from _walk_for_repos(root.resolve(), max_depth, seen)


def _walk_for_repos(start: Path, max_depth: int, seen: set[Path]) -> Iterator[Path]:
    # Si start ya es un repo, devuelvelo y no recurses
    if _is_git_repo(start):
        if start not in seen:
            seen.add(start)
            yield start
        return

    if max_depth <= 0:
        return

    try:
        children = sorted(p for p in start.iterdir() if p.is_dir())
    except (PermissionError, OSError) as exc:
        logger.debug("git: no se puede listar %s: %s", start, exc)
        return

    for child in children:
        if child.name.startswith(".") and child.name != ".":
            continue
        yield from _walk_for_repos(child, max_depth - 1, seen)


def _is_git_repo(path: Path) -> bool:
    return (path / ".git").exists() or (path / "HEAD").is_file()


def read_repo_state(path: Path) -> RepoState | None:
    """Lee el estado actual de un repo. None si el repo es ilegible.

    No hace I/O de red. status() respeta .gitignore.
    """
    require_pygit2()

    try:
        repo = pygit2.Repository(str(path))
    except Exception as exc:  # noqa: BLE001
        logger.warning("git: no se puede abrir %s: %s", path, exc)
        return None

    branch = _current_branch(repo)
    modified = _count_modified(repo)
    latest = _latest_commit(repo)
    ahead, behind = _ahead_behind(repo, branch)

    return RepoState(
        name=path.name,
        path=path,
        branch=branch,
        modified_files=modified,
        latest_commit=latest,
        ahead=ahead,
        behind=behind,
    )


def _current_branch(repo: "pygit2.Repository") -> str | None:
    if repo.head_is_unborn:
        return None
    try:
        if repo.head_is_detached:
            return f"({str(repo.head.target)[:7]})"
        return repo.head.shorthand
    except Exception as exc:  # noqa: BLE001
        logger.debug("git: branch error %s: %s", repo.path, exc)
        return None


def _count_modified(repo: "pygit2.Repository") -> int:
    try:
        status = repo.status()
    except Exception as exc:  # noqa: BLE001
        logger.debug("git: status error %s: %s", repo.path, exc)
        return 0
    # Cualquier flag distinto a CURRENT cuenta como modificado.
    current_flag = getattr(pygit2, "GIT_STATUS_CURRENT", 0)
    return sum(1 for v in status.values() if v != current_flag)


def _latest_commit(repo: "pygit2.Repository") -> dict[str, Any] | None:
    if repo.head_is_unborn:
        return None
    try:
        commit = repo[repo.head.target]
    except Exception as exc:  # noqa: BLE001
        logger.debug("git: latest commit error: %s", exc)
        return None
    message = (commit.message or "").strip().split("\n", 1)[0]
    if len(message) > 200:
        message = message[:197] + "..."
    return {
        "hash": str(commit.id)[:7],
        "message": message,
        "ts": int(commit.commit_time),
        "author": commit.author.name if commit.author else None,
    }


def _ahead_behind(repo: "pygit2.Repository", branch: str | None) -> tuple[int | None, int | None]:
    if not branch or branch.startswith("("):
        return None, None
    try:
        local = repo.lookup_reference(f"refs/heads/{branch}")
        remote = repo.lookup_reference(f"refs/remotes/origin/{branch}")
        ahead, behind = repo.ahead_behind(local.target, remote.target)
        return ahead, behind
    except (KeyError, Exception):  # noqa: BLE001
        return None, None


def expand_paths(raw_paths: list[str]) -> list[Path]:
    out: list[Path] = []
    for raw in raw_paths:
        expanded = os.path.expandvars(os.path.expanduser(raw))
        out.append(Path(expanded))
    return out
