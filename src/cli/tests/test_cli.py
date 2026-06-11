import pytest
from typer.testing import CliRunner

import app.cli as cli_mod
from app.config import Config

runner = CliRunner()

_captured = {}


class FakeApiClient:
    def __init__(self, *args, **kwargs):
        pass

    def get_project(self, project_token):
        return {
            "id": "proj-1",
            "name": "Member Portal",
            "key": "member-portal",
            "description": "A portal.",
            "customer_name": "Acme Ltd.",
            "customer_industry": "Architecture",
            "llm_temperature": 0.5,
            "llm_tonality": "professional",
        }

    def close(self):
        pass


class FakeGitConnector:
    def __init__(self, *args, **kwargs):
        pass

    def get_diff(self, start_ref, end_ref, ref_type):
        return [], "diff --git a/x.py b/x.py\n@@ -1 +1 @@\n+change\n"


class FakeGenerator:
    def __init__(self, *args, **kwargs):
        pass

    def generate(self, analysis, project):
        return "Diese Version verbessert die Benutzererfahrung deutlich."


class FakePublisher:
    def __init__(self, *args, **kwargs):
        pass

    def publish(self, body, version_bump, start_ref, end_ref, ref_type, branch_name=None):
        _captured["version_bump"] = version_bump
        _captured["ref_type"] = ref_type
        _captured["body"] = body
        return {"id": "rel-1", "status": "published", "version": "1.3.0"}


@pytest.fixture
def patched_flow(monkeypatch):
    _captured.clear()
    monkeypatch.setattr(
        "app.config.Config.load",
        classmethod(lambda cls: Config("api", "proj", "sk", "GPT-5.4", None)),
    )
    monkeypatch.setattr("app.api_client.ApiClient", FakeApiClient)
    monkeypatch.setattr("app.git_connector.GitConnector", FakeGitConnector)
    monkeypatch.setattr("app.release_notes_generator.ReleaseNotesGenerator", FakeGenerator)
    monkeypatch.setattr("app.rn_publisher.RNPublisher", FakePublisher)


def test_mutual_exclusivity_major_minor_exits_with_1():
    result = runner.invoke(
        cli_mod.app, ["generate", "--start", "v1.0.0", "--major", "--minor"]
    )
    assert result.exit_code == 1


def test_no_bump_flag_defaults_to_minor(patched_flow):
    result = runner.invoke(
        cli_mod.app, ["generate", "--start", "v1.0.0", "--publish"]
    )
    assert result.exit_code == 0
    assert _captured["version_bump"] == "minor"


def test_version_flag_prints_version():
    result = runner.invoke(cli_mod.app, ["--version"])
    assert result.exit_code == 0
    assert "rylees" in result.output


def test_publish_flag_skips_hitl(patched_flow):
    result = runner.invoke(
        cli_mod.app, ["generate", "--start", "v1.0.0", "--publish"]
    )
    assert result.exit_code == 0
    # HITL menu must never be shown when --publish is active.
    assert "[A] Accept and publish" not in result.output
    # Warning about skipping human review is emitted.
    assert "Publishing release note without human review" in result.output
