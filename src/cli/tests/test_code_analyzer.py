from types import SimpleNamespace
from app.code_analyzer import CodeAnalyzer


def _commit(message: str):
    return SimpleNamespace(message=message)


def test_strips_lock_file_diffs():
    diff = (
        "diff --git a/package-lock.json b/package-lock.json\n"
        "index 1111..2222 100644\n"
        "--- a/package-lock.json\n"
        "+++ b/package-lock.json\n"
        "@@ -1 +1 @@\n"
        "+lockfile noise\n"
        "diff --git a/src/app.py b/src/app.py\n"
        "index 3333..4444 100644\n"
        "--- a/src/app.py\n"
        "+++ b/src/app.py\n"
        "@@ -1 +1 @@\n"
        "+real code change\n"
    )
    result = CodeAnalyzer().analyze([], diff)
    assert "package-lock.json" not in result.diff
    assert "real code change" in result.diff


def test_strips_binary_diffs():
    diff = (
        "diff --git a/logo.png b/logo.png\n"
        "Binary files a/logo.png and b/logo.png differ\n"
        "diff --git a/src/app.py b/src/app.py\n"
        "@@ -1 +1 @@\n"
        "+keep this\n"
    )
    result = CodeAnalyzer().analyze([], diff)
    assert "logo.png" not in result.diff
    assert "keep this" in result.diff


def test_truncates_long_diffs():
    diff = "word " * (CodeAnalyzer.MAX_WORDS + 100)
    result = CodeAnalyzer().analyze([], diff)
    assert result.diff.endswith("[truncated]")
    assert len(result.diff.split()) <= CodeAnalyzer.MAX_WORDS + 2


def test_extracts_commit_messages():
    commits = [_commit("  feat: add login  \n"), _commit("fix: typo\n")]
    result = CodeAnalyzer().analyze(commits, "")
    assert result.commit_messages == ["feat: add login", "fix: typo"]
