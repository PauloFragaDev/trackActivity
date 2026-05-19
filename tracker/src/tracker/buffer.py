"""Buffer en memoria con flush en batch a SQLite."""
from __future__ import annotations

import logging
import threading
import time
from collections import deque
from typing import Iterable

from tracker.models import Signal
from tracker.storage import Storage

logger = logging.getLogger(__name__)


class SignalBuffer:
    """Acumula señales en memoria y las descarga periódicamente."""

    def __init__(
        self,
        storage: Storage,
        *,
        flush_every_seconds: int = 30,
        max_pending: int = 200,
    ):
        self.storage = storage
        self.flush_every_seconds = flush_every_seconds
        self.max_pending = max_pending
        self._queue: deque[Signal] = deque()
        self._lock = threading.Lock()
        self._stop = threading.Event()
        self._thread: threading.Thread | None = None
        self._last_flush_ts = time.monotonic()

    # ---- API pública ----

    def append(self, signal: Signal) -> None:
        with self._lock:
            self._queue.append(signal)
            should_flush_now = len(self._queue) >= self.max_pending
        if should_flush_now:
            self.flush()

    def extend(self, signals: Iterable[Signal]) -> None:
        for s in signals:
            self.append(s)

    def flush(self) -> int:
        with self._lock:
            if not self._queue:
                return 0
            batch = list(self._queue)
            self._queue.clear()

        try:
            self.storage.insert_events(batch)
        except Exception:
            logger.exception("flush failed, returning %d signals to buffer", len(batch))
            with self._lock:
                # Re-encolar al frente para no perder eventos
                self._queue.extendleft(reversed(batch))
            return 0

        self._last_flush_ts = time.monotonic()
        return len(batch)

    # ---- thread de flush periódico ----

    def start(self) -> None:
        if self._thread is not None:
            return
        self._stop.clear()
        self._thread = threading.Thread(
            target=self._run, name="signal-buffer-flush", daemon=True
        )
        self._thread.start()

    def stop(self) -> None:
        self._stop.set()
        if self._thread is not None:
            self._thread.join(timeout=5)
        # último flush
        self.flush()

    def _run(self) -> None:
        while not self._stop.is_set():
            self._stop.wait(timeout=self.flush_every_seconds)
            self.flush()
