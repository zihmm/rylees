# Rylees — Technical Specification

Status: Draft 2

Purpose: Defines the complete technical specification for the Rylees platform — covering architecture, data model, API contracts, CLI behaviour, and frontend requirements for the v1 release.

## Normative Language

The key words `MUST`, `MUST NOT`, `REQUIRED`, `SHOULD`, `SHOULD NOT`, `RECOMMENDED`, `MAY`, and `OPTIONAL` in this document are to be interpreted as described in RFC 2119.

## 1. Purpose and Scope

Rylees is a two-component platform for automated, LLM-assisted release-note generation and publication.

**Components in scope for v1:**

| Component      | Technology                                      | Purpose                                                        |
| -------------- | ----------------------------------------------- | -------------------------------------------------------------- |
| CLI tool       | Python 3.12, LangChain                          | Generate and publish release notes from a local Git repository |
| Backend API    | PHP 8.5, Laravel 13, PostgreSQL                 | Central business logic, persistence, authentication            |
| Frontend / Web | Vue 3, Vite, JavaScript ES6, Tailwind CSS, SCSS | Developer Console + public Release History viewer              |

**Features in scope for v1:**

- Release-note generation via Git diff + commit messages + OpenAI GPT-5.4
- Human-in-the-loop (HITL) review before publication (accept / regenerate / edit)
- CLI review bypass mode (`--publish` flag)
- Developer Console: customer, project, API-token management
- Public Release History with language switcher (DE / EN / FR via on-demand LLM translation)
- User registration with email activation

**Explicitly out of scope for v1:**

- Reviewer/validator LLM second pass
- Release-note inline editing in the Developer Console UI
- Multi-administrator roles
- Translation result caching

---

## 2. System Overview

```
Developer (local machine)
  └─ CLI tool
       ├─ reads  → Git Repository (local/remote, read-only)
       ├─ calls  → OpenAI API (HTTPS)
       └─ calls  → Backend API (HTTPS)

Browser — Developer
  └─ Developer Console (Vue SPA, console.rylees.ai)
       └─ calls  → Backend API (HTTPS)

Browser — Customer
  └─ Release History (Vue SPA, {customer-slug}.rylees.ai/{project-name})
       └─ calls  → Backend API (HTTPS)

Backend API (api.rylees.ai)
  ├─ serves   → CLI tool requests
  ├─ serves   → Developer Console requests
  ├─ serves   → Release History requests (public, no auth)
  ├─ calls    → OpenAI API (for LLM translation)
  └─ persists → PostgreSQL
```

### Communication protocols

| From              | To             | Protocol        | Auth                 |
| ----------------- | -------------- | --------------- | -------------------- |
| CLI               | Git repository | Git (read-only) | SSH / HTTPS          |
| CLI               | OpenAI API     | HTTPS           | `OPENAI_API_KEY`     |
| CLI               | Backend API    | HTTPS/REST      | Bearer `api_key`     |
| Developer Console | Backend API    | HTTPS/REST      | Bearer session token |
| Release History   | Backend API    | HTTPS/REST      | none (public)        |
| Backend API       | OpenAI API     | HTTPS           | `OPENAI_API_KEY`     |
| Backend API       | PostgreSQL     | TCP/socket      | DB credentials       |

---

## 3. Technology Stack

### CLI

| Concern         | Choice                                           |
| --------------- | ------------------------------------------------ |
| Language        | Python 3.12                                      |
| CLI framework   | Typer                                            |
| LLM integration | LangChain `langchain-openai` (`ChatOpenAI`)      |
| Git access      | GitPython                                        |
| HTTP client     | `httpx`                                          |
| Config          | `python-dotenv` (`.env` file)                    |
| Packaging       | `pyproject.toml` (PEP 517); entry point `rylees` |
| Testing         | `pytest`                                         |

### Backend API

| Concern    | Choice                                   |
| ---------- | ---------------------------------------- |
| Language   | PHP 8.5                                  |
| Framework  | Laravel 13                               |
| Database   | PostgreSQL 16                            |
| Auth (web) | Laravel Sanctum (personal access tokens) |
| Email      | Laravel Mailable + configurable SMTP     |
| LLM client | `openai-php/client`                      |
| Testing    | Pest                                     |

### Frontend

| Concern     | Choice                                    |
| ----------- | ----------------------------------------- |
| Framework   | Vue 3 (Composition API, `<script setup>`) |
| Build tool  | Vite                                      |
| Router      | Vue Router 4                              |
| State       | Pinia                                     |
| HTTP client | Axios                                     |
| Testing     | JEST, Vue 3 testing utils                 |
| CSS         | Tailwind CSS v3                           |
| Icons       | Provided in Figma design                  |

---

## 4. CLI Component

### 4.1 Installation

The CLI MUST be installable via pip:

```bash
pip install rylees
```

After installation the `rylees` executable MUST be available on `$PATH`.

### 4.2 Configuration

The CLI MUST read configuration from a `.env` file in the current working directory. A `.env.example` MUST be shipped with the package.

**Required variables:**

| Variable               | Description                                                     |
| ---------------------- | --------------------------------------------------------------- |
| `RYLEES_API_TOKEN`     | Developer's personal API key (`users.api_key`)                  |
| `RYLEES_PROJECT_TOKEN` | Project token (`projects.token`) identifying the target project |
| `OPENAI_API_KEY`       | OpenAI API key                                                  |

**Optional variables (with defaults):**

| Variable                 | Default                         | Description                       |
| ------------------------ | ------------------------------- | --------------------------------- |
| `RYLEES_LLM_MODEL`       | `GPT-5.4`                       | OpenAI model name                 |
| `RYLEES_LLM_TEMPERATURE` | _(fetched from project config)_ | Overrides project LLM temperature |

The CLI MUST fail with a clear error message if any required variable is missing.

### 4.3 Command Interface

#### Synopsis

```text
rylees <command> [options]
```

#### Commands

| Command    | Alias | Description                                                                      |
| ---------- | ----- | -------------------------------------------------------------------------------- |
| `generate` | `gen` | Generate release notes from commits or tags and coresponding git commit messages |
| `help`     | —     | Display help text                                                                |

#### Global options

| Short | Long        | Description            |
| ----- | ----------- | ---------------------- |
| `-V`  | `--version` | Show installed version |

#### `generate` / `gen`

```bash
rylees generate --start <ref> --end <ref> [options]
```

| Short | Long        | Value         | Description                                    | Default      |
| ----- | ----------- | ------------- | ---------------------------------------------- | ------------ |
| `-s`  | `--start`   | `<tag\|hash>` | Start tag or commit hash                       | — (required) |
| `-e`  | `--end`     | `<tag\|hash>` | End tag or commit hash                         | `HEAD`       |
| `-t`  | `--type`    | `tag\|commit` | Interpret `--start`/`--end` as tags or commits | `tag`        |
| —     | `--major`   | flag          | Bump the major version component               | false        |
| —     | `--minor`   | flag          | Bump the minor version component               | true         |
| —     | `--patch`   | flag          | Bump the patch version component               | false        |
| `-p`  | `--publish` | flag          | **⚠ DANGER:** skip HITL, publish immediately   | false        |

