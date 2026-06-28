from dataclasses import dataclass
from dotenv import find_dotenv, load_dotenv
import os

from app.api_client import BASE_URL as DEFAULT_API_URL

class ConfigError(Exception):
    def __init__(self, var_name: str):
        self.var_name = var_name
        super().__init__(f"Missing required configuration variable: {var_name}")

def _parse_bool(value: str | None) -> bool:
    return (value or "").strip().lower() in {"1", "true", "yes", "on"}

@dataclass
class Config:
    api_token: str
    project_token: str
    openai_api_key: str
    api_url: str
    llm_model: str
    llm_temperature_override: float | None
    # LangSmith tracing for LLM requests (opt-in, all optional).
    langsmith_tracing: bool = False
    langsmith_api_key: str | None = None
    langsmith_project: str | None = None
    langsmith_endpoint: str | None = None

    @classmethod
    def load(cls) -> "Config":
        # Load .env from the current working directory (the target project), not
        # from the rylees source tree. usecwd=True makes find_dotenv search up
        # from the cwd instead of from this file's location.
        load_dotenv(find_dotenv(usecwd=True))
        required = {
            "RYLEES_API_TOKEN": None,
            "RYLEES_PROJECT_TOKEN": None,
            "OPENAI_API_KEY": None,
        }
        for var in required:
            val = os.getenv(var)
            if not val:
                raise ConfigError(var)
            required[var] = val

        temp_override = os.getenv("RYLEES_LLM_TEMPERATURE")
        return cls(
            api_token=required["RYLEES_API_TOKEN"],
            project_token=required["RYLEES_PROJECT_TOKEN"],
            openai_api_key=required["OPENAI_API_KEY"],
            api_url=os.getenv("RYLEES_API_URL", DEFAULT_API_URL),
            llm_model=os.getenv("RYLEES_LLM_MODEL", "GPT-5.4"),
            llm_temperature_override=float(temp_override) if temp_override else None,
            langsmith_tracing=_parse_bool(os.getenv("LANGSMITH_TRACING")),
            langsmith_api_key=os.getenv("LANGSMITH_API_KEY"),
            langsmith_project=os.getenv("LANGSMITH_PROJECT"),
            langsmith_endpoint=os.getenv("LANGSMITH_ENDPOINT"),
        )
