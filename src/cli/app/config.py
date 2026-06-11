from dataclasses import dataclass
from dotenv import load_dotenv
import os

class ConfigError(Exception):
    def __init__(self, var_name: str):
        self.var_name = var_name
        super().__init__(f"Missing required configuration variable: {var_name}")

@dataclass
class Config:
    api_token: str
    project_token: str
    openai_api_key: str
    llm_model: str
    llm_temperature_override: float | None

    @classmethod
    def load(cls) -> "Config":
        load_dotenv()
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
            llm_model=os.getenv("RYLEES_LLM_MODEL", "GPT-5.4"),
            llm_temperature_override=float(temp_override) if temp_override else None,
        )
