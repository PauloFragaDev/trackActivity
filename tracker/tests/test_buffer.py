"""Tests básicos del buffer + storage en SQLite :memory:.

Sirven como smoke test del cimiento M1 sin requerir X11.
"""
from __future__ import annotations

import sqlite3
from datetime import datetime, timezone

import pytest

from tracker.buffer import SignalBuffer
from tracker.models import Signal
from tracker.storage import Storage


@pytest.fixture()
def storage(tmp_path):
    db = tmp_path / "test.db"
    # Crear schema mínimo requerido por el storage.
    con = sqlite3.connect(db)
    con.executescript("""
        CREATE TABLE activity_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            occurred_at DATETIME NOT NULL,
            source TEXT NOT NULL,
            app TEXT, title TEXT, repo_name TEXT, branch TEXT,
            modified_files INTEGER, url TEXT, subject TEXT, metadata TEXT
        );
        CREATE TABLE repositories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            path TEXT NOT NULL UNIQUE,
            project_id INTEGER,
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL
        );
    """)
    con.close()
    return Storage(db, wal_mode=False)


def test_validate_schema_ok(storage):
    storage.validate_schema()


def test_insert_single_signal(storage):
    s = Signal(
        source="window",
        occurred_at=datetime.now(timezone.utc),
        app="code",
        title="example",
    )
    n = storage.insert_events([s])
    assert n == 1


def test_buffer_flushes_on_threshold(storage):
    buf = SignalBuffer(storage, flush_every_seconds=999, max_pending=3)
    for i in range(3):
        buf.append(Signal(source="window", app=f"app-{i}", title="t"))
    # threshold debe disparar flush sincrónico
    con = sqlite3.connect(storage.path)
    count = con.execute("SELECT COUNT(*) FROM activity_events").fetchone()[0]
    con.close()
    assert count == 3


def test_buffer_manual_flush(storage):
    buf = SignalBuffer(storage, flush_every_seconds=999, max_pending=999)
    buf.append(Signal(source="idle", metadata={"state": "enter", "idle_seconds": 200}))
    assert buf.flush() == 1
    assert buf.flush() == 0  # nada pendiente
