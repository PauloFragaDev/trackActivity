"""Configuración centralizada de logging."""
from __future__ import annotations

import logging
import sys
from logging.handlers import RotatingFileHandler
from pathlib import Path

FORMAT = "%(asctime)s %(levelname)-5s %(name)-32s %(message)s"
DATEFMT = "%Y-%m-%dT%H:%M:%S%z"


def setup_logging(
    level: str = "INFO",
    file: Path | None = None,
    rotate_mb: int = 10,
    rotate_keep: int = 5,
    foreground: bool = True,
) -> None:
    root = logging.getLogger()
    root.handlers.clear()
    root.setLevel(level)

    fmt = logging.Formatter(FORMAT, datefmt=DATEFMT)

    if foreground:
        sh = logging.StreamHandler(sys.stdout)
        sh.setFormatter(fmt)
        root.addHandler(sh)

    if file:
        file.parent.mkdir(parents=True, exist_ok=True)
        fh = RotatingFileHandler(
            file,
            maxBytes=rotate_mb * 1024 * 1024,
            backupCount=rotate_keep,
            encoding="utf-8",
        )
        fh.setFormatter(fmt)
        root.addHandler(fh)

    # Silenciar libs ruidosas
    logging.getLogger("apscheduler").setLevel(logging.WARNING)
