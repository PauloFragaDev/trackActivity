"""Helpers para paths XDG."""
from __future__ import annotations

import os
from pathlib import Path


def xdg_data_home() -> Path:
    return Path(os.environ.get("XDG_DATA_HOME", str(Path.home() / ".local" / "share")))


def xdg_config_home() -> Path:
    return Path(os.environ.get("XDG_CONFIG_HOME", str(Path.home() / ".config")))


def app_data_dir() -> Path:
    d = xdg_data_home() / "trackActivity"
    d.mkdir(parents=True, exist_ok=True)
    return d


def app_config_dir() -> Path:
    d = xdg_config_home() / "trackActivity"
    d.mkdir(parents=True, exist_ok=True)
    return d
