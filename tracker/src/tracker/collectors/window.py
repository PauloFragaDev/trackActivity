"""Collector de ventana activa (X11)."""
from __future__ import annotations

import logging
from typing import Iterable

from tracker.collectors.base import Collector
from tracker.models import Signal
from tracker.utils.x11 import X11NotAvailable, get_active_window

logger = logging.getLogger(__name__)


class WindowCollector(Collector):
    name = "window"

    def __init__(self, interval_seconds: int = 15, backend: str = "xdotool"):
        self.interval_seconds = interval_seconds
        self.backend = backend
        self._last_signature: tuple[str, str] | None = None
        self._unavailable_logged = False

    def collect(self) -> Iterable[Signal]:
        try:
            win = get_active_window(backend=self.backend)
        except X11NotAvailable as exc:
            if not self._unavailable_logged:
                logger.warning("window collector disabled: %s", exc)
                self._unavailable_logged = True
            return []

        signature = (win.app, win.title)
        if signature == self._last_signature:
            return []
        self._last_signature = signature

        return [
            Signal(
                source="window",
                app=win.app,
                title=win.title,
                metadata={"wm_class": win.wm_class} if win.wm_class else {},
            )
        ]