Exactly one of `--major`, `--minor`, `--patch` MUST be active at a time. If none is passed, `--minor` applies. If more than one is passed, the CLI MUST exit with an error message.

**Examples:**

```bash
# Generate between two tags, bump minor version (interactive)
rylees gen --start v1.2.0 --end v1.3.0

# Generate between two commits, bump patch
rylees gen -s 8f2a1c4 -e HEAD --type commit --patch

# CI/CD: generate and publish without review
rylees gen --start v1.2.0 --end v1.3.0 --publish
```

### 4.4 Internal Module Architecture

```
rylees/
├── cli.py                       # Typer app, entry point, orchestration
├── config.py                    # .env loading and validation
├── git_connector.py             # GitConnector
├── code_analyzer.py             # CodeAnalyzer
├── release_notes_generator.py   # ReleaseNotesGenerator
├── validator.py                 # Validator
├── rn_publisher.py              # RNPublisher
├── api_client.py                # HTTP wrapper for Backend API
└── models.py                    # Dataclasses / typed dicts
```

**Module responsibilities:**

**`config.py`**

- Loads `.env` via `python-dotenv`
- Validates all required variables are present
- Raises `ConfigError` with a human-readable message on missing values

**`cli.py` (InputHandler)**

- Defines the Typer app and `generate` command
- Validates `--major`/`--minor`/`--patch` mutual exclusivity
- Calls `config.py`, then orchestrates the full workflow

**`git_connector.py` (GitConnector)**

- Opens the Git repository at the current working directory using GitPython
- `get_diff(start_ref, end_ref, ref_type)` → returns `(commits: list[Commit], diff: str)`
- Supports both tag-based and commit-hash-based references

**`code_analyzer.py` (CodeAnalyzer)**

- `analyze(commits, diff)` → returns `AnalysisResult(diff: str, commit_messages: list[str])`
- Strips binary file diffs and auto-generated file diffs (e.g. `*.lock`, `package-lock.json`)
- Truncates `diff` to 8000 tokens before returning

**`release_notes_generator.py` (ReleaseNotesGenerator)**

- `generate(analysis_result, project_config)` → returns `str` (draft text)
- Builds system and user prompts (see section 4.5)
- Invokes `ChatOpenAI` via LangChain
- Passes result to `Validator`; raises `GenerationError` if validation fails after 3 retries

**`validator.py` (Validator)**

- `validate(text: str)` → raises `ValidationError` if:
  - text is empty or whitespace-only
  - text is shorter than 10 characters
  - text is longer than 2000 characters
- MUST NOT reject content based on language or topic

**`rn_publisher.py` (RNPublisher)**

- `publish(approved_text, version_bump, options)` → calls `POST /projects/{project-token}/release-history`
- Prints the returned `{ status, version }` to stdout

**`api_client.py`**

- Thin wrapper around `httpx`
- Injects `Authorization: Bearer <RYLEES_API_TOKEN>` header
- `get_project(project_token)` → project config dict
- `publish_release_note(project_token, payload)` → response dict

### 4.5 LLM Integration

#### Project context fetch

Before generation the CLI MUST call `GET /projects/{RYLEES_PROJECT_TOKEN}` to retrieve:

- `customer.name`
- `customer.industry`
- `project.description`
- `llm.temperature` (numeric float)
- `llm.tonality` (name string, e.g. `"professional"`)

The fetched `temperature` MUST be used as `ChatOpenAI(temperature=...)`. `RYLEES_LLM_TEMPERATURE` env var, if set, overrides it.

#### System prompt template

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

#### User prompt template

```
Project: {project_description}

Commit messages:
{commit_messages}

Code diff summary:
{diff}
```

`{diff}` MUST be truncated to 8000 tokens before insertion.

### 4.6 HITL Generation Workflow

When `--publish` is NOT passed:

```
1.  Load config (.env), fail fast on missing vars
2.  Fetch project config from API (GET /projects/{token})
3.  Open Git repository at cwd
4.  Compute diff between --start and --end (GitConnector)
5.  Extract and clean commit messages (CodeAnalyzer)
6.  Build system + user prompts
7.  Invoke LLM → draft text (ReleaseNotesGenerator)
8.  Run Validator; retry up to 3× on ValidationError
9.  Print draft to stdout (formatted, with separator lines)
10. Prompt developer:
      [A] Accept and publish   [R] Regenerate     [E] Edit in default editor
11. If [R] → goto 6
12. If [A] → call RNPublisher
13. If [E] → Open text in editor, when editing done goto 10 with changed text
13. Print confirmation: status + version string
```

The interactive prompt (step 10) MUST look like:

```
─────────────────────────────────────────────────────────
Generated release note:

{draft_text}

─────────────────────────────────────────────────────────
[A] Accept and publish   [R] Regenerate     [E] Edit
> _
```

The CLI MUST accept `A`/`a` or `R`/`r` or `E`/`e`. Any other input MUST re-display the same prompt.

### 4.7 No human review (`--publish`)

When `--publish` IS passed:

- Steps 10–13 (interactive loop) MUST be skipped.
- The first successful LLM draft MUST be passed directly to `RNPublisher`.
- The following warning MUST be printed to stderr before publishing:

```
⚠  Publishing release note without human review (--publish flag active).
```

### 4.8 Acceptance Criteria

#### AC-CLI-01 — Installation

- Running `pip install rylees` completes without errors.
- After installation, `rylees --version` executes and prints a version string.

#### AC-CLI-02 — Configuration validation

- Starting any command without a `.env` file (or with a required variable missing) prints a human-readable error message identifying the missing variable and exits with a non-zero code.
- A `.env.example` file is included in the installed package.

#### AC-CLI-03 — Version bump mutual exclusivity

- Running `rylees gen --start v1.0.0 --major --minor` exits with an error and does not call the API.
- Running `rylees gen --start v1.0.0` (no bump flag) behaves identically to passing `--minor`.

#### AC-CLI-04 — Git reference resolution

- `--type tag` resolves `--start` and `--end` as Git tags.
- `--type commit` resolves `--start` and `--end` as commit hashes.
- Passing a ref that does not exist in the repository exits with a clear error.

#### AC-CLI-05 — LLM draft generation

- Before calling OpenAI the CLI fetches `GET /projects/{RYLEES_PROJECT_TOKEN}` and uses the returned `llm.temperature` value.
- If `RYLEES_LLM_TEMPERATURE` is set in `.env`, it overrides the API-supplied temperature.
- Binary file diffs and lock-file diffs are stripped before the diff is sent to the LLM.
- The diff passed to the LLM is truncated to 8 000 tokens.

#### AC-CLI-06 — Validator retry

- If the LLM returns an empty, whitespace-only, under-10-character, or over-2 000-character response, the CLI retries automatically.
- After 3 consecutive validation failures the CLI exits with a `GenerationError` and a non-zero exit code.

#### AC-CLI-07 — Interactive HITL prompt

