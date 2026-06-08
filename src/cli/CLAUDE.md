# CLI Sub-project

## Scope
This directory contains the Rylees CLI tool — a Python 3.12 application for automated, LLM-assisted release-note generation and publication.

Work ONLY in `src/cli/` when changes belong to the CLI tool.

## Tech Stack
- **Language**: Python 3.12
- **CLI framework**: Typer
- **LLM integration**: LangChain `langchain-openai` (`ChatOpenAI`)
- **Git access**: GitPython
- **HTTP client**: `httpx`
- **Config**: `python-dotenv` (`.env` file)
- **Packaging**: `pyproject.toml` (PEP 517); entry point `rylees`
- **Testing**: `pytest`

## Project Layout

```
src/cli/
├── app/
│   ├── __init__.py
│   ├── cli.py                       # Typer app, entry point, orchestration
│   ├── config.py                    # .env loading and validation
│   ├── git_connector.py             # GitConnector
│   ├── code_analyzer.py             # CodeAnalyzer
│   ├── release_notes_generator.py   # ReleaseNotesGenerator
│   ├── validator.py                 # Validator
│   ├── rn_publisher.py              # RNPublisher
│   ├── api_client.py                # HTTP wrapper for Backend API
│   └── models.py                    # Dataclasses / typed dicts
├── tests/
├── .env.example
└── pyproject.toml
```

## Spec & Docs
- Full specification: `/SPEC.md` (section 4 — CLI Component)
- Architecture docs: `/docs/architecture/`
- Design docs: `/docs/design/`

## Key Rules
- Entry point command: `rylees`
- Config is read from `.env` in the current working directory
- Required env vars: `RYLEES_API_TOKEN`, `RYLEES_PROJECT_TOKEN`, `OPENAI_API_KEY`
- Diff truncated to 8 000 tokens before LLM call
- Validator retries up to 3× on empty/too-short/too-long output
- `--publish` flag skips HITL and prints warning to stderr
- Binary file diffs and lock-file diffs must be stripped before LLM
