"""Entrypoint CLI del daemon (typer)."""
from __future__ import annotations

import logging
import signal as sig
import sys
import time
from pathlib import Path
from typing import Optional

import typer

from tracker import __version__
from tracker.buffer import SignalBuffer
from tracker.collectors.idle import IdleCollector
from tracker.collectors.window import WindowCollector
from tracker.config import TrackerConfig, find_config
from tracker.logging_setup import setup_logging
from tracker.scheduler import Scheduler
from tracker.storage import SchemaError, Storage
from tracker.utils.x11 import check_xdotool

app = typer.Typer(add_completion=False, help="trackActivity daemon CLI")
logger = logging.getLogger("tracker.cli")


def _load(config_path: Optional[Path]) -> TrackerConfig:
    cfg_file = find_config(config_path)
    cfg = TrackerConfig.load(cfg_file)
    setup_logging(
        level=cfg.logging.level,
        file=cfg.logging.resolved_file,
        rotate_mb=cfg.logging.rotate_mb,
        rotate_keep=cfg.logging.rotate_keep,
        foreground=True,
    )
    logger.info("config loaded from %s", cfg_file)
    return cfg


def _build_collectors(cfg: TrackerConfig) -> list:
    collectors = []
    if cfg.collectors.window.enabled:
        collectors.append(WindowCollector(
            interval_seconds=cfg.collectors.window.interval_seconds,
            backend=cfg.collectors.window.backend,
        ))
    if cfg.collectors.idle.enabled:
        collectors.append(IdleCollector(
            interval_seconds=cfg.collectors.idle.interval_seconds,
            threshold_seconds=cfg.collectors.idle.threshold_seconds,
        ))
    # git/browser/thunderbird: M2+
    return collectors


@app.command()
def run(
    config: Optional[Path] = typer.Option(None, "--config", "-c", help="Ruta a config.yml"),
    foreground: bool = typer.Option(True, "--foreground/--background"),
    log_level: Optional[str] = typer.Option(None, "--log-level", help="DEBUG/INFO/WARNING/ERROR"),
) -> None:
    """Arranca el daemon."""
    cfg = _load(config)
    if log_level:
        logging.getLogger().setLevel(log_level)

    storage = Storage(
        cfg.database.resolved_path,
        wal_mode=cfg.database.wal_mode,
        busy_timeout_ms=cfg.database.busy_timeout_ms,
    )
    try:
        storage.validate_schema()
    except SchemaError as exc:
        logger.error("schema error: %s", exc)
        sys.exit(2)

    buffer = SignalBuffer(
        storage,
        flush_every_seconds=cfg.buffer.flush_every_seconds,
        max_pending=cfg.buffer.max_pending_signals,
    )
    scheduler = Scheduler(buffer)
    scheduler.register_many(_build_collectors(cfg))

    buffer.start()
    scheduler.start()
    logger.info("trackActivity daemon v%s running", __version__)

    stop_evt = _install_signal_handlers()
    try:
        while not stop_evt.is_set():
            time.sleep(1)
    finally:
        logger.info("shutting down...")
        scheduler.stop()
        buffer.stop()
        logger.info("bye")


@app.command()
def doctor(
    config: Optional[Path] = typer.Option(None, "--config", "-c"),
) -> None:
    """Verifica dependencias del SO, schema y conexión a BBDD."""
    cfg = _load(config)

    ok = True

    print("→ xdotool:", "✅" if check_xdotool() else "❌ no encontrado")
    if not check_xdotool():
        ok = False

    db_path = cfg.database.resolved_path
    print(f"→ BBDD: {db_path}")
    if not db_path.parent.exists():
        print(f"   ❌ directorio padre no existe: {db_path.parent}")
        ok = False

    storage = Storage(db_path, wal_mode=cfg.database.wal_mode)
    try:
        storage.validate_schema()
        print("   ✅ schema válido (tablas requeridas presentes)")
    except SchemaError as exc:
        print(f"   ❌ {exc}")
        ok = False

    sys.exit(0 if ok else 1)


@app.command()
def collect(
    kind: str = typer.Argument(..., help="window | idle"),
    config: Optional[Path] = typer.Option(None, "--config", "-c"),
    once: bool = typer.Option(False, "--once", help="Ejecuta una sola vez y sale"),
    dry_run: bool = typer.Option(False, "--dry-run", help="No escribe a BBDD"),
) -> None:
    """Ejecuta un único collector, para debugging."""
    cfg = _load(config)
    factory = {
        "window": lambda: WindowCollector(
            interval_seconds=cfg.collectors.window.interval_seconds,
            backend=cfg.collectors.window.backend,
        ),
        "idle": lambda: IdleCollector(
            interval_seconds=cfg.collectors.idle.interval_seconds,
            threshold_seconds=cfg.collectors.idle.threshold_seconds,
        ),
    }
    if kind not in factory:
        print(f"collector desconocido: {kind} (válidos: {list(factory)})")
        raise typer.Exit(code=2)

    collector = factory[kind]()
    storage = (
        None
        if dry_run
        else Storage(cfg.database.resolved_path, wal_mode=cfg.database.wal_mode)
    )

    def tick() -> None:
        signals = list(collector.collect())
        if not signals:
            print("(sin señales nuevas)")
            return
        for s in signals:
            print(s)
        if storage is not None:
            storage.insert_events(signals)
            print(f"  → {len(signals)} insertadas")

    tick()
    if once:
        return
    try:
        while True:
            time.sleep(collector.interval_seconds)
            tick()
    except KeyboardInterrupt:
        pass


@app.command()
def version() -> None:
    """Muestra la versión."""
    print(__version__)


def _install_signal_handlers():
    import threading
    evt = threading.Event()

    def _handler(signum, frame):  # noqa: ARG001
        logger.info("signal %s received", signum)
        evt.set()

    sig.signal(sig.SIGINT, _handler)
    sig.signal(sig.SIGTERM, _handler)
    return evt


if __name__ == "__main__":
    app()
