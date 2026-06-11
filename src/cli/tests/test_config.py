import pytest
from app.config import Config, ConfigError
import app.config as config_mod


@pytest.fixture(autouse=True)
def no_dotenv(monkeypatch):
    # Prevent a stray .env in the cwd from leaking into the tests.
    monkeypatch.setattr(config_mod, "load_dotenv", lambda *a, **k: None)
    monkeypatch.delenv("RYLEES_LLM_TEMPERATURE", raising=False)
    monkeypatch.delenv("RYLEES_LLM_MODEL", raising=False)


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
