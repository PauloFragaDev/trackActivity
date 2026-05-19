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
        """Serializa para INSERT en SQLite.

        Convención del proyecto: datetimes en SQLite siempre en UTC y sin offset
        explícito (formato 'YYYY-MM-DD HH:MM:SS'). La conversión a zona local se
        hace en el dashboard (config 'tracker.display_timezone'). Esto evita que
        las comparaciones por rango fallen al mezclar formatos con/sin offset.
        """
        import json
        utc_dt = self.occurred_at.astimezone(timezone.utc).replace(microsecond=0, tzinfo=None)
        return {
            "occurred_at": utc_dt.isoformat(sep=" "),
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
