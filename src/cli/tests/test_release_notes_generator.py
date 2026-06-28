import os

import pytest

import app.release_notes_generator as rng
from app.release_notes_generator import ReleaseNotesGenerator, GenerationError
from app.models import AnalysisResult

PROJECT = {
    "id": "proj-1",
    "name": "Member Portal",
    "key": "member-portal",
    "description": "A customer-facing portal.",
    "customer_name": "Acme Ltd.",
    "customer_industry": "Architecture",
    "llm_temperature": 0.5,
    "llm_tonality": "professional",
}

ANALYSIS = AnalysisResult(
    diff="diff --git a/x.py b/x.py\n@@ -1 +1 @@\n+change\n",
    commit_messages=["feat: add thing"],
)

VALID_DRAFT = "Diese Version verbessert die Benutzererfahrung deutlich."


class FakeResponse:
    def __init__(self, content):
        self.content = content


class RecordingLLM:
    """Stand-in for ChatOpenAI that records the temperature it was built with."""

    def __init__(self, **kwargs):
        self.kwargs = kwargs

    def invoke(self, messages):
        return FakeResponse(VALID_DRAFT)


def test_temperature_passed_into_langchain_request_payload():
    """The temperature reaches LangChain's actual request payload (DoD)."""
    os.environ.setdefault("OPENAI_API_KEY", "sk-test")
    generator = ReleaseNotesGenerator(model="gpt-4o", temperature=0.42)

    # ChatOpenAI stores it as an attribute ...
    assert generator._llm.temperature == 0.42
    # ... and emits it in the params sent on every request to the model.
    assert generator._llm._default_params.get("temperature") == 0.42


def test_generator_builds_chatopenai_with_given_temperature(monkeypatch):
    captured = {}

    def fake_ctor(**kwargs):
        captured.update(kwargs)
        return RecordingLLM(**kwargs)

    monkeypatch.setattr(rng, "ChatOpenAI", fake_ctor)

    ReleaseNotesGenerator(model="GPT-5.4", temperature=0.73)

    assert captured["model"] == "GPT-5.4"
    assert captured["temperature"] == 0.73


def test_generate_uses_configured_temperature(monkeypatch):
    monkeypatch.setattr(rng, "ChatOpenAI", lambda **kwargs: RecordingLLM(**kwargs))

    generator = ReleaseNotesGenerator(model="GPT-5.4", temperature=0.1)
    draft = generator.generate(ANALYSIS, PROJECT)

    assert draft == VALID_DRAFT
    assert generator._llm.kwargs["temperature"] == 0.1
