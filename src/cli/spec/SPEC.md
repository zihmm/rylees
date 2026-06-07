# CLI Component — Implementation Specification

Status: v1 — Authoritative agent implementation guide.

This document is self-contained. Implement the entire CLI component using only this file. Do not reference any other document.

---

## 1. Overview

The `rylees` CLI tool lets a developer generate release notes from a local Git repository and publish them to the Rylees backend API. It reads a commit range (by tag or commit hash), strips noise from the diff, sends the relevant context to OpenAI to generate a human-readable German release note, optionally presents it for human review, and publishes the approved note.

**In scope:** `generate`/`gen` command, HITL review loop, `--publish` bypass, config loading, Git diff extraction, LLM call, validation, API publish.

**Not in scope:** User registration, customer/project management, any frontend rendering, any server-side logic.

---

## 2. Technology Stack & Dependencies

**Python version:** 3.12 exactly.

### Complete `pyproject.toml`

```toml
[project]
name = "rylees"
version = "0.1.0"
description = "LLM-assisted release note generator and publisher"
requires-python = ">=3.12"
dependencies = [
    "typer>=0.12",
    "langchain-openai>=0.2",
    "gitpython>=3.1",
    "httpx>=0.27",
    "python-dotenv>=1.0",
]

[project.scripts]
rylees = "rylees.cli:app"

[build-system]
requires = ["hatchling"]
build-backend = "hatchling.build"

[tool.hatch.build.targets.wheel]
packages = ["rylees"]

[project.optional-dependencies]
dev = [
    "pytest>=8",
    "pytest-httpx>=0.30",
    "respx>=0.21",
]
```

After `pip install -e .` (or `pip install rylees`), the `rylees` executable must be available on `$PATH`.

---

## 3. Project Layout

```
src/cli/
├── rylees/
│   ├── __init__.py          # empty
│   ├── cli.py               # Typer app, entry point, orchestration
│   ├── config.py            # .env loading and validation
│   ├── git_connector.py     # GitConnector
│   ├── code_analyzer.py     # CodeAnalyzer
│   ├── release_notes_generator.py  # ReleaseNotesGenerator
│   ├── validator.py         # Validator
│   ├── rn_publisher.py      # RNPublisher
│   ├── api_client.py        # HTTP wrapper for Backend API
│   └── models.py            # Dataclasses and TypedDicts
├── tests/
│   ├── __init__.py
│   ├── test_config.py
│   ├── test_git_connector.py
│   ├── test_code_analyzer.py
│   ├── test_validator.py
│   ├── test_api_client.py
│   ├── test_rn_publisher.py
│   └── test_cli.py
├── .env.example
└── pyproject.toml
```

### `.env.example` (verbatim)

```dotenv
# Required
RYLEES_API_TOKEN=your-64-char-api-key-here
RYLEES_PROJECT_TOKEN=your-64-char-project-token-here
OPENAI_API_KEY=sk-...

# Optional — overrides the temperature fetched from the API
# RYLEES_LLM_TEMPERATURE=0.5

# Optional — overrides the default model
# RYLEES_LLM_MODEL=GPT-5.4
```

---

## 4. Configuration (`config.py`)

### Exception

```python
class ConfigError(Exception):
    def __init__(self, var_name: str):
        self.var_name = var_name
        super().__init__(f"Missing required configuration variable: {var_name}")
```

### Config dataclass

```python
from dataclasses import dataclass
from dotenv import load_dotenv
import os

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
```

**Behaviour:**
- `load_dotenv()` reads `.env` from the current working directory.
- If any required variable is absent or empty, raises `ConfigError` with the variable name.
- `RYLEES_LLM_TEMPERATURE` is optional; if set, it overrides the temperature returned by the API.
- `RYLEES_LLM_MODEL` defaults to `"GPT-5.4"`.

---

## 5. Data Models (`models.py`)

```python
from dataclasses import dataclass
from typing import TypedDict, Literal

class ProjectConfig(TypedDict):
    id: str
    name: str
    key: str
    description: str
    customer_name: str
    customer_industry: str
    llm_temperature: float
    llm_tonality: str

@dataclass
class AnalysisResult:
    diff: str
    commit_messages: list[str]

class PublishPayload(TypedDict):
    startRef: str
    endRef: str
    type: Literal["commits", "tag"]
    branchName: str
    body: str
    versionBump: Literal["major", "minor", "patch"]

class PublishResponse(TypedDict):
    id: str
    status: str
    version: str
```

