from langchain_openai import ChatOpenAI
from langchain_core.messages import SystemMessage, HumanMessage
from app.models import AnalysisResult, ProjectConfig
from app.validator import Validator, ValidationError

class GenerationError(Exception):
    pass

SYSTEM_PROMPT_TEMPLATE = """\
You are a technical writer creating release notes for {customer_name},
a company in the {customer_industry} industry.

Your task is to summarise the following code changes in one short
paragraph written for a non-technical audience.

Write the release note in a {tonality} tone of voice. The {tonality}
tonality must clearly shape the wording, phrasing, and style of the
whole note.

Rules:
- Do NOT mention file names, function names, or code.
- Do NOT use technical jargon.
- Write in German.
- Maximum 500 words.
- Keep the tone consistently {tonality} throughout.
- Describe what changed from the user's perspective, not how it was implemented.\
"""

USER_PROMPT_TEMPLATE = """\
Project: {project_description}

Commit messages:
{commit_messages}

Code diff summary:
{diff}\
"""

class ReleaseNotesGenerator:
    MAX_RETRIES = 3

    def __init__(self, model: str, temperature: float):
        self._llm = ChatOpenAI(model=model, temperature=temperature)
        self._validator = Validator()

    def generate(self, analysis: AnalysisResult, project: ProjectConfig) -> str:
        system_content = SYSTEM_PROMPT_TEMPLATE.format(
            customer_name=project["customer_name"],
            customer_industry=project["customer_industry"],
            tonality=project["llm_tonality"],
        )
        user_content = USER_PROMPT_TEMPLATE.format(
            project_description=project["description"],
            commit_messages="\n".join(analysis.commit_messages),
            diff=analysis.diff,
        )
        for attempt in range(self.MAX_RETRIES):
            messages = [
                SystemMessage(content=system_content),
                HumanMessage(content=user_content),
            ]
            response = self._llm.invoke(messages)
            draft = response.content
            try:
                self._validator.validate(draft)
                return draft
            except ValidationError:
                if attempt == self.MAX_RETRIES - 1:
                    raise GenerationError(
                        f"LLM output failed validation after {self.MAX_RETRIES} attempts"
                    )
        raise GenerationError("Unreachable")
