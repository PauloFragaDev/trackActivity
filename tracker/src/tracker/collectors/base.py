"""Contrato base para todos los collectors."""
from __future__ import annotations

from abc import ABC, abstractmethod
from typing import Iterable

from tracker.models import Signal


class Collector(ABC):
    """Un collector captura señales periódicamente.

    El Scheduler invoca ``collect()`` cada ``interval_seconds``.
    """

    name: str
    interval_seconds: int

    @abstractmethod
    def collect(self) -> Iterable[Signal]:
        """Devuelve 0..N señales capturadas en este tick.

        Implementaciones deben:
          - ser idempotentes y rápidas (< 50 ms ideal).
          - no lanzar excepciones esperables; usar logging.warning.
          - deduplicar internamente cuando aplique (no emitir señales redundantes).
        """
