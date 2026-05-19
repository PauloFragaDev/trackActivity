"""Carga y validación de config.yml con pydantic."""
from __future__ import annotations

import os
from pathlib import Path

import yaml
from pydantic import BaseModel, Field


def _expand(path: str | Path) -> Path:
    return Path(os.path.expandvars(os.path.expanduser(str(path)))).resolve()


class DatabaseConfig(BaseModel):
    path: str
    wal_mode: bool = True
    busy_timeout_ms: int = 5000

    @property
    def resolved_path(self) -> Path:
        return _expand(self.path)


class BufferConfig(BaseModel):
    flush_every_seconds: int = 30
    max_pending_signals: int = 200


class LoggingConfig(BaseModel):
    level: str = "INFO"
    file: str | None = None
    rotate_mb: int = 10
    rotate_keep: int = 5

    @property
    def resolved_file(self) -> Path | None:
        return _expand(self.file) if self.file else None


class WindowCollectorConfig(BaseModel):
    enabled: bool = True
    interval_seconds: int = 15
    backend: str = "xdotool"
    capture_title: bool = True
    capture_app_name: bool = True


class GitCollectorConfig(BaseModel):
    enabled: bool = False
    interval_seconds: int = 240
    repositories_paths: list[str] = Field(default_factory=list)
    max_depth: int = 3


class BrowserCollectorConfig(BaseModel):
    enabled: bool = False
    interval_seconds: int = 30
    url_patterns: list[str] = Field(default_factory=list)


class ThunderbirdCollectorConfig(BaseModel):
    enabled: bool = False
    interval_seconds: int = 60


class IdleCollectorConfig(BaseModel):
    enabled: bool = True
    interval_seconds: int = 30
    threshold_seconds: int = 180


class CollectorsConfig(BaseModel):
    window: WindowCollectorConfig = Field(default_factory=WindowCollectorConfig)
    git: GitCollectorConfig = Field(default_factory=GitCollectorConfig)
    browser: BrowserCollectorConfig = Field(default_factory=BrowserCollectorConfig)
    thunderbird: ThunderbirdCollectorConfig = Field(default_factory=ThunderbirdCollectorConfig)
    idle: IdleCollectorConfig = Field(default_factory=IdleCollectorConfig)


class TrackerConfig(BaseModel):
    database: DatabaseConfig
    buffer: BufferConfig = Field(default_factory=BufferConfig)
    logging: LoggingConfig = Field(default_factory=LoggingConfig)
    collectors: CollectorsConfig = Field(default_factory=CollectorsConfig)

    @classmethod
    def load(cls, path: str | Path) -> TrackerConfig:
        with open(path, encoding="utf-8") as fh:
            raw = yaml.safe_load(fh) or {}
        return cls.model_validate(raw)


DEFAULT_CONFIG_PATHS = [
    Path("config.yml"),
    Path.home() / ".config" / "trackActivity" / "config.yml",
]


def find_config(explicit: str | Path | None = None) -> Path:
    """Localiza el config.yml según prioridad: explícito > cwd > XDG."""
    if explicit:
        p = _expand(explicit)
        if not p.exists():
            raise FileNotFoundError(f"Config file not found: {p}")
        return p

    for candidate in DEFAULT_CONFIG_PATHS:
        if candidate.exists():
            return candidate.resolve()

    raise FileNotFoundError(
        "No config.yml encontrado. Copia config.example.yml a config.yml "
        "o pásalo con --config."
    )
