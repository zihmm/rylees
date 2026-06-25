# CLI Component — Implementation Plan

Status: Derived from `SPEC.md` (v1). This plan sequences the build, lists exact deliverables per step, and flags decisions that must be resolved before/while coding.

---

## 0. Pre-flight decisions

### 0.1 Package directory name — **must resolve first**

There is a conflict between two authoritative-looking sources:

| Source | Package dir | Entry point |
| --- | --- | --- |
| `src/cli/spec/SPEC.md` (§3, §2 `pyproject.toml`) | `rylees/` | `rylees.cli:app` |
| `src/cli/CLAUDE.md` | `app/` | `rylees` |

**Decision: follow `SPEC.md` and use `rylees/`.** Rationale: the SPEC declares itself "self-contained… implement the entire CLI component using only this file," and the `pyproject.toml` in §2 hardwires `packages = ["rylees"]` and `rylees = "rylees.cli:app"`. The `app/` layout in `CLAUDE.md` would require rewriting `pyproject.toml`, breaking the verbatim requirement.

> If the user prefers the `app/` layout, only `pyproject.toml` (`[project.scripts]`, `[tool.hatch.build.targets.wheel]`) and the intra-package imports change; nothing else in this plan moves.

### 0.2 Working location

All work lands under `src/cli/`. Per memory, work directly on `main`; commit after each verified step.

### 0.3 Model id sanity

`RYLEES_LLM_MODEL` defaults to `"GPT-5.4"` (SPEC §4). This is passed verbatim to `ChatOpenAI(model=...)`. Keep it exactly as specified — do not "correct" it to a real OpenAI model id; it is the spec's contract and is exercised only via mocks in tests.

---

## 1. Scaffolding (Step 1)

**Goal:** installable skeleton, nothing functional yet.

Deliverables:
- `src/cli/pyproject.toml` — copy verbatim from SPEC §2.
- `src/cli/.env.example` — copy verbatim from SPEC §3.
- `src/cli/rylees/__init__.py` — empty.
- `src/cli/tests/__init__.py` — empty.
- Stub module files (empty or minimal) so imports resolve as we build:
  `config.py`, `models.py`, `api_client.py`, `git_connector.py`, `code_analyzer.py`, `validator.py`, `release_notes_generator.py`, `rn_publisher.py`, `cli.py`.

Verify:
```bash
cd src/cli
python3.12 -m venv .venv && source .venv/bin/activate
pip install -e ".[dev]"
which rylees   # must resolve on PATH (AC-CLI-01)
```

Commit: `cli: scaffold package, pyproject, env example`.

---

## 2. Foundation modules — no external I/O (Step 2)

Build the leaf modules first; they have no dependencies on each other except `models`.

### 2.1 `models.py` (SPEC §5)
- `ProjectConfig` (TypedDict), `AnalysisResult` (dataclass), `PublishPayload` (TypedDict), `PublishResponse` (TypedDict).
- Copy verbatim. No logic.

### 2.2 `config.py` (SPEC §4)
- `ConfigError`, `Config` dataclass + `Config.load()`.
- Copy verbatim. Note `load_dotenv()` reads CWD `.env`.

### 2.3 `validator.py` (SPEC §9)
- `ValidationError`, `Validator.validate()`.
- Copy verbatim. Pure function, no language/topic checks.

Dependencies: `validator`, `config`, `models` are independent → can be written in one pass.

Commit: `cli: add models, config, validator`.

---

## 3. Git + diff analysis (Step 3)

### 3.1 `git_connector.py` (SPEC §7)
- `GitConnectorError`, `GitConnector` with `get_diff(start_ref, end_ref, ref_type)`.
- Note the `ref_type` Literal here is `"tag" | "commit"` (CLI-facing), distinct from the API's `"tag" | "commits"`. Mapping happens in `cli.py`, not here.
- Returns `(list[git.Commit], diff_str)`.