---

## 6. API Client (`api_client.py`)

### Responsibility

Thin wrapper around `httpx`. Injects the `Authorization` header, maps responses to typed dicts.

```python
import httpx
from rylees.models import ProjectConfig, PublishPayload, PublishResponse

BASE_URL = "https://api.rylees.ai/v1"

class ApiClient:
    def __init__(self, api_token: str, base_url: str = BASE_URL):
        self._client = httpx.Client(
            base_url=base_url,
            headers={"Authorization": f"Bearer {api_token}"},
            timeout=30.0,
        )

    def get_project(self, project_token: str) -> ProjectConfig:
        response = self._client.get(f"/projects/{project_token}")
        response.raise_for_status()
        data = response.json()
        return ProjectConfig(
            id=data["id"],
            name=data["name"],
            key=data["key"],
            description=data.get("description", ""),
            customer_name=data["customer"]["name"],
            customer_industry=data["customer"]["industry"],
            llm_temperature=data["llm"]["temperature"],
            llm_tonality=data["llm"]["tonality"],
        )

    def publish_release_note(
        self, project_token: str, payload: PublishPayload
    ) -> PublishResponse:
        response = self._client.post(
            f"/projects/{project_token}/release-history",
            json=payload,
        )
        response.raise_for_status()
        return response.json()

    def close(self):
        self._client.close()
```

### API response shapes (for reference)

`GET /projects/{projectToken}` response:
```json
{
  "id": "...",
  "name": "Member Portal",
  "key": "member-portal",
  "description": "A customer-facing portal for membership management.",
  "customer": {
    "id": "...",
    "name": "Acme Ltd.",
    "industry": "Architecture"
  },
  "llm": {
    "temperature": 0.5,
    "tonality": "professional"
  }
}
```

`POST /projects/{projectToken}/release-history` request body:
```json
{
  "startRef": "v1.2.0",
  "endRef": "v1.3.0",
  "type": "tag",
  "branchName": "main",
  "body": "Diese Version enthält...",
  "versionBump": "minor"
}
```

`POST /projects/{projectToken}/release-history` response (`201 Created`):
```json
{
  "id": "...",
  "status": "published",
  "version": "1.3.0"
}
```

---

## 7. Git Connector (`git_connector.py`)

### Responsibility

Opens the Git repository at the current working directory and extracts commits and diff between two references.

```python
from typing import Literal
import git
from rylees.models import AnalysisResult

class GitConnectorError(Exception):
    pass

class GitConnector:
    def __init__(self, repo_path: str = "."):
        try:
            self._repo = git.Repo(repo_path)
        except git.InvalidGitRepositoryError:
            raise GitConnectorError(f"Not a valid git repository: {repo_path}")

    def get_diff(
        self,
        start_ref: str,
        end_ref: str,
        ref_type: Literal["tag", "commit"],
    ) -> tuple[list[git.Commit], str]:
        try:
            if ref_type == "tag":
                start = self._repo.tags[start_ref].commit
                end = self._repo.tags[end_ref].commit if end_ref != "HEAD" else self._repo.head.commit
            else:
                start = self._repo.commit(start_ref)
                end = self._repo.commit(end_ref) if end_ref != "HEAD" else self._repo.head.commit
        except (IndexError, git.BadName, KeyError):
            raise GitConnectorError(f"Reference not found: check --start and --end values")

        commits = list(self._repo.iter_commits(f"{start.hexsha}..{end.hexsha}"))
        diff_str = self._repo.git.diff(start.hexsha, end.hexsha)
        return commits, diff_str
```

**Error behaviour:** Any unresolvable ref raises `GitConnectorError` with a human-readable message. The caller catches this and exits with code 1.

---

## 8. Code Analyzer (`code_analyzer.py`)

### Responsibility

Cleans the raw diff and extracts commit messages.