- The draft is printed between separator lines exactly as specified in section 4.6.
- Pressing `A` or `a` publishes and prints the returned `status` and `version`.
- Pressing `R` or `r` discards the draft and regenerates from step 6.
- Pressing `E` or `e` opens the draft in the system default editor; on save the edited text is re-displayed with the `[A] / [R] / [E]` prompt.
- Any other input re-displays the prompt without side effects.

#### AC-CLI-08 — `--publish` bypass

- When `--publish` is passed, the interactive prompt is never shown.
- The warning `⚠  Publishing release note without human review (--publish flag active).` is printed to **stderr** (not stdout) before the API call.
- The first valid LLM draft is sent to `POST /projects/{token}/release-history` immediately.

#### AC-CLI-09 — Publish confirmation

- After a successful publish, the CLI prints the response `status` and `version` string to stdout and exits with code `0`.

#### AC-CLI-10 — Cross-platform execution

- All acceptance criteria above pass on macOS, Linux, and Windows with Python 3.12.

---

## 5. Backend API Component

### 5.1 Framework Setup

- Laravel 13, PHP 8.5
- PostgreSQL 16 as sole database
- All routes MUST be prefixed `/v1`
- Base URL: `https://api.rylees.ai/v1`
- All responses MUST use `Content-Type: application/json`
- All timestamps in responses MUST be ISO 8601 UTC (`2026-06-05T10:00:00Z`)
- All primary keys MUST be UUIDs (`uuid()` in migrations)

### 5.2 Module Structure

The API MUST be organized as a modular monolith. Each module lives under `app/Modules/{ModuleName}/` and MUST contain its own:

```
app/Modules/{ModuleName}/
├── Controllers/
├── Models/
├── Services/
├── Requests/         # Laravel Form Requests
├── Resources/        # Laravel API Resources
├── Repositories/     # Laravel Eloquent queries
└── routes.php        # registered by the module's service provider
```

| Module           | Responsibility                                |
| ---------------- | --------------------------------------------- |
| `Auth`           | Registration, login, logout, email activation |
| `Account`        | User profile and organisation management      |
| `Customer`       | Customer and contact management               |
| `Project`        | Project management, token generation          |
| `ReleaseHistory` | Release note creation and retrieval           |
| `AI`             | LLM-based services, like translation          |

Modules MUST communicate only through public service class interfaces. Direct cross-module Model access is PROHIBITED.

Modules SHOULD use Eloquent repository classes for all database related work → thin Models

### 5.3 Database Schema

All tables MUST use UUID primary keys. Every table EXCEPT `*_types` lookup tables MUST include `created_at`, `updated_at` (`timestamps()`), and `deleted_at` (`softDeletes()`).

---

#### `organisations`

| Column     | Type           | Constraints      | Notes                                       |
| ---------- | -------------- | ---------------- | ------------------------------------------- |
| `id`       | `uuid`         | PK               |                                             |
| `slug`     | `varchar(255)` | UNIQUE, NOT NULL | URL-safe slug derived from `name` on create |
| `name`     | `varchar(255)` | NOT NULL         |                                             |
| `street`   | `varchar(255)` | nullable         |                                             |
| `postcode` | `varchar(20)`  | nullable         |                                             |
| `city`     | `varchar(255)` | nullable         |                                             |
| `website`  | `varchar(255)` | nullable         |                                             |
| `email`    | `varchar(255)` | nullable         |                                             |

> `slug` MUST be auto-generated on create or update: lowercase the `name`, replace spaces and non-alphanumeric characters with hyphens, collapse multiple hyphens, trim hyphens from both ends. Append `-2`, `-3`, … until unique.

---

#### `users`

| Column             | Type           | Constraints               | Notes                                   |
| ------------------ | -------------- | ------------------------- | --------------------------------------- |
| `id`               | `uuid`         | PK                        |                                         |
| `username`         | `varchar(255)` | UNIQUE, NOT NULL          | Used as login identifier (email format) |
| `password`         | `varchar(255)` | NOT NULL                  | Bcrypt hash — NEVER plaintext           |
| `api_key`          | `varchar(64)`  | UNIQUE, NOT NULL          | CLI auth key; generated on registration |
| `is_active`        | `boolean`      | NOT NULL, default `false` |                                         |
| `activation_token` | `varchar(255)` | nullable                  | Cleared after activation                |
| `activated_at`     | `timestamp`    | nullable                  | Set on activation                       |

---

#### `user_profiles`

| Column            | Type           | Constraints             | Notes               |
| ----------------- | -------------- | ----------------------- | ------------------- |
| `id`              | `uuid`         | PK                      |                     |
| `user_id`         | `uuid`         | FK → `users.id`, UNIQUE | Enforces 1:1        |
| `firstname`       | `varchar(255)` | NOT NULL                |                     |
| `lastname`        | `varchar(255)` | NOT NULL                |                     |
| `organisation_id` | `uuid`         | FK → `organisations.id` | Developer's own org |

---

#### `industry_types`

| Column | Type           | Constraints      |
| ------ | -------------- | ---------------- |
| `id`   | `uuid`         | PK               |
| `name` | `varchar(255)` | UNIQUE, NOT NULL |

---

#### `llm_tonality_types`

| Column | Type           | Constraints      |
| ------ | -------------- | ---------------- |
| `id`   | `uuid`         | PK               |
| `name` | `varchar(255)` | UNIQUE, NOT NULL |

---

#### `llm_temperature_types`

| Column  | Type           | Constraints      | Notes          |
| ------- | -------------- | ---------------- | -------------- |
| `id`    | `uuid`         | PK               |                |
| `name`  | `varchar(255)` | UNIQUE, NOT NULL | e.g. `precise` |
| `value` | `float`        | NOT NULL         | 0.0 – 1.0      |

---

#### `customers`

| Column            | Type   | Constraints                       | Notes                   |
| ----------------- | ------ | --------------------------------- | ----------------------- |
| `id`              | `uuid` | PK                                |                         |
| `user_id`         | `uuid` | FK → `users.id`, NOT NULL         | Owning developer        |
| `organisation_id` | `uuid` | FK → `organisations.id`, NOT NULL | Customer's organisation |
| `industry_id`     | `uuid` | FK → `industry_types.id`          | nullable                |
| `main_contact_id` | `uuid` | FK → `customer_contacts.id`       | nullable                |
| `description`     | `text` | nullable                          |                         |

---

#### `customer_contacts`

| Column        | Type           | Constraints                   |
| ------------- | -------------- | ----------------------------- |
| `id`          | `uuid`         | PK                            |
| `customer_id` | `uuid`         | FK → `customers.id`, NOT NULL |
| `firstname`   | `varchar(255)` | NOT NULL                      |
| `lastname`    | `varchar(255)` | NOT NULL                      |
| `email`       | `varchar(255)` | NOT NULL                      |

> `customers.main_contact_id` is nullable. Insert the customer first, then contacts, then set `main_contact_id` in a separate update to avoid a circular FK deadlock.

---

#### `projects`

