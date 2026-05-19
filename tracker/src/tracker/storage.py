"""Capa de persistencia SQLite. Solo INSERT y upsert; no altera schema."""
from __future__ import annotations

import logging
import sqlite3
import threading
from datetime import datetime
from pathlib import Path
from typing import Sequence

from tracker.models import Signal

logger = logging.getLogger(__name__)

REQUIRED_TABLES = ("activity_events", "repositories")

INSERT_EVENT_SQL = """
INSERT INTO activity_events
    (occurred_at, source, app, title, repo_name, branch, modified_files, url, subject, metadata)
VALUES
    (:occurred_at, :source, :app, :title, :repo_name, :branch, :modified_files, :url, :subject, :metadata)
"""

UPSERT_REPO_SQL = """
INSERT INTO repositories (name, path, project_id, first_seen_at, last_seen_at)
VALUES (:name, :path, NULL, :now, :now)
ON CONFLICT(path) DO UPDATE SET
    name = excluded.name,
    last_seen_at = excluded.last_seen_at
"""


class SchemaError(RuntimeError):
    """El schema de la BBDD no es el esperado."""


class Storage:
    """Wrapper fino sobre sqlite3 con conexiones por hilo."""

    def __init__(self, path: Path, *, wal_mode: bool = True, busy_timeout_ms: int = 5000):
        self.path = path
        self.wal_mode = wal_mode
        self.busy_timeout_ms = busy_timeout_ms
        self._local = threading.local()

    # ---- conexión por hilo ----

    def _conn(self) -> sqlite3.Connection:
        conn = getattr(self._local, "conn", None)
        if conn is None:
            self.path.parent.mkdir(parents=True, exist_ok=True)
            conn = sqlite3.connect(
                str(self.path),
                isolation_level=None,           # autocommit; BEGIN explícito
                check_same_thread=True,
                detect_types=sqlite3.PARSE_DECLTYPES,
            )
            conn.row_factory = sqlite3.Row
            self._configure(conn)
            self._local.conn = conn
        return conn

    def _configure(self, conn: sqlite3.Connection) -> None:
        cur = conn.cursor()
        if self.wal_mode:
            cur.execute("PRAGMA journal_mode = WAL")
        cur.execute("PRAGMA synchronous = NORMAL")
        cur.execute("PRAGMA foreign_keys = ON")
        cur.execute(f"PRAGMA busy_timeout = {self.busy_timeout_ms}")
        cur.execute("PRAGMA temp_store = MEMORY")
        cur.close()

    # ---- validación ----

    def validate_schema(self) -> None:
        conn = self._conn()
        cur = conn.cursor()
        for table in REQUIRED_TABLES:
            row = cur.execute(
                "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
                (table,),
            ).fetchone()
            if row is None:
                raise SchemaError(
                    f"Tabla requerida '{table}' no existe. "
                    "Ejecuta `php artisan migrate` desde dashboard/."
                )

    # ---- escritura ----

    def insert_events(self, signals: Sequence[Signal]) -> int:
        if not signals:
            return 0
        rows = [s.as_row() for s in signals]
        conn = self._conn()
        cur = conn.cursor()
        try:
            cur.execute("BEGIN")
            cur.executemany(INSERT_EVENT_SQL, rows)
            cur.execute("COMMIT")
        except sqlite3.Error:
            cur.execute("ROLLBACK")
            raise
        finally:
            cur.close()
        logger.debug("inserted %d events", len(rows))
        return len(rows)

    def upsert_repository(self, *, name: str, path: str) -> None:
        now = datetime.utcnow().replace(microsecond=0).isoformat(sep=" ")
        conn = self._conn()
        cur = conn.cursor()
        try:
            cur.execute(UPSERT_REPO_SQL, {"name": name, "path": path, "now": now})
        finally:
            cur.close()
