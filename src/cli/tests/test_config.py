import pytest
from app.config import Config, ConfigError
import app.config as config_mod


@pytest.fixture(autouse=True)
def no_dotenv(monkeypatch):
    # Prevent a stray .env in the cwd from leaking into the tests.
    monkeypatch.setattr(config_mod, "load_dotenv", lambda *a, **k: None)
    monkeypatch.delenv("RYLEES_LLM_TEMPERATURE", raising=False)
    monkeypatch.delenv("RYLEES_LLM_MODEL", raising=False)
    for var in (
        "LANGSMITH_TRACING",
        "LANGSMITH_API_KEY",
        "LANGSMITH_PROJECT",
        "LANGSMITH_ENDPOINT",
    ):
        monkeypatch.delenv(var, raising=False)


def _set_all_required(monkeypatch):
    monkeypatch.setenv("RYLEES_API_TOKEN", "api-tok")
    monkeypatch.setenv("RYLEES_PROJECT_TOKEN", "proj-tok")
    monkeypatch.setenv("OPENAI_API_KEY", "sk-test")


def test_load_raises_config_error_for_missing_api_token(monkeypatch):
    _set_all_required(monkeypatch)
    monkeypatch.delenv("RYLEES_API_TOKEN", raising=False)
    with pytest.raises(ConfigError) as exc_info:
        Config.load()
    assert exc_info.value.var_name == "RYLEES_API_TOKEN"


def test_load_raises_config_error_for_missing_project_token(monkeypatch):
    _set_all_required(monkeypatch)
    monkeypatch.delenv("RYLEES_PROJECT_TOKEN", raising=False)
    with pytest.raises(ConfigError) as exc_info:
        Config.load()
    assert exc_info.value.var_name == "RYLEES_PROJECT_TOKEN"


def test_load_raises_config_error_for_missing_openai_key(monkeypatch):
    _set_all_required(monkeypatch)
    monkeypatch.delenv("OPENAI_API_KEY", raising=False)
    with pytest.raises(ConfigError) as exc_info:
        Config.load()
    assert exc_info.value.var_name == "OPENAI_API_KEY"


def test_load_succeeds_with_all_required_vars(monkeypatch):
    _set_all_required(monkeypatch)
    cfg = Config.load()
    assert cfg.api_token == "api-tok"
    assert cfg.project_token == "proj-tok"
    assert cfg.openai_api_key == "sk-test"
    assert cfg.llm_temperature_override is None


def test_temperature_override_parsed_as_float(monkeypatch):
    _set_all_required(monkeypatch)
    monkeypatch.setenv("RYLEES_LLM_TEMPERATURE", "0.7")
    cfg = Config.load()
    assert cfg.llm_temperature_override == 0.7


def test_default_model_is_gpt54(monkeypatch):
    _set_all_required(monkeypatch)
    cfg = Config.load()
    assert cfg.llm_model == "GPT-5.4"


def test_langsmith_tracing_defaults_to_disabled(monkeypatch):
    _set_all_required(monkeypatch)
    cfg = Config.load()
    assert cfg.langsmith_tracing is False
    assert cfg.langsmith_api_key is None
    assert cfg.langsmith_project is None
    assert cfg.langsmith_endpoint is None


@pytest.mark.parametrize("value", ["true", "True", "1", "yes", "on"])
def test_langsmith_tracing_parsed_as_truthy(monkeypatch, value):
    _set_all_required(monkeypatch)
    monkeypatch.setenv("LANGSMITH_TRACING", value)
    cfg = Config.load()
    assert cfg.langsmith_tracing is True


@pytest.mark.parametrize("value", ["false", "0", "no", ""])
def test_langsmith_tracing_parsed_as_falsy(monkeypatch, value):
    _set_all_required(monkeypatch)
    monkeypatch.setenv("LANGSMITH_TRACING", value)
    cfg = Config.load()
    assert cfg.langsmith_tracing is False


def test_langsmith_settings_loaded(monkeypatch):
    _set_all_required(monkeypatch)
    monkeypatch.setenv("LANGSMITH_API_KEY", "ls-key")
    monkeypatch.setenv("LANGSMITH_PROJECT", "my-project")
    monkeypatch.setenv("LANGSMITH_ENDPOINT", "https://eu.api.smith.langchain.com")
    cfg = Config.load()
    assert cfg.langsmith_api_key == "ls-key"
    assert cfg.langsmith_project == "my-project"
    assert cfg.langsmith_endpoint == "https://eu.api.smith.langchain.com"