| Column               | Type           | Constraints                               | Notes                                                     |
| -------------------- | -------------- | ----------------------------------------- | --------------------------------------------------------- |
| `id`                 | `uuid`         | PK                                        |                                                           |
| `customer_id`        | `uuid`         | FK → `customers.id`, NOT NULL             |                                                           |
| `name`               | `varchar(255)` | NOT NULL                                  |                                                           |
| `key`                | `varchar(255)` | NOT NULL                                  | URL slug; unique per customer; auto-generated from `name` |
| `description`        | `text`         | nullable                                  | Passed to CLI as project context for LLM prompt           |
| `token`              | `varchar(64)`  | UNIQUE, NOT NULL                          | CLI auth/identification token                             |
| `llm_tonality_id`    | `uuid`         | FK → `llm_tonality_types.id`, NOT NULL    |                                                           |
| `llm_temperature_id` | `uuid`         | FK → `llm_temperature_types.id`, NOT NULL |                                                           |

> `projects.token` MUST be generated with `Str::random(64)` on create.
> `projects.key` uses the same slug algorithm as `organisations.slug`, but uniqueness is enforced per `customer_id` (not globally).

---

#### `release_histories`

| Column       | Type   | Constraints                  | Notes |
| ------------ | ------ | ---------------------------- | ----- |
| `id`         | `uuid` | PK                           |       |
| `project_id` | `uuid` | FK → `projects.id`, NOT NULL |       |

> One `release_history` record MUST be created automatically when a project is created. A project MUST have exactly one `release_history`.

---

#### `release_notes`

| Column               | Type           | Constraints                           | Notes                         |
| -------------------- | -------------- | ------------------------------------- | ----------------------------- |
| `id`                 | `uuid`         | PK                                    |                               |
| `release_history_id` | `uuid`         | FK → `release_histories.id`, NOT NULL |                               |
| `author_id`          | `uuid`         | FK → `users.id`, NOT NULL             | Developer who published it    |
| `body`               | `text`         | NOT NULL                              | Approved release note content |
| `version_major`      | `integer`      | NOT NULL                              |                               |
| `version_minor`      | `integer`      | NOT NULL                              |                               |
| `version_patch`      | `integer`      | NOT NULL                              |                               |
| `branch_name`        | `varchar(255)` | nullable                              |                               |
| `commithash_start`   | `char(64)`     | nullable                              |                               |
| `commithash_end`     | `char(64)`     | nullable                              |                               |
| `tag_start`          | `varchar(255)` | nullable                              |                               |
| `tag_end`            | `varchar(255)` | nullable                              |                               |

> Either (`commithash_start` + `commithash_end`) OR (`tag_start` + `tag_end`) MUST be populated — determined by the `type` field sent by the CLI.

---

#### Database indexes

Every FK column MUST carry an index. Unique-constrained columns receive their index from the constraint and MUST NOT be duplicated.

| Index name                               | Table               | Column               |
| ---------------------------------------- | ------------------- | -------------------- |
| `customers_user_id_index`                | `customers`         | `user_id`            |
| `customers_organisation_id_index`        | `customers`         | `organisation_id`    |
| `customers_industry_id_index`            | `customers`         | `industry_id`        |
| `customers_main_contact_id_index`        | `customers`         | `main_contact_id`    |
| `customer_contacts_customer_id_index`    | `customer_contacts` | `customer_id`        |
| `projects_customer_id_index`             | `projects`          | `customer_id`        |
| `projects_llm_tonality_id_index`         | `projects`          | `llm_tonality_id`    |
| `projects_llm_temperature_id_index`      | `projects`          | `llm_temperature_id` |
| `release_histories_project_id_index`     | `release_histories` | `project_id`         |
| `release_notes_release_history_id_index` | `release_notes`     | `release_history_id` |
| `release_notes_author_id_index`          | `release_notes`     | `author_id`          |
| `user_profiles_organisation_id_index`    | `user_profiles`     | `organisation_id`    |

---

### 5.4 Authentication and Authorization

#### CLI authentication

All CLI-targeted endpoints MUST authenticate via:

```http
Authorization: Bearer <api_key>
```

Where `<api_key>` is the value from `users.api_key`. Laravel Sanctum guards MUST be configured to recognise this token and resolve the owning user.

#### Web / Developer Console authentication

`POST /auth/login` MUST issue a Sanctum personal access token scoped to `web`. Requests from the Developer Console MUST include:

```http
Authorization: Bearer <access_token>
```

Tokens MUST expire after 60 minutes. Responses from login MUST include `expires_in` (integer seconds).

#### Authorization rules

- A developer MUST only access customers where `customers.user_id = authenticated user id`.
- A developer MUST only access projects under their own customers.
- Public release history endpoints MUST NOT require authentication.

#### Standard error responses

| HTTP Status | Code               | Condition                                   |
| ----------- | ------------------ | ------------------------------------------- |
| 401         | `unauthenticated`  | Missing, expired, or invalid token          |
| 403         | `inactive_user`    | `users.is_active = false`                   |
| 403         | `forbidden`        | Resource exists but belongs to another user |
| 404         | `not_found`        | Resource does not exist                     |
| 422         | `validation_error` | Request payload failed validation           |

All error responses MUST use the shape:

```json
{ "message": "Human-readable description.", "code": "snake_case_code" }
```

---

### 5.5 Endpoint Specifications

#### Auth module — `/v1`

---

**`POST /auth/login`** — Public

Request body:

```json
{ "username": "jane@example.com", "password": "..." }
```

Response `200 OK`:

```json
{
  "token_type": "Bearer",
  "access_token": "<sanctum-token>",
  "expires_in": 3600,
  "user": {
    "id": "...",
    "username": "jane@example.com",
    "is_active": true,
    "profile": { "id": "...", "firstname": "Jane", "lastname": "Doe" },
    "organisation": { "id": "...", "name": "Doe Digital GmbH" }
  }
}
```

MUST return `403 inactive_user` if `is_active = false`.
MUST return `401 unauthenticated` on wrong credentials (do not distinguish user-not-found from wrong password).

---

**`POST /auth/logout`** — Requires web auth

Revokes the current Sanctum token. Response `204 No Content`.

---

#### Account module — `/v1`

---

**`POST /users/register`** — Public

Creates: `organisations` row, `users` row, `user_profiles` row.
Sets `is_active = false`, generates `api_key = Str::random(64)`, generates `activation_token = Str::random(64)`, sends activation email.

Request:

```json
{
  "username": "jane@example.com",
  "password": "SuperSecure!42",
  "profile": { "firstname": "Jane", "lastname": "Doe" },
  "organisation": {
    "name": "Doe Digital GmbH",
    "street": "Kernackerweg 7",
    "postcode": "5000",
    "city": "Aarau",
    "website": "https://doedigital.example",
    "email": "hello@doedigital.example"
  }
}
```

Validation:

- `username`: required, valid email format, unique in `users`
- `password`: required, min 12 characters
- `profile.firstname`, `profile.lastname`: required
- `organisation.name`: required

Response `201 Created`:

```json
{
  "user": {
    "id": "...",
    "username": "...",
    "is_active": false,
    "activated_at": null,
    "created_at": "..."
  },
  "profile": { "id": "...", "firstname": "Jane", "lastname": "Doe" },
  "organisation": {
    "id": "...",
    "name": "Doe Digital GmbH",
    "slug": "doe-digital-gmbh"
  }
}
```