### 3.2 `code_analyzer.py` (SPEC §8)
- `CodeAnalyzer` with `MAX_WORDS = int(8000 / 1.3)`, `analyze()`, `_strip_excluded()`, `_strip_binary()`, `_truncate()`.
- Exact filter rules: exclude `.lock`, `package-lock.json`, `yarn.lock`, `.min.js`, `.min.css` hunks; drop any hunk containing `"Binary files"`; truncate to `MAX_WORDS` words with `"\n...[truncated]"` suffix.

Commit: `cli: add git connector and code analyzer`.

---

## 4. API + publishing (Step 4)

### 4.1 `api_client.py` (SPEC §6)
- `BASE_URL = "https://api.rylees.ai/v1"`.
- `ApiClient.__init__` sets `Authorization: Bearer <token>`, timeout 30s.
- `get_project(project_token)` → maps JSON into `ProjectConfig` (note nested `customer.*` and `llm.*`, with `description` defaulting to `""`).
- `publish_release_note(project_token, payload)` → POST, `raise_for_status()`, returns JSON.
- `close()`.

### 4.2 `rn_publisher.py` (SPEC §11)
- `RNPublisher(api_client, project_token)`.
- `publish(body, version_bump, start_ref, end_ref, ref_type, branch_name=None)` assembles `PublishPayload` (`branchName` defaults to `""`).

Commit: `cli: add api client and publisher`.

---

## 5. LLM generation (Step 5)

### 5.1 `release_notes_generator.py` (SPEC §10)
- `GenerationError`.
- `SYSTEM_PROMPT_TEMPLATE` / `USER_PROMPT_TEMPLATE` — verbatim (German output, no jargon, ≤500 words, user-perspective).
- `ReleaseNotesGenerator(model, temperature)` builds `ChatOpenAI`, holds a `Validator`.
- `generate(analysis, project)` — format prompts, loop up to `MAX_RETRIES = 3`, invoke LLM, validate, return on success; on 3rd failure raise `GenerationError`.

Dependency: `validator` (Step 2). LLM call is mocked in tests — no live OpenAI key needed for CI.

Commit: `cli: add release notes generator`.

---

## 6. CLI orchestration (Step 6)

### 6.1 `cli.py` (SPEC §12)
Assemble in this order:
1. `app = typer.Typer(name="rylees", no_args_is_help=True)`.
2. `generate` command + hidden `gen` alias. Typer has no native aliases → register both names on the same underlying function (factor body into a shared `_run_generate(...)` helper to avoid duplication).
3. Version-bump mutual exclusivity: `>1` → exit 1; `0` → default `minor`; map to `version_bump` string.
4. `--type` validation: must be `tag`/`commit`; map to API `ref_type_api` = `tag`/`commits`.
5. HITL workflow steps 1–13 exactly as ordered in SPEC §12:
   - Load config → fetch project → temperature override → git diff → analyze → generate draft → `--publish` bypass → interactive loop (`a`/`r`/`e`/other).
6. Helpers: `_display_draft` (separator = `"─" * 57`, exact prompt line), `_open_in_editor` (`$EDITOR` fallback `nano`, tempfile round-trip).
7. `version_callback` + `@app.callback() main(--version/-V)`.
8. `if __name__ == "__main__": app()`.

Watch-outs:
- `--start` is required (`= ...` sentinel). Confirm Typer treats this as required and errors cleanly when omitted.
- `--publish` warning goes to **stderr** (AC-CLI-08); publish confirmation to **stdout** (AC-CLI-09).
- All error paths use `typer.echo(..., err=True)` + `raise typer.Exit(code=1)`; never leak tracebacks (SPEC §13).

Commit: `cli: wire up typer entry point and HITL loop`.

---

## 7. Tests (Step 7)

Framework: `pytest`, `pytest-httpx`, `respx` (dev deps from §2). Mirror SPEC §14 one-to-one — each named test must exist.