```python
import re
from rylees.models import AnalysisResult

EXCLUDED_FILE_PATTERNS = re.compile(
    r"^diff --git a/(.*\.lock|package-lock\.json|yarn\.lock|.*\.min\.js|.*\.min\.css)\b",
    re.MULTILINE,
)

class CodeAnalyzer:
    MAX_WORDS = int(8000 / 1.3)  # ≈ 6153 words ≈ 8000 tokens

    def analyze(self, commits: list, diff: str) -> AnalysisResult:
        cleaned = self._strip_excluded(diff)
        cleaned = self._strip_binary(cleaned)
        cleaned = self._truncate(cleaned)
        messages = [c.message.strip() for c in commits]
        return AnalysisResult(diff=cleaned, commit_messages=messages)

    def _strip_excluded(self, diff: str) -> str:
        # Split on "diff --git" boundaries and discard excluded files
        hunks = re.split(r"(?=^diff --git )", diff, flags=re.MULTILINE)
        kept = []
        for hunk in hunks:
            if not hunk:
                continue
            if EXCLUDED_FILE_PATTERNS.match(hunk):
                continue
            kept.append(hunk)
        return "".join(kept)

    def _strip_binary(self, diff: str) -> str:
        hunks = re.split(r"(?=^diff --git )", diff, flags=re.MULTILINE)
        kept = []
        for hunk in hunks:
            if not hunk:
                continue
            if "Binary files" in hunk:
                continue
            kept.append(hunk)
        return "".join(kept)

    def _truncate(self, diff: str) -> str:
        words = diff.split()
        if len(words) <= self.MAX_WORDS:
            return diff
        return " ".join(words[: self.MAX_WORDS]) + "\n...[truncated]"
```

**Filtering rules (exact):**
- Exclude any diff hunk whose header line matches file extensions: `.lock`, `package-lock.json`, `yarn.lock`, `.min.js`, `.min.css`
- Exclude any diff hunk containing the string `"Binary files"` in the hunk body
- Truncate the total diff to 8000 tokens, approximated as `int(8000 / 1.3)` words; append `"\n...[truncated]"` if truncated

---

## 9. Validator (`validator.py`)

```python
class ValidationError(Exception):
    pass

class Validator:
    def validate(self, text: str) -> None:
        stripped = text.strip()
        if not stripped:
            raise ValidationError("Response is empty or whitespace")
        if len(stripped) < 10:
            raise ValidationError("Response too short (min 10 chars)")
        if len(stripped) > 2000:
            raise ValidationError("Response too long (max 2000 chars)")
```

**Rules:**
- Raises `ValidationError("Response is empty or whitespace")` if `not text.strip()`
- Raises `ValidationError("Response too short (min 10 chars)")` if `len(text.strip()) < 10`
- Raises `ValidationError("Response too long (max 2000 chars)")` if `len(text.strip()) > 2000`
- MUST NOT reject based on language or topic

---

## 10. Release Notes Generator (`release_notes_generator.py`)

### Responsibility

Builds prompts, calls OpenAI via LangChain, validates output, retries up to 3 times.

```python
from langchain_openai import ChatOpenAI
from langchain_core.messages import SystemMessage, HumanMessage
from rylees.models import AnalysisResult, ProjectConfig
from rylees.validator import Validator, ValidationError

class GenerationError(Exception):
    pass

SYSTEM_PROMPT_TEMPLATE = """\
You are a technical writer creating release notes for {customer_name},
a company in the {customer_industry} industry.

Your task is to summarise the following code changes in one short,
{tonality} paragraph written for a non-technical audience.

Rules:
- Do NOT mention file names, function names, or code.
- Do NOT use technical jargon.
- Write in German.
- Maximum 500 words.
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
```

### System prompt template (verbatim — use exactly as written)

```
You are a technical writer creating release notes for {customer_name},
a company in the {customer_industry} industry.

Your task is to summarise the following code changes in one short,
{tonality} paragraph written for a non-technical audience.

Rules:
- Do NOT mention file names, function names, or code.
- Do NOT use technical jargon.
- Write in German.
- Maximum 500 words.
- Describe what changed from the user's perspective, not how it was implemented.
```

### User prompt template (verbatim)

```
Project: {project_description}

Commit messages:
{commit_messages}

Code diff summary:
{diff}
```

`{diff}` is the already-truncated string from `CodeAnalyzer.analyze()`.

---

## 11. Publisher (`rn_publisher.py`)

```python
from typing import Literal
from rylees.api_client import ApiClient
from rylees.models import PublishPayload, PublishResponse

class RNPublisher:
    def __init__(self, api_client: ApiClient, project_token: str):
        self._client = api_client
        self._project_token = project_token

    def publish(
        self,
        body: str,
        version_bump: Literal["major", "minor", "patch"],
        start_ref: str,
        end_ref: str,
        ref_type: Literal["commits", "tag"],
        branch_name: str | None = None,
    ) -> PublishResponse:
        payload: PublishPayload = {
            "startRef": start_ref,
            "endRef": end_ref,
            "type": ref_type,
            "branchName": branch_name or "",
            "body": body,
            "versionBump": version_bump,
        }
        return self._client.publish_release_note(self._project_token, payload)
```