---

**`GET /users/activate`** — Public

Query param: `token=<activation_token>`

Finds user by `activation_token`, sets `is_active = true`, `activated_at = now()`, clears `activation_token = null`.

Response `200 OK`:

```json
{ "message": "Account activated successfully." }
```

Returns `404 not_found` if token is not found or already cleared.

---

**`GET /users/me`** — Requires web auth

Response `200 OK`:

```json
{
  "id": "...",
  "username": "jane@example.com",
  "is_active": true,
  "activated_at": "...",
  "api_key": "<64-char-api-key>",
  "profile": { "id": "...", "firstname": "Jane", "lastname": "Doe" },
  "organisation": {
    "id": "...",
    "slug": "doe-digital-gmbh",
    "name": "Doe Digital GmbH",
    "street": "...",
    "postcode": "...",
    "city": "...",
    "website": "...",
    "email": "..."
  }
}
```

---

**`PATCH /users/me`** — Requires web auth

Updates profile and/or organisation fields. Password change requires `current_password`.

Request (all fields optional):

```json
{
  "profile": { "firstname": "...", "lastname": "..." },
  "organisation": {
    "name": "...",
    "street": "...",
    "postcode": "...",
    "city": "...",
    "website": "...",
    "email": "..."
  },
  "current_password": "...",
  "new_password": "..."
}
```

If `new_password` is provided, `current_password` MUST also be provided and MUST match. On mismatch return `422 validation_error`.

Response `200 OK`: same shape as `GET /users/me`.

---

**`DELETE /users/me`** — Requires web auth

Soft-deletes the `users` row and the `user_profiles` row (sets `deleted_at = now()`).
Revokes all active Sanctum tokens for the user.
Does NOT delete the `organisations` row (it may be referenced by customers).

Response `204 No Content`.

---

#### Customer module — `/v1`

All endpoints require web auth and MUST scope results to `authenticated user`.

---

**`GET /customers`**

Returns all non-deleted customers owned by the authenticated developer.

Response `200 OK`:

```json
{
  "items": [
    {
      "id": "...",
      "description": "...",
      "organisation": {
        "id": "...",
        "slug": "acme-ltd",
        "name": "Acme Ltd.",
        "street": "...",
        "postcode": "...",
        "city": "...",
        "website": "...",
        "email": "..."
      },
      "main_contact": {
        "id": "...",
        "firstname": "John",
        "lastname": "Doe",
        "email": "john@acme.com"
      },
      "industry": { "id": "...", "name": "Architecture" },
      "created_at": "...",
      "updated_at": "..."
    }
  ]
}
```

---

**`POST /customers`**

Creates a new customer. Also creates the customer's `organisations` row. Optionally creates an initial `customer_contacts` row and sets `main_contact_id`.

Request:

```json
{
  "organisation": {
    "name": "Acme Ltd.",
    "street": "...",
    "postcode": "5000",
    "city": "Aarau",
    "website": "...",
    "email": "info@acme.com"
  },
  "industry_id": "<uuid>",
  "description": "...",
  "main_contact": {
    "firstname": "John",
    "lastname": "Doe",
    "email": "john@acme.com"
  }
}
```

Validation:

- `organisation.name`: required
- `main_contact.firstname`, `main_contact.lastname`, `main_contact.email`: all required if `main_contact` key is present
- `industry_id`: optional; if provided, MUST reference a valid `industry_types.id`

Response `201 Created`:

```json
{
  "id": "...",
  "organisation": { "id": "...", "name": "Acme Ltd.", "slug": "acme-ltd" },
  "created_at": "..."
}
```

---

**`GET /customers/{id}`**

Returns full customer details. Returns `404` if not found or not owned by caller.

Response `200 OK`:

```json
{
  "id": "...",
  "description": "...",
  "organisation": {
    "id": "...",
    "slug": "...",
    "name": "...",
    "street": "...",
    "postcode": "...",
    "city": "...",
    "website": "...",
    "email": "..."
  },
  "industry": { "id": "...", "name": "Architecture" },
  "contacts": [
    {
      "id": "...",
      "firstname": "John",
      "lastname": "Doe",
      "email": "john@acme.com"
    }
  ],
  "main_contact": {
    "id": "...",
    "firstname": "John",
    "lastname": "Doe",
    "email": "john@acme.com"
  },
  "projects": [{ "id": "...", "name": "Member Portal", "key": "member-portal" }]
}
```

---

**`PATCH /customers/{id}`**

Updates customer fields. Same request shape as `POST /customers` (all fields optional).

Response `200 OK`: same shape as `GET /customers/{id}`.

---

#### Project module — `/v1`

All endpoints require web auth and MUST scope through customer ownership.

---

**`POST /customers/{customerId}/projects`**

Creates a new project under the given customer. Also creates one `release_histories` row for the project.

Request:

```json
{
  "name": "Member Portal",
  "description": "A customer-facing portal for membership management.",
  "llm_tonality_id": "<uuid>",
  "llm_temperature_id": "<uuid>"
}
```

Validation:

- `name`: required
- `llm_tonality_id`: required, MUST reference valid `llm_tonality_types.id`
- `llm_temperature_id`: required, MUST reference valid `llm_temperature_types.id`

Generates: `projects.key` from `name` (slug, unique per customer); `projects.token` via `Str::random(64)`.

Response `201 Created`:

```json
{
  "id": "...",
  "name": "Member Portal",
  "key": "member-portal",
  "token": "<64-char-token>",
  "created_at": "..."
}
```

---

**`GET /customers/{customerId}/projects/{id}`**

Returns full project details. Returns `404` if not found or not accessible to caller.

Response `200 OK`:

```json
{
  "id": "...",
  "name": "Member Portal",
  "key": "member-portal",
  "description": "...",
  "token": "<64-char-token>",
  "customer": { "id": "...", "name": "Acme Ltd.", "industry": "Architecture" },
  "llm": { "temperature": 0.5, "tonality": "professional" },
  "created_at": "...",
  "updated_at": "..."
}
```

---

**`PATCH /customers/{customerId}/projects/{id}`**

Updates project fields. Updatable: `name`, `description`, `llm_tonality_id`, `llm_temperature_id`.
`token` and `key` MUST NOT be changeable via this endpoint.

Response `200 OK`: same shape as GET.

---

#### CLI endpoints — `/v1`

Authenticated via `api_key` Bearer token.

---

**`GET /projects/{projectToken}`**

Returns project context for CLI generation. `{projectToken}` resolves via `projects.token`.

MUST verify that the project's customer belongs to the authenticated developer.

Response `200 OK`:

```json
{
  "id": "...",
  "name": "Member Portal",
  "key": "member-portal",
  "description": "A customer-facing portal for membership management.",
  "customer": { "id": "...", "name": "Acme Ltd.", "industry": "Architecture" },
  "llm": { "temperature": 0.5, "tonality": "professional" }
}
```

---

**`POST /projects/{projectToken}/release-history`**

Publishes a release note. Version number is computed server-side.

Request:

```json
{
  "startRef": "8f2a1c4",
  "endRef": "1e9p37x",
  "type": "commits",
  "branchName": "development",
  "body": "Several usability improvements and bug fixes.",
  "versionBump": "minor"
}
```

Field rules:

- `startRef`: required, non-empty string
- `endRef`: required, non-empty string
- `type`: required, one of `commits` or `tag`
- `branchName`: optional
- `body`: required, non-empty
- `versionBump`: required, one of `major`, `minor`, `patch`

**Server-side version computation:**

1. Find the latest `release_note` in this project's `release_history`, ordered by `created_at DESC`.
2. If none exists, treat the current version as `0.0.0`.
3. Apply the bump:
   - `major` → `(major+1).0.0`
   - `minor` → `major.(minor+1).0`
   - `patch` → `major.minor.(patch+1)`
4. Persist `version_major`, `version_minor`, `version_patch` on the new record.

When `type = commits`, populate `commithash_start` and `commithash_end` from `startRef`/`endRef`.
When `type = tag`, populate `tag_start` and `tag_end`.

Response `201 Created`:

```json
{ "id": "...", "status": "published", "version": "1.3.0" }
```

---

#### Public Release History endpoints — `/v1/public`

No authentication required.

---

**`GET /public/release-history/{customerSlug}/{projectKey}`**

- `{customerSlug}` resolves via `organisations.slug` joined through `customers.organisation_id`
- `{projectKey}` resolves via `projects.key` scoped to that customer

Returns non-deleted release notes ordered by `created_at DESC`.

Response `200 OK`:

```json
{
  "project": { "id": "...", "name": "Member Portal", "key": "member-portal" },
  "items": [
    {
      "id": "...",
      "version": "1.3.0",
      "body": "This release includes usability improvements.",
      "publishedAt": "2026-06-05T12:00:00Z"
    }
  ]
}
```

Returns `404` if the slug/key combination does not resolve.

---

**`GET /public/release-history/{customerSlug}/{projectKey}/translate`**

Query parameter: `language` — required, one of `de`, `en`, `fr`.

Calls the `AI` module to translate all release note bodies for the resolved project into the target language.

Response `200 OK`:

```json
{
  "language": "fr",
  "items": [
    {
      "id": "...",
      "version": "1.3.0",
      "body": "Cette version inclut des améliorations d'ergonomie."
    }
  ]
}
```

Returns `422 validation_error` if `language` is missing or not one of the allowed values.

---

#### Reference data endpoints — `/v1/ref`

No authentication required.

**`GET /ref/industries`**

Response `200 OK`: `{ "items": [{ "id": "...", "name": "Technology" }, ...] }`

**`GET /ref/llm-tonalities`**

Response `200 OK`: `{ "items": [{ "id": "...", "name": "professional" }, ...] }`

**`GET /ref/llm-temperatures`**

Response `200 OK`: `{ "items": [{ "id": "...", "name": "balanced", "value": 0.5 }, ...] }`

---

### 5.6 AI Module — Translation

The `AI` module (used by the translate endpoint) MUST use `openai-php/client` directly.

#### System prompt

```
You are translating release notes from {original_language} into {target_language}.
The audience is non-technical.
Return a JSON array where each object has exactly two keys: "id" and "body".
Translate only the "body" value. Do not alter IDs, version numbers, or dates.
Return only the JSON array, nothing else.
```

#### User prompt

A JSON array of all release notes for the project:

```json
[
  { "id": "<uuid>", "body": "Original text..." },
  ...
]
```

The AI module MUST call `GPT-5.4` with temperature `0.3` for translation (consistent, low creativity).
The source language MUST be treated as German (`de`) by default (since the CLI always writes in German).

### 5.7 Email — Registration Activation

Mailable class: `App\Modules\Auth\Mail\AccountActivationMail`.

Activation link:

```
https://console.rylees.ai/activate?token={activation_token}
```

Email MUST include:

- Greeting with the user's first name
- Explanation that account activation is required to log in
- Activation link as a prominent button
- Plain-text fallback body

