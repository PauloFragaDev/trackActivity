"""Tests de los parsers de titulos (puros, sin I/O)."""
from __future__ import annotations

import pytest

from tracker.utils.title_parsers import (
    infer_repo_from_path,
    parse_chrome_title,
    parse_vscode_title,
)


# ─────────────────── VSCode ───────────────────

@pytest.mark.parametrize("title, expected", [
    ("api.py - jasper-api - Visual Studio Code",
     {"file": "api.py", "repo": "jasper-api"}),
    ("● api.py - jasper-api - Visual Studio Code",
     {"file": "api.py", "repo": "jasper-api"}),
    ("activity.db - trackActivity - Visual Studio Code",
     {"file": "activity.db", "repo": "trackActivity"}),
    ("file.ts — my-repo — Cursor",
     {"file": "file.ts", "repo": "my-repo"}),
    ("my-repo - Visual Studio Code",
     {"file": None, "repo": "my-repo"}),
    ("",
     {"file": None, "repo": None}),
    ("Something random without vscode suffix",
     {"file": None, "repo": None}),
])
def test_parse_vscode_title(title, expected):
    assert parse_vscode_title(title) == expected


# ─────────────────── Chrome ───────────────────

def test_parse_chrome_simple():
    out = parse_chrome_title("Some Page Title - Google Chrome")
    assert out["page"] == "Some Page Title"
    assert out["site_or_url_hint"] is None


def test_parse_chrome_github():
    out = parse_chrome_title("Fix permissions · Issue #123 · company/jasper-api · GitHub - Google Chrome")
    assert "GitHub" in out["page"]
    assert out["site_or_url_hint"] == "company/jasper-api"


def test_parse_chrome_jira():
    out = parse_chrome_title("[PROJ-456] Some bug - Jira - Google Chrome")
    assert out["site_or_url_hint"] == "Jira"


def test_parse_chrome_non_chrome():
    out = parse_chrome_title("Random title without browser suffix")
    assert out == {"page": None, "site_or_url_hint": None}


# ─────────────────── Path inference ───────────────────

@pytest.mark.parametrize("path, expected", [
    ("/var/www/html/jasper-api/src/foo", "jasper-api"),
    ("/var/www/html/trackActivity", "trackActivity"),
    ("/home/paulo/Projects/jasper-api/src", "jasper-api"),
    ("/home/paulo/Work/ywl-admin", "ywl-admin"),
    ("/some/random/path", "path"),
    ("", None),
])
def test_infer_repo_from_path(path, expected):
    assert infer_repo_from_path(path) == expected
