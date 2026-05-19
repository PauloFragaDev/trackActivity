"""Tests del GitCollector con un repo temporal real."""
from __future__ import annotations

from pathlib import Path

import pygit2
import pytest

from tracker.collectors.git import GitCollector
from tracker.utils.git_utils import discover_repositories, read_repo_state


@pytest.fixture()
def repo(tmp_path) -> Path:
    """Crea un repo git real con un commit y un archivo modificado."""
    repo_path = tmp_path / "demo-repo"
    repo_path.mkdir()
    r = pygit2.init_repository(str(repo_path), bare=False)

    # commit inicial
    sig = pygit2.Signature("Test", "test@example.com")
    (repo_path / "README.md").write_text("# demo\n")
    index = r.index
    index.add("README.md")
    index.write()
    tree = index.write_tree()
    r.create_commit("HEAD", sig, sig, "initial commit", tree, [])

    # rename branch a 'main'
    try:
        main = r.lookup_branch("master")
        main.rename("main")
    except Exception:
        pass

    # archivo modificado sin commitear
    (repo_path / "WIP.txt").write_text("trabajo en curso\n")

    return repo_path


# ─────────────────── discovery ───────────────────


def test_discover_finds_repo_at_root(repo):
    found = list(discover_repositories([repo.parent], max_depth=2))
    assert repo in found


def test_discover_does_not_recurse_into_repo(repo):
    # subdir dentro del repo no se trata como otro repo
    (repo / "sub").mkdir()
    found = list(discover_repositories([repo.parent], max_depth=4))
    assert found == [repo]


def test_discover_skips_hidden_dirs(tmp_path, repo):
    hidden = tmp_path / ".hidden"
    hidden.mkdir()
    pygit2.init_repository(str(hidden), bare=False)
    found = list(discover_repositories([tmp_path], max_depth=3))
    assert hidden not in found


# ─────────────────── state reading ───────────────────


def test_read_repo_state_basics(repo):
    state = read_repo_state(repo)
    assert state is not None
    assert state.name == "demo-repo"
    assert state.branch in ("main", "master")  # toleramos default config
    assert state.modified_files >= 1   # WIP.txt esta sin commitear
    assert state.latest_commit is not None
    assert state.latest_commit["message"] == "initial commit"
    assert len(state.latest_commit["hash"]) == 7


def test_read_repo_state_unborn(tmp_path):
    """Repo sin commits aun."""
    p = tmp_path / "empty"
    p.mkdir()
    pygit2.init_repository(str(p), bare=False)
    state = read_repo_state(p)
    assert state is not None
    assert state.branch is None
    assert state.latest_commit is None


# ─────────────────── collector ───────────────────


def test_collector_yields_signal(repo):
    col = GitCollector(repositories_paths=[str(repo.parent)], max_depth=2)
    signals = list(col.collect())
    assert len(signals) == 1
    s = signals[0]
    assert s.source == "git"
    assert s.repo_name == "demo-repo"
    assert s.modified_files >= 1
    assert s.metadata["latest_commit"]["message"] == "initial commit"
    assert "path" in s.metadata


def test_collector_dedupes_when_unchanged(repo):
    col = GitCollector(repositories_paths=[str(repo.parent)], max_depth=2)
    first = list(col.collect())
    second = list(col.collect())
    assert len(first) == 1
    assert second == []   # nada cambio -> sin signal


def test_collector_reemits_when_branch_changes(repo):
    col = GitCollector(repositories_paths=[str(repo.parent)], max_depth=2)
    list(col.collect())   # primer tick, guarda firma

    # crear y cambiar a una nueva rama
    r = pygit2.Repository(str(repo))
    sig = pygit2.Signature("Test", "test@example.com")
    commit_obj = r[r.head.target]
    new_branch = r.branches.local.create("feature/x", commit_obj)
    r.set_head(new_branch.name)

    signals = list(col.collect())
    assert len(signals) == 1
    assert signals[0].branch == "feature/x"


def test_collector_calls_upsert_when_storage_provided(repo):
    class FakeStorage:
        def __init__(self):
            self.calls: list[tuple[str, str]] = []

        def upsert_repository(self, *, name: str, path: str) -> None:
            self.calls.append((name, path))

    storage = FakeStorage()
    col = GitCollector(
        repositories_paths=[str(repo.parent)],
        max_depth=2,
        storage=storage,
    )
    list(col.collect())
    assert storage.calls == [("demo-repo", str(repo))]


def test_collector_upserts_even_when_deduped(repo):
    class FakeStorage:
        def __init__(self):
            self.call_count = 0

        def upsert_repository(self, *, name: str, path: str) -> None:
            self.call_count += 1

    storage = FakeStorage()
    col = GitCollector(
        repositories_paths=[str(repo.parent)],
        max_depth=2,
        storage=storage,
    )
    list(col.collect())
    list(col.collect())
    # Aunque el segundo tick no produzca signal, upsert se llama siempre
    # para mantener last_seen_at fresco.
    assert storage.call_count == 2


def test_collector_handles_missing_path(tmp_path):
    """Path inexistente no rompe el collector."""
    col = GitCollector(repositories_paths=[str(tmp_path / "no-existe")], max_depth=2)
    assert list(col.collect()) == []