---

## 12. CLI Entry Point (`cli.py`)

### App setup

```python
import os
import sys
import tempfile
import subprocess
from typing import Annotated
import typer

app = typer.Typer(name="rylees", no_args_is_help=True)
```

### `generate` command

```python
@app.command(name="generate")
@app.command(name="gen", hidden=True)
def generate(
    start: Annotated[str, typer.Option("--start", "-s", help="Start tag or commit hash")] = ...,
    end: Annotated[str, typer.Option("--end", "-e", help="End tag or commit hash")] = "HEAD",
    type_: Annotated[str, typer.Option("--type", "-t", help="'tag' or 'commit'")] = "tag",
    major: Annotated[bool, typer.Option("--major", help="Bump major version")] = False,
    minor: Annotated[bool, typer.Option("--minor", help="Bump minor version")] = False,
    patch: Annotated[bool, typer.Option("--patch", help="Bump patch version")] = False,
    publish: Annotated[bool, typer.Option("--publish", "-p", help="Skip HITL and publish immediately")] = False,
):
    ...
```

> Note: Typer does not natively support command aliases. Register both `generate` and `gen` as separate commands on the same app that call the same underlying function, or use a callback approach.

### Version bump mutual exclusivity

```python
    bump_count = sum([major, minor, patch])
    if bump_count > 1:
        typer.echo("Error: only one of --major, --minor, --patch may be set at a time.", err=True)
        raise typer.Exit(code=1)
    if bump_count == 0:
        minor = True

    if major:
        version_bump = "major"
    elif patch:
        version_bump = "patch"
    else:
        version_bump = "minor"
```

### `--type` validation

```python
    if type_ not in ("tag", "commit"):
        typer.echo("Error: --type must be 'tag' or 'commit'.", err=True)
        raise typer.Exit(code=1)
    ref_type_api = "tag" if type_ == "tag" else "commits"
```

Note: the API field `type` uses `"commits"` (plural) for commit-hash mode; the CLI option uses `"commit"` (singular). Map accordingly.

### Full HITL workflow (implement in this exact order)

```python
    from rylees.config import Config, ConfigError
    from rylees.api_client import ApiClient
    from rylees.git_connector import GitConnector, GitConnectorError
    from rylees.code_analyzer import CodeAnalyzer
    from rylees.release_notes_generator import ReleaseNotesGenerator, GenerationError
    from rylees.rn_publisher import RNPublisher

    # Step 1 — Load config
    try:
        config = Config.load()
    except ConfigError as e:
        typer.echo(str(e), err=True)
        raise typer.Exit(code=1)

    # Step 2 — Fetch project config from API
    api_client = ApiClient(api_token=config.api_token)
    try:
        project = api_client.get_project(config.project_token)
    except Exception as e:
        typer.echo(f"Failed to fetch project config: {e}", err=True)
        raise typer.Exit(code=1)

    # Step 3 — Apply temperature override if set
    temperature = (
        config.llm_temperature_override
        if config.llm_temperature_override is not None
        else project["llm_temperature"]
    )

    # Step 4 — Open Git repository and compute diff
    try:
        connector = GitConnector()
        commits, diff = connector.get_diff(start, end, type_)
    except GitConnectorError as e:
        typer.echo(str(e), err=True)
        raise typer.Exit(code=1)

    # Step 5 — Analyze diff (strip noise, truncate)
    analyzer = CodeAnalyzer()
    analysis = analyzer.analyze(commits, diff)

    # Step 6 — Generate release note draft
    generator = ReleaseNotesGenerator(model=config.llm_model, temperature=temperature)
    try:
        draft = generator.generate(analysis, project)
    except GenerationError as e:
        typer.echo(str(e), err=True)
        raise typer.Exit(code=1)

    publisher = RNPublisher(api_client, config.project_token)

    # Step 7 — --publish bypass (no HITL)
    if publish:
        typer.echo(
            "⚠  Publishing release note without human review (--publish flag active).",
            err=True,
        )
        try:
            result = publisher.publish(
                body=draft,
                version_bump=version_bump,
                start_ref=start,
                end_ref=end,
                ref_type=ref_type_api,
            )
        except Exception as e:
            typer.echo(f"Publish failed: {e}", err=True)
            raise typer.Exit(code=1)
        typer.echo(f"Published: {result['status']} — version {result['version']}")
        return

    # Step 8–13 — Interactive HITL loop
    current_draft = draft
    while True:
        _display_draft(current_draft)
        choice = input("> ").strip().lower()

        if choice == "a":
            # Accept and publish
            try:
                result = publisher.publish(
                    body=current_draft,
                    version_bump=version_bump,
                    start_ref=start,
                    end_ref=end,
                    ref_type=ref_type_api,
                )
            except Exception as e:
                typer.echo(f"Publish failed: {e}", err=True)
                raise typer.Exit(code=1)
            typer.echo(f"Published: {result['status']} — version {result['version']}")
            return

        elif choice == "r":
            # Regenerate from same analysis
            try:
                current_draft = generator.generate(analysis, project)
            except GenerationError as e:
                typer.echo(str(e), err=True)
                raise typer.Exit(code=1)

        elif choice == "e":
            # Open in editor
            current_draft = _open_in_editor(current_draft)

        else:
            # Any other input — re-display prompt
            continue
```