Email configuration uses standard Laravel `MAIL_*` env vars (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`).

### 5.8 Acceptance Criteria

#### AC-API-01 — Framework and routing

- All routes are prefixed `/v1` and the base URL resolves to `https://api.rylees.ai/v1`.
- Every response carries `Content-Type: application/json`.
- All timestamps in responses are ISO 8601 UTC (e.g. `2026-06-05T10:00:00Z`).
- All primary keys in responses are UUIDs.

#### AC-API-02 — Database schema

- `php artisan migrate` runs without errors on a clean PostgreSQL 16 database.
- All tables (except `*_types` lookup tables) have `created_at`, `updated_at`, and `deleted_at` columns.
- Every foreign-key column carries the index specified in section 5.3.
- Soft-deleted records do not appear in any API response.

#### AC-API-03 — Modular monolith structure

- Each module (`Auth`, `Account`, `Customer`, `Project`, `ReleaseHistory`, `AI`) lives under `app/Modules/{ModuleName}/` with its own controllers, models, services, requests, resources, repositories, and `routes.php`.
- No module directly imports a Model from another module.

#### AC-API-04 — Registration and email activation

- `POST /users/register` with valid data returns `201` and creates `organisations`, `users`, and `user_profiles` rows.
- The new user has `is_active = false` and a non-null `activation_token`.
- An activation email is sent containing a link with the `activation_token`.
- `GET /users/activate?token=<valid>` sets `is_active = true`, `activated_at`, and clears `activation_token`; returns `200`.
- `GET /users/activate?token=<invalid-or-used>` returns `404 not_found`.

#### AC-API-05 — Authentication

- `POST /auth/login` with correct credentials and `is_active = true` returns `200` with a Bearer token and `expires_in: 3600`.
- `POST /auth/login` with `is_active = false` returns `403 inactive_user`.
- `POST /auth/login` with wrong credentials returns `401 unauthenticated` (same response whether username exists or not).
- `POST /auth/logout` revokes the current token; subsequent requests with it return `401`.
- CLI requests authenticated via `Authorization: Bearer <api_key>` resolve the owning user correctly.

#### AC-API-06 — Authorization scoping

- A developer cannot read, update, or delete customers or projects belonging to another developer; such requests return `403 forbidden` or `404 not_found`.
- Public release history endpoints return data without any `Authorization` header.

#### AC-API-07 — Standard error shape

- Every `4xx` response body matches `{ "message": "...", "code": "..." }`.
- Non-existent resources return `404`, not `500`.
- Invalid request payloads return `422 validation_error`.

#### AC-API-08 — Customer and project management

- `POST /customers` creates an `organisations` row and a `customers` row; optionally creates a `customer_contacts` row and sets `main_contact_id`.
- `POST /customers/{customerId}/projects` creates a `projects` row with a generated `key` (slug, unique per customer) and a 64-character random `token`, and automatically creates one `release_histories` row.
- `PATCH` endpoints update only the fields provided; omitted fields remain unchanged.
- `projects.token` and `projects.key` cannot be changed via `PATCH /customers/{customerId}/projects/{id}`.

#### AC-API-09 — Security field exposure

- `api_key` does not appear in any list-level response; it is present only in `GET /users/me`.
- `projects.token` does not appear in list responses; it is present only in `GET /customers/{customerId}/projects/{id}` and the `POST` create response.
- Passwords are stored as bcrypt hashes; plaintext never appears in any response or log.

#### AC-API-10 — CLI publish endpoint

- `POST /projects/{projectToken}/release-history` with `versionBump: minor` and no prior release notes creates a record with `version_major=0`, `version_minor=1`, `version_patch=0` and returns `201` with `{ "status": "published", "version": "0.1.0" }`.
- Subsequent bumps increment the correct component and reset lower components (`major` resets minor + patch, `minor` resets patch).
- When `type = commits`, `commithash_start` and `commithash_end` are populated; `tag_start` and `tag_end` remain null, and vice versa for `type = tag`.

#### AC-API-11 — Public release history

- `GET /public/release-history/{customerSlug}/{projectKey}` returns release notes ordered newest first without authentication.
- Returns `404` when the slug/key combination does not resolve.

#### AC-API-12 — Translation

- `GET /public/release-history/{customerSlug}/{projectKey}/translate?language=fr` calls the AI module and returns translated bodies for all release notes in the project.
- Missing or invalid `language` query parameter returns `422 validation_error`.

#### AC-API-13 — Reference data

- `GET /ref/industries`, `GET /ref/llm-tonalities`, and `GET /ref/llm-temperatures` return all seeded records without authentication.

#### AC-API-14 — Seed data

- `php artisan db:seed` populates `llm_tonality_types` (4 records), `llm_temperature_types` (3 records), and `industry_types` (13 records) without errors and is idempotent on re-run.

---

## 6. Frontend Component

### 6.1 Framework Setup

```
frontend/
├── src/
│   ├── apps/
│   │   ├── console/          # Developer Console entry point
│   │   │   ├── main.js
│   │   │   ├── App.vue
│   │   │   ├── router/
│   │   │   ├── stores/
│   │   │   ├── views/
│   │   │   └── components/
│   │   └── history/          # Public Release History entry point
│   │       ├── main.js
│   │       ├── App.vue
│   │       └── views/
│   └── shared/               # Shared composables, types, API client
│       ├── api.js
│       └── types.js
├── console.html              # Vite entry for Developer Console
├── history.html              # Vite entry for Release History
├── vite.config.js
└── tailwind.config.js
```

Two Vite entry points MUST be defined in `vite.config.js`:

```javascript
build: {
  rollupOptions: {
    input: {
      console: 'console.html',
      history: 'history.html',
    }
  }
}
```

### 6.2 Developer Console (`console.rylees.ai`)

All routes EXCEPT `/login`, `/register`, and `/activate` MUST require authentication. Unauthenticated access MUST redirect to `/login`.

Auth state (user object + token) MUST be stored in Pinia (`useAuthStore`) and persisted in `localStorage`. On app mount, the store MUST attempt to restore auth from `localStorage` and validate it against `GET /users/me`.

**Axios instance** MUST inject `Authorization: Bearer <token>` header on every request when a token is stored.

#### Routes

| Path                                       | View Component       | Auth Required |
| ------------------------------------------ | -------------------- | ------------- |
| `/login`                                   | `LoginView`          | no            |
| `/register`                                | `RegisterView`       | no            |
| `/activate`                                | `ActivateView`       | no            |
| `/dashboard`                               | `DashboardView`      | yes           |
| `/customers`                               | `CustomersView`      | yes           |
| `/customers/new`                           | `CustomerCreateView` | yes           |
| `/customers/:id`                           | `CustomerDetailView` | yes           |
| `/customers/:id/edit`                      | `CustomerEditView`   | yes           |
| `/customers/:customerId/projects/new`      | `ProjectCreateView`  | yes           |
| `/customers/:customerId/projects/:id`      | `ProjectDetailView`  | yes           |
| `/customers/:customerId/projects/:id/edit` | `ProjectEditView`    | yes           |
| `/account`                                 | `AccountView`        | yes           |

#### Key UI behaviors

**LoginView**: email + password form. On success, stores token and user in Pinia, redirects to `/dashboard`.

**RegisterView**: multi-section form (credentials, personal info, organisation). On success, shows a message: "Account created. Please check your email to activate your account."

**ActivateView**: on mount, reads `?token=` from query string, calls `GET /users/activate?token=...`. Shows success or error message. On success, provides a link to `/login`.

**DashboardView**: shows welcome message with user's first name and a count of customers and projects.

**CustomersView**: paginated table of customers (name, industry, city, project count). "New customer" button.

**CustomerDetailView**: customer info panel + contacts list + projects list. Each project in the list links to `ProjectDetailView`.

**ProjectDetailView**:

- Project info panel (name, description, LLM settings)
- **API token section**: displays the `token` in a `<code>` block with a "Copy to clipboard" button
- Release history table: version, date, first 100 characters of `body`

**AccountView**: form to update profile (firstname, lastname), organisation fields, and change password.

### 6.3 Public Release History (`{customer-slug}.rylees.ai/{project-key}`)

#### Subdomain and path resolution

On mount the app MUST:

1. Extract `customerSlug` from `window.location.hostname` (the subdomain portion, before the first `.`)
2. Extract `projectKey` from `window.location.pathname` (the first path segment, stripping leading `/`)
3. Call `GET /v1/public/release-history/{customerSlug}/{projectKey}`

#### Routes

| Path               | View                 |
| ------------------ | -------------------- |
| `/:projectKey`     | `ReleaseHistoryView` |
| `/:pathMatch(.*)*` | `NotFoundView`       |

#### UI requirements

- Display a vertical timeline of release notes, newest first.
- Each entry MUST show:
  - Version badge (e.g. `v1.3.0`)
  - Publication date (formatted as `DD. MMMM YYYY` in the active language)
  - Body text (full)
- Language switcher MUST appear in the page header with three options: **DE**, **EN**, **FR**.
- Default language MUST be **DE**.
- When a different language is selected, the app MUST call the translate endpoint and replace displayed bodies with translated ones. While loading, each body MUST display a loading skeleton / spinner.
- Page `<title>` MUST be `{project.name} — Release History`.
- If the project or customer slug is not found, `NotFoundView` MUST be rendered with a friendly "Project not found" message.

### 6.4 Acceptance Criteria

#### AC-FE-01 — Build and entry points

- `vite build` completes without errors and produces two separate bundles: one for the Developer Console (`console.html`) and one for the Release History (`history.html`).
- Neither bundle contains code from the other entry point.

#### AC-FE-02 — Authentication persistence (Developer Console)

- After a successful login, the token and user object are stored in `localStorage` and the Pinia `useAuthStore`.
- On a hard page reload, the app restores auth from `localStorage`, calls `GET /users/me` to validate, and lands on the previously visited route rather than redirecting to `/login`.
- If `GET /users/me` returns a non-`200` response during restore, the stored auth is cleared and the user is redirected to `/login`.

#### AC-FE-03 — Route guards

- Navigating to any auth-required route while unauthenticated redirects to `/login`.
- After login, the user is redirected to the originally requested route (or `/dashboard` if none).
- `/login`, `/register`, and `/activate` are accessible without a token.

#### AC-FE-04 — Axios auth header

- Every Axios request made while a token is stored includes `Authorization: Bearer <token>`.
- Requests made before login or after logout do not include the header.

#### AC-FE-05 — Registration flow

- Submitting the `RegisterView` form with valid data calls `POST /users/register` and displays: "Account created. Please check your email to activate your account."
- Validation errors returned by the API are shown inline next to the relevant fields.

#### AC-FE-06 — Activation flow

- `ActivateView` reads `?token=` from the query string on mount and calls `GET /users/activate?token=...` automatically.
- A success response renders a success message and a link to `/login`.
- A `404` response renders a clear error message.

#### AC-FE-07 — Dashboard

- `DashboardView` displays the authenticated user's first name and the total count of their customers and projects.

#### AC-FE-08 — Customer list and detail

- `CustomersView` renders a table with columns for name, industry, city, and project count, including a "New customer" button.
- `CustomerDetailView` shows the customer info panel, the contacts list, and a projects list where each project links to `ProjectDetailView`.

#### AC-FE-09 — Project detail

- `ProjectDetailView` displays the project name, description, and LLM settings.
- The `token` is displayed in a `<code>` block with a functional "Copy to clipboard" button.
- The release history table lists each entry with its version, publication date, and the first 100 characters of the body.

#### AC-FE-10 — Account settings

- `AccountView` allows updating `firstname`, `lastname`, and all organisation fields.
- Submitting a new password without `current_password` shows a validation error before calling the API.
- A mismatched `current_password` returned by the API is displayed as an inline error.

#### AC-FE-11 — Release History app — data loading

- On mount, `customerSlug` is extracted from the subdomain of `window.location.hostname` and `projectKey` from the first path segment.
- `GET /v1/public/release-history/{customerSlug}/{projectKey}` is called automatically and the response is rendered as a vertical timeline, newest entry first.
- The page `<title>` is set to `{project.name} — Release History`.
- An unresolvable slug/key combination renders `NotFoundView` with a "Project not found" message.

#### AC-FE-12 — Release History app — timeline entries

- Each entry shows a version badge (e.g. `v1.3.0`), a publication date formatted as `DD. MMMM YYYY` in the active language, and the full body text.

#### AC-FE-13 — Language switcher

- The page header contains **DE**, **EN**, and **FR** options; **DE** is active by default.
- Selecting a non-active language calls the translate endpoint and replaces each body with the translated text; a loading skeleton or spinner is shown per entry while the request is in flight.
- Switching back to **DE** restores the original untranslated bodies without a new API call.

#### AC-FE-14 — No `v-html` with user content

- No Vue template uses `v-html` to render any value that originates from user input or API responses containing free-form text.

---

## 7. Seed Data

All seed data MUST be inserted via a dedicated Laravel seeder and MUST be runnable via:

```bash
php artisan db:seed
```

### `llm_tonality_types`

| `name`         |
| -------------- |
| `neutral`      |
| `professional` |
| `friendly`     |
| `humorous`     |

### `llm_temperature_types`

| `name`     | `value` |
| ---------- | ------- |
| `precise`  | `0.2`   |
| `balanced` | `0.5`   |
| `creative` | `0.8`   |

### `industry_types`

Architecture, Consulting, Education, Finance, Healthcare, Legal, Manufacturing, Marketing, Media, Real Estate, Retail, Technology, Other

---

## 8. Security Requirements

- All inter-component communication MUST use HTTPS (TLS 1.2+).
- Passwords MUST be hashed with bcrypt (Laravel `Hash::make()`). Plaintext passwords MUST NEVER be stored or logged.
- `api_key` and `projects.token` MUST be generated via `Str::random(64)` and MUST NOT be derived from user data.
- `api_key` MUST NOT appear in any list-level API responses — only in `GET /users/me`.
- `projects.token` MUST NOT appear in any list-level response — only in `GET /customers/{id}/projects/{id}` and `POST /customers/{id}/projects`.
- Code diffs sent to OpenAI MUST NOT be logged server-side or client-side.
- Secrets (`OPENAI_API_KEY`, `RYLEES_API_TOKEN`, etc.) MUST reside in `.env` files which MUST be listed in `.gitignore`.
- All user-provided API inputs MUST be validated via Laravel Form Requests before any business logic is executed.
- Raw SQL queries are PROHIBITED; all database access MUST go through Eloquent or the query builder.
- Vue templates MUST NOT use `v-html` with any user-controlled content.

---

## 9. Non-Functional Requirements

| ID    | Requirement                                                                                                |
| ----- | ---------------------------------------------------------------------------------------------------------- |
| NF-01 | CLI `generate` MUST complete (excluding LLM latency) in under 5 seconds for diffs up to 500 changed lines. |
| NF-02 | `GET /public/release-history/{slug}/{key}` MUST respond in under 300 ms (p95) under normal load.           |
| NF-03 | The translation endpoint MAY take up to 30 seconds; the frontend MUST show a loading indicator.            |
| NF-04 | Database migrations MUST be idempotent and runnable via `php artisan migrate`.                             |
| NF-05 | The CLI MUST run on macOS, Linux, and Windows with Python 3.12 installed.                                  |
| NF-06 | All API error responses MUST return JSON with at least `{ "message": "...", "code": "..." }`.              |
| NF-07 | All API endpoints MUST return `404` (not `500`) for not-found resources.                                   |
| NF-08 | Soft-deleted records MUST be excluded from all non-admin API responses.                                    |

---

## 10. Repository Structure (Monorepo)

```
rylees/
├── docs/
│   ├── architecture/
│   │   ├── adr/
│   │   └── concepts/
│   ├── design/
│   │   ├── api/
│   │   ├── cli/
│   │   ├── database/
│   │   └── web/
├── src/
│   ├── cli/
│   │   ├── app/
│   │   │   ├── __init__.py
│   │   │   ├── cli.py
│   │   │   ├── config.py
│   │   │   ├── git_connector.py
│   │   │   ├── code_analyzer.py
│   │   │   ├── release_notes_generator.py
│   │   │   ├── validator.py
│   │   │   ├── rn_publisher.py
│   │   │   ├── api_client.py
│   │   │   └── models.py
│   │   ├── tests/
│   │   ├── .env.example
│   │   └── pyproject.toml
│   ├── api/
│   │   ├── app/
│   │   │   └── Modules/
│   │   │       ├── Auth/
│   │   │       ├── Account/
│   │   │       ├── Customer/
│   │   │       ├── Project/
│   │   │       ├── ReleaseHistory/
│   │   │       └── AI/
│   │   ├── database/
│   │   │   ├── migrations/
│   │   │   └── seeders/
│   │   └── ...
│   ├── frontend/
│   │   ├── src/
│   │   │   ├── apps/
│   │   │   │   ├── console/
│   │   │   │   └── history/
│   │   │   └── shared/
│   │   ├── console.html
│   │   ├── history.html
│   │   ├── vite.config.js
│   │   └── tailwind.config.js
└── README.md
```
