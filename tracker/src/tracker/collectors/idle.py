"""Collector de inactividad. Solo emite señal en transiciones."""
from __future__ import annotations

import logging
from typing import Iterable

from tracker.collectors.base import Collector
from tracker.models import Signal
from tracker.utils.x11 import X11NotAvailable, get_idle_seconds

logger = logging.getLogger(__name__)


class IdleCollector(Collector):
    name = "idle"

    def __init__(self, interval_seconds: int = 30, threshold_seconds: int = 180):
        self.interval_seconds = interval_seconds
        self.threshold_seconds = threshold_seconds
        self._was_idle = False
        self._unavailable_logged = False

    def collect(self) -> Iterable[Signal]:
        try:
            idle = get_idle_seconds()
        except X11NotAvailable as exc:
            if not self._unavailable_logged:
                logger.warning("idle collector disabled: %s", exc)
                self._unavailable_logged = True
            return []

        is_idle = idle >= self.threshold_seconds

        if is_idle == self._was_idle:
            return []

        state = "enter" if is_idle else "exit"
        self._was_idle = is_idle

        return [
            Signal(
                source="idle",
                metadata={"state": state, "idle_seconds": int(idle)},
            )
        ]