### Display helper (exact format)

```python
SEPARATOR = "─" * 57

def _display_draft(draft: str) -> None:
    print(SEPARATOR)
    print("Generated release note:\n")
    print(draft)
    print()
    print(SEPARATOR)
    print("[A] Accept and publish   [R] Regenerate     [E] Edit")
```

### Editor helper

```python
def _open_in_editor(text: str) -> str:
    editor = os.environ.get("EDITOR", "nano")
    with tempfile.NamedTemporaryFile(
        mode="w", suffix=".txt", delete=False, encoding="utf-8"
    ) as f:
        f.write(text)
        tmp_path = f.name
    subprocess.call([editor, tmp_path])
    with open(tmp_path, encoding="utf-8") as f:
        edited = f.read()
    os.unlink(tmp_path)
    return edited
```

### Version option

```python
def version_callback(value: bool):
    if value:
        typer.echo("rylees 0.1.0")
        raise typer.Exit()

@app.callback()
def main(
    version: Annotated[
        bool,
        typer.Option("--version", "-V", callback=version_callback, is_eager=True),
    ] = False,
):
    pass
```

### Entry guard

```python
if __name__ == "__main__":
    app()
```

---

## 13. Error Handling and Exit Codes

| Situation | Exit code | Output stream |
| --------- | --------- | ------------- |
| Missing required `.env` variable | 1 | stderr |
| API request fails (non-2xx or network error) | 1 | stderr |
| Git reference not found | 1 | stderr |
| Not a valid git repository | 1 | stderr |
| LLM validation fails after 3 retries | 1 | stderr |
| Publish API call fails | 1 | stderr |
| `--major` and `--minor` both passed | 1 | stderr |
| Successful publish | 0 | stdout |
| `--version` flag | 0 | stdout |

All errors are printed via `typer.echo(..., err=True)`. Never print stack traces to the user unless in a debug mode (not in scope for v1).

---

## 14. Testing Requirements

Framework: `pytest` with `pytest-httpx` for mocking `httpx` calls.

### Test files and what each must cover

**`tests/test_config.py`**
- `test_load_raises_config_error_for_missing_api_token` — unset `RYLEES_API_TOKEN` raises `ConfigError`
- `test_load_raises_config_error_for_missing_project_token`
- `test_load_raises_config_error_for_missing_openai_key`
- `test_load_succeeds_with_all_required_vars` — returns correct `Config` instance
- `test_temperature_override_parsed_as_float` — `RYLEES_LLM_TEMPERATURE=0.7` sets `llm_temperature_override=0.7`
- `test_default_model_is_gpt54` — when `RYLEES_LLM_MODEL` unset, `config.llm_model == "GPT-5.4"`

**`tests/test_git_connector.py`**
- `test_raises_for_invalid_repo` — path with no `.git` raises `GitConnectorError`
- `test_raises_for_unknown_tag` — tag not in repo raises `GitConnectorError`
- `test_raises_for_unknown_commit` — hash not in repo raises `GitConnectorError`

**`tests/test_code_analyzer.py`**
- `test_strips_lock_file_diffs` — diff containing a `package-lock.json` hunk is removed
- `test_strips_binary_diffs` — diff containing `Binary files` line is removed
- `test_truncates_long_diffs` — diff exceeding MAX_WORDS is truncated and ends with `[truncated]`
- `test_extracts_commit_messages` — commit messages are stripped of leading/trailing whitespace

