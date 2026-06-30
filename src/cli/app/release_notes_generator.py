from langchain_openai import ChatOpenAI
from langchain_core.messages import SystemMessage, HumanMessage
from app.models import AnalysisResult, ProjectConfig
from app.validator import Validator, ValidationError

class GenerationError(Exception):
    pass

LANGUAGE_NAMES = {
    "en": "English",
    "de": "German",
    "fr": "French",
    "it": "Italian",
    "es": "Spanish",
}

SYSTEM_PROMPT_TEMPLATE = """\
You are a technical writer creating release notes for {customer_name},
a company in the {customer_industry} industry.

Your task is to summarise the following code changes for a
non-technical audience. Keep it concise, but you may split the note
into a few short paragraphs separated by a blank line when that makes
it easier to read.

Write the release note in a {tonality} tone of voice. The {tonality}
tonality must clearly shape the wording, phrasing, and style of the
whole note.

Rules:
- Do NOT mention file names, function names, or code.
- Do NOT use technical jargon.
- Write the release note in {language}.
- Start the release note with an introductory, summarising sentence. Write it in bold with two line breaks.
- Do not refer to your own release (“This release”)
- Use between 20 and 100 Words. **Max. 180 words.** 
- Keep it as short as necessary
- Keep the tone consistently {tonality} throughout.
- Use paragraphs for longer texts, separating them with a blank line.
- Don’t address your audience
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
        self._model = model
        self._llm = ChatOpenAI(model=model, temperature=temperature)
        self._validator = Validator()

    def generate(self, analysis: AnalysisResult, project: ProjectConfig) -> str:
        language = LANGUAGE_NAMES.get(project["language"], project["language"])
        system_content = SYSTEM_PROMPT_TEMPLATE.format(
            customer_name=project["customer_name"],
            customer_industry=project["customer_industry"],
            tonality=project["llm_tonality"],
            language=language,
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
            response = self._llm.invoke(
                messages,
                config={
                    "run_name": "release-notes-generation",
                    "tags": ["rylees-cli"],
                    "metadata": {"model": self._model, "attempt": attempt + 1},
                },
            )
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
