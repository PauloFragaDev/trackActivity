"""Orquestación de collectors con APScheduler."""
from __future__ import annotations

import logging
from typing import Iterable

from apscheduler.schedulers.background import BackgroundScheduler

from tracker.buffer import SignalBuffer
from tracker.collectors.base import Collector

logger = logging.getLogger(__name__)


class Scheduler:
    """Wrapper minimal sobre BackgroundScheduler."""

    def __init__(self, buffer: SignalBuffer):
        self.buffer = buffer
        self._scheduler = BackgroundScheduler(timezone="UTC")
        self._collectors: list[Collector] = []
        self._failures: dict[str, int] = {}

    def register(self, collector: Collector) -> None:
        self._collectors.append(collector)
        self._scheduler.add_job(
            self._tick,
            "interval",
            seconds=collector.interval_seconds,
            id=collector.name,
            kwargs={"collector": collector},
            max_instances=1,
            coalesce=True,
        )
        logger.info(
            "collector registered: %s (interval=%ss)",
            collector.name,
            collector.interval_seconds,
        )

    def register_many(self, collectors: Iterable[Collector]) -> None:
        for c in collectors:
            self.register(c)

    def start(self) -> None:
        self._scheduler.start()

    def stop(self) -> None:
        self._scheduler.shutdown(wait=False)

    # ---- tick handler ----

    def _tick(self, collector: Collector) -> None:
        try:
            signals = list(collector.collect())
        except Exception:
            self._failures[collector.name] = self._failures.get(collector.name, 0) + 1
            logger.exception(
                "collector '%s' failed (consecutive=%d)",
                collector.name,
                self._failures[collector.name],
            )
            if self._failures[collector.name] >= 3:
                logger.error("disabling collector '%s' after 3 failures", collector.name)
                try:
                    self._scheduler.remove_job(collector.name)
                except Exception:
                    pass
            return

        self._failures[collector.name] = 0
        if signals:
            self.buffer.extend(signals)
            logger.debug("collector '%s' produced %d signal(s)", collector.name, len(signals))
