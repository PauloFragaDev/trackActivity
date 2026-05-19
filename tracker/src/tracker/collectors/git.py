"""Collector de estado de repositorios Git."""
from __future__ import annotations

import logging
from pathlib import Path
from typing import Iterable

from tracker.collectors.base import Collector
from tracker.models import Signal
from tracker.utils.git_utils import (
    PygitNotAvailable,
    RepoState,
    discover_repositories,
    expand_paths,
    read_repo_state,
    require_pygit2,
)

logger = logging.getLogger(__name__)


class GitCollector(Collector):
    """Captura el estado de los repos bajo `repositories_paths`.

    Por cada repo:
      - Upserta la fila de `repositories` (refresca `last_seen_at`).
      - Emite una `Signal(source='git', ...)` solo si algo cambio
        respecto al ultimo tick (dedupe por branch/modified/last_commit).
    """

    name = "git"

    def __init__(
        self,
        *,
        interval_seconds: int = 240,
        repositories_paths: list[str],
        max_depth: int = 3,
        storage=None,  # tracker.storage.Storage | None; opcional para dry-run/tests
    ):
        self.interval_seconds = interval_seconds
        self.repositories_paths = expand_paths(repositories_paths)
        self.max_depth = max_depth
        self.storage = storage
        self._last_signatures: dict[Path, tuple] = {}
        self._unavailable_logged = False

    def collect(self) -> Iterable[Signal]:
        try:
            require_pygit2()
        except PygitNotAvailable as exc:
            if not self._unavailable_logged:
                logger.warning("git collector disabled: %s", exc)
                self._unavailable_logged = True
            return

        repos = list(discover_repositories(self.repositories_paths, max_depth=self.max_depth))
        if not repos:
            logger.debug("git: no se encontro ningun repo bajo %s", self.repositories_paths)
            return

        for repo_path in repos:
            state = read_repo_state(repo_path)
            if state is None:
                continue

            # Upsert siempre (mantiene last_seen_at fresco aunque no haya cambios).
            if self.storage is not None:
                try:
                    self.storage.upsert_repository(name=state.name, path=str(state.path))
                except Exception:
                    logger.exception("git: upsert_repository fallo para %s", state.path)

            # Dedupe: si no cambio nada, no emitas signal nueva.
            sig = state.signature
            if self._last_signatures.get(repo_path) == sig:
                continue
            self._last_signatures[repo_path] = sig

            yield self._to_signal(state)

    @staticmethod
    def _to_signal(state: RepoState) -> Signal:
        return Signal(
            source="git",
            repo_name=state.name,
            branch=state.branch,
            modified_files=state.modified_files,
            metadata={
                "path": str(state.path),
                "latest_commit": state.latest_commit,
                "ahead": state.ahead,
                "behind": state.behind,
            },
        )