| File | Coverage | Notes |
| --- | --- | --- |
| `test_config.py` | missing each required var, success, temp float parse, default model | use `monkeypatch.setenv/delenv`; isolate `.env` |
| `test_git_connector.py` | invalid repo, unknown tag, unknown commit | build a tmp repo with GitPython, or assert on error paths |
| `test_code_analyzer.py` | strip lock, strip binary, truncate, strip commit msgs | craft synthetic diff strings; fake commit objects with `.message` |
| `test_validator.py` | empty, whitespace, too-short, too-long (2001), valid (50), French passes | French test guards the "no language rejection" rule |
| `test_api_client.py` | get_project 200 mapping, 404 raises, publish payload 201, publish 422 raises | mock with `pytest-httpx`/`respx` against `BASE_URL` |
| `test_rn_publisher.py` | payload assembly, returns response | inject a fake/mock `ApiClient` |
| `test_cli.py` | `--major --minor` exits 1, no-flag → minor, `--version` prints+exit 0, `--publish` skips HITL + stderr warning | use `typer.testing.CliRunner`; mock generator/api/git |

Test-design notes:
- Mock the LLM (`ChatOpenAI.invoke`) — never hit OpenAI. Patch at `release_notes_generator` import site.
- For `test_cli.py` HITL/publish paths, monkeypatch `GitConnector`, `ApiClient`/`RNPublisher`, and `ReleaseNotesGenerator` so no network/git is needed.
- For the `--publish` test, assert the warning is on stderr and that `input()`/`_display_draft` is never called.

Verify:
```bash
cd src/cli && pytest -q
```

Commit: `cli: add test suite`.

---

## 8. Acceptance verification (Step 8)

Walk SPEC §15 and confirm each AC, mapping to evidence:

| AC | Verification |
| --- | --- |
| AC-CLI-01 Installation | `pip install -e .` + `rylees --version` prints version, exit 0 |
| AC-CLI-02 Config validation | run with missing var → human-readable error naming the var, non-zero exit; `.env.example` packaged |
| AC-CLI-03 Bump mutual exclusivity | `--major --minor` errors without API call; no flag == `--minor` |
| AC-CLI-04 Git ref resolution | tag vs commit modes; unknown ref → clear error |
| AC-CLI-05 LLM draft | project fetched before OpenAI; temp override honored; lock/binary stripped; 8000-token truncation |
| AC-CLI-06 Validator retry | empty/short/long retried; 3 failures → `GenerationError`, non-zero exit |
| AC-CLI-07 HITL prompt | separators exact; A/a publish; R/r regenerate; E/e editor; other re-prompts |
| AC-CLI-08 `--publish` bypass | no prompt; stderr warning; first draft published immediately |
| AC-CLI-09 Publish confirmation | prints `status` + `version` to stdout, exit 0 |
| AC-CLI-10 Cross-platform | Python 3.12 on macOS/Linux/Windows; editor/tempfile paths portable |

Most ACs are covered by the §7 suite; AC-01/02/10 are partly manual/CI-matrix checks.

---

## 9. Build order summary (dependency-sorted)

```
1. Scaffold (pyproject, .env.example, empty modules)   ──► installable
2. models.py ─┬─► config.py
              ├─► validator.py
              └─► (used by all below)
3. git_connector.py, code_analyzer.py                  (depend on models)
4. api_client.py ──► rn_publisher.py                   (depend on models)
5. release_notes_generator.py                          (depends on validator, models)
6. cli.py                                              (orchestrates 2–5)
7. tests/                                              (per SPEC §14)
8. acceptance pass                                     (per SPEC §15)
```

Steps 2–5 are largely independent and can be built in parallel; Step 6 is the integration point; Step 7 can begin per-module as soon as that module lands.

---

## 10. Open items to confirm with the user

1. **Package dir `rylees/` vs `app/`** — plan assumes `rylees/` (matches `pyproject.toml`). Confirm or request the `app/` variant.
2. **`src/cli/spec/` vs root `SPEC.md`** — `CLAUDE.md` references a top-level `/SPEC.md`; the authoritative file used here is `src/cli/spec/SPEC.md`. No action needed unless other docs must be reconciled.
3. **Live integration test** — all external calls (OpenAI, Rylees API) are mocked. No live smoke test is in scope for v1 unless requested.
