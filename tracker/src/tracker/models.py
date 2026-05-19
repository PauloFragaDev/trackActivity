"""Modelos de datos compartidos por collectors, buffer y storage."""
from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime, timezone
from typing import Any


@dataclass(slots=True)
class Signal:
    """Una señal de actividad capturada por un collector.

    Mapea 1:1 con una fila de la tabla `activity_events`.
    """

    source: str                                # window | git | browser | thunderbird | idle
    occurred_at: datetime = field(default_factory=lambda: datetime.now(timezone.utc))
    app: str | None = None
    title: str | None = None
    repo_name: str | None = None
    branch: str | None = None
    modified_files: int | None = None
    url: str | None = None
    subject: str | None = None
    metadata: dict[str, Any] = field(default_factory=dict)

    def as_row(self) -> dict[str, Any]:
        """Serializa para INSERT en SQLite."""
        import json
        return {
            "occurred_at": self.occurred_at.replace(microsecond=0).isoformat(sep=" "),
            "source": self.source,
            "app": self.app,
            "title": self.title,
            "repo_name": self.repo_name,
            "branch": self.branch,
            "modified_files": self.modified_files,
            "url": self.url,
            "subject": self.subject,
            "metadata": json.dumps(self.metadata) if self.metadata else None,
        }


@dataclass(slots=True)
class WindowInfo:
    """Información de la ventana activa."""

    app: str
    title: str
    wm_class: str | None = None
    cwd_hint: str | None = None