**`tests/test_validator.py`**
- `test_raises_on_empty_string`
- `test_raises_on_whitespace_only`
- `test_raises_on_too_short` — `"hi"` (< 10 chars) raises `ValidationError`
- `test_raises_on_too_long` — string of 2001 chars raises `ValidationError`
- `test_passes_valid_text` — 50-char string passes without exception
- `test_does_not_reject_french_text` — French content passes validation

**`tests/test_api_client.py`** (use `pytest-httpx` to mock)
- `test_get_project_returns_project_config` — mock `GET /projects/{token}` 200; assert mapping
- `test_get_project_raises_on_404` — mock 404; assert `httpx.HTTPStatusError` raised
- `test_publish_release_note_sends_correct_payload` — mock `POST /projects/{token}/release-history` 201; assert payload fields
- `test_publish_raises_on_error` — mock 422; assert `httpx.HTTPStatusError` raised

**`tests/test_rn_publisher.py`**
- `test_publish_assembles_payload_correctly` — verify `type`, `versionBump`, `startRef` etc. are passed correctly
- `test_publish_returns_response` — verify `PublishResponse` dict is returned

**`tests/test_cli.py`**
- `test_mutual_exclusivity_major_minor_exits_with_1` — invoke with `--major --minor`; assert exit code 1
- `test_no_bump_flag_defaults_to_minor` — no bump flag; assert `version_bump == "minor"` in flow
- `test_version_flag_prints_version` — `rylees --version` prints a version string and exits 0
- `test_publish_flag_skips_hitl` — with `--publish`, no interactive prompt displayed, warning on stderr

---

## 15. Acceptance Criteria

### AC-CLI-01 — Installation

- Running `pip install rylees` completes without errors.
- After installation, `rylees --version` executes and prints a version string.

### AC-CLI-02 — Configuration validation

- Starting any command without a `.env` file (or with a required variable missing) prints a human-readable error message identifying the missing variable and exits with a non-zero code.
- A `.env.example` file is included in the installed package.

### AC-CLI-03 — Version bump mutual exclusivity

- Running `rylees gen --start v1.0.0 --major --minor` exits with an error and does not call the API.
- Running `rylees gen --start v1.0.0` (no bump flag) behaves identically to passing `--minor`.

### AC-CLI-04 — Git reference resolution

- `--type tag` resolves `--start` and `--end` as Git tags.
- `--type commit` resolves `--start` and `--end` as commit hashes.
- Passing a ref that does not exist in the repository exits with a clear error.

### AC-CLI-05 — LLM draft generation

- Before calling OpenAI the CLI fetches `GET /projects/{RYLEES_PROJECT_TOKEN}` and uses the returned `llm.temperature` value.
- If `RYLEES_LLM_TEMPERATURE` is set in `.env`, it overrides the API-supplied temperature.
- Binary file diffs and lock-file diffs are stripped before the diff is sent to the LLM.
- The diff passed to the LLM is truncated to 8 000 tokens.

### AC-CLI-06 — Validator retry

- If the LLM returns an empty, whitespace-only, under-10-character, or over-2 000-character response, the CLI retries automatically.
- After 3 consecutive validation failures the CLI exits with a `GenerationError` and a non-zero exit code.

### AC-CLI-07 — Interactive HITL prompt

- The draft is printed between separator lines exactly as specified in section 12.
- Pressing `A` or `a` publishes and prints the returned `status` and `version`.
- Pressing `R` or `r` discards the draft and regenerates from the same analysis result.
- Pressing `E` or `e` opens the draft in the system default editor (`$EDITOR`, fallback `nano`); on save the edited text is re-displayed with the `[A] / [R] / [E]` prompt.
- Any other input re-displays the prompt without side effects.

### AC-CLI-08 — `--publish` bypass

- When `--publish` is passed, the interactive prompt is never shown.
- The warning `⚠  Publishing release note without human review (--publish flag active).` is printed to **stderr** before the API call.
- The first valid LLM draft is sent to `POST /projects/{token}/release-history` immediately.

### AC-CLI-09 — Publish confirmation

- After a successful publish, the CLI prints the response `status` and `version` string to stdout and exits with code `0`.

### AC-CLI-10 — Cross-platform execution

- All acceptance criteria above pass on macOS, Linux, and Windows with Python 3.12.
