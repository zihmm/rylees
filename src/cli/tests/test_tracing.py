import pytest

from app.tracing import configure_tracing, DEFAULT_PROJECT

LANGSMITH_VARS = (
    "LANGSMITH_TRACING",
    "LANGSMITH_API_KEY",
    "LANGSMITH_PROJECT",
    "LANGSMITH_ENDPOINT",
)


@pytest.fixture(autouse=True)
def clean_env(monkeypatch):
    for var in LANGSMITH_VARS:
        monkeypatch.delenv(var, raising=False)


def test_disabled_returns_false_and_marks_env_off(monkeypatch):
    result = configure_tracing(
        enabled=False, api_key="ls-key", project=None, endpoint=None
    )
    assert result is False
    import os

    assert os.environ["LANGSMITH_TRACING"] == "false"
    assert "LANGSMITH_API_KEY" not in os.environ


def test_enabled_without_key_degrades_and_warns(monkeypatch, capsys):
    result = configure_tracing(
        enabled=True, api_key=None, project=None, endpoint=None
    )
    assert result is False
    import os

    assert os.environ["LANGSMITH_TRACING"] == "false"
    assert "LANGSMITH_API_KEY" not in os.environ
    assert "without tracing" in capsys.readouterr().err


def test_enabled_with_key_sets_env_and_default_project(monkeypatch):
    result = configure_tracing(
        enabled=True, api_key="ls-key", project=None, endpoint=None
    )
    assert result is True
    import os

    assert os.environ["LANGSMITH_TRACING"] == "true"
    assert os.environ["LANGSMITH_API_KEY"] == "ls-key"
    assert os.environ["LANGSMITH_PROJECT"] == DEFAULT_PROJECT
    assert "LANGSMITH_ENDPOINT" not in os.environ


def test_enabled_with_explicit_project_and_endpoint(monkeypatch):
    result = configure_tracing(
        enabled=True,
        api_key="ls-key",
        project="my-project",
        endpoint="https://eu.api.smith.langchain.com",
    )
    assert result is True
    import os

    assert os.environ["LANGSMITH_PROJECT"] == "my-project"
    assert os.environ["LANGSMITH_ENDPOINT"] == "https://eu.api.smith.langchain.com"
