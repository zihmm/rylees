# Rylees CLI

An LLM-assisted release-note generator and publisher. `rylees` reads the diff
between two points in your Git history, asks an LLM to draft a release note in
your project's configured tone, lets you review or edit it, and publishes it to
the Rylees backend.

## Requirements

- Python **3.12** or newer
- A Git repository to generate notes from
- A Rylees API token and project token (from the developer console)
- An OpenAI API key

## Installation

The CLI is a standard PEP 517 package. Install it from the `src/cli` directory.

### Using a virtual environment (recommended)

```bash
cd src/cli
python3.12 -m venv .venv
source .venv/bin/activate
pip install .
```

For development, install in editable mode with the dev dependencies:

```bash
pip install -e ".[dev]"
```

### Using uv

```bash
cd src/cli
uv venv
uv pip install -e ".[dev]"
```

Once installed, the `rylees` command is available on your `PATH` (inside the
activated environment):

```bash
rylees --version
```

## Configuration

`rylees` reads configuration from a `.env` file in your **current working
directory** (it searches upward from the cwd, not from the CLI's install
location). Copy the bundled example and fill in your values:

```bash
cp .env.example .env
```

| Variable | Required | Description |
| --- | --- | --- |
| `RYLEES_API_TOKEN` | yes | Authenticates you against the Rylees API. |
| `RYLEES_PROJECT_TOKEN` | yes | Identifies the project to publish notes to. |
| `OPENAI_API_KEY` | yes | Used by the LLM to draft the release note. |
| `RYLEES_API_URL` | no | Override the base API URL. Default: `https://api.rylees.ai/v1`. |
| `RYLEES_LLM_TEMPERATURE` | no | Override the temperature configured on the project. |
| `RYLEES_LLM_MODEL` | no | Override the model. Default: `GPT-5.4`. |

If a required variable is missing, the CLI exits with an error naming the
variable.

## Usage

Run `rylees` from the root of the Git repository you want to generate notes for.

```bash
rylees generate --start <ref> [options]
```

### Options

| Option | Alias | Default | Description |
| --- | --- | --- | --- |
| `--start` | `-s` | *(required)* | Start tag or commit hash. |
| `--end` | `-e` | `HEAD` | End tag or commit hash. |
| `--type` | `-t` | `tag` | Reference type: `tag` or `commit`. |
| `--major` | | | Bump the major version. |
| `--minor` | | (default) | Bump the minor version. |
| `--patch` | | | Bump the patch version. |
| `--publish` | `-p` | off | Skip the review step and publish immediately. |

Only one of `--major`, `--minor`, `--patch` may be set. If none is given,
`--minor` is assumed.

### Examples

Generate a note for the changes between two tags and review it interactively:

```bash
rylees generate --start v1.2.0 --end v1.3.0 --minor
```

Generate from a range of commits instead of tags:

```bash
rylees generate --type commit --start a1b2c3d --end HEAD
```

Generate and publish a major release without manual review (use with care —
this skips human review and prints a warning to stderr):

```bash
rylees generate --start v1.0.0 --major --publish
```

### Interactive review (HITL)

Without `--publish`, the CLI prints the generated draft and prompts for an
action:

```
[A] Accept and publish   [R] Regenerate     [E] Edit
```

- **A** — publish the current draft to the Rylees backend.
- **R** — regenerate a fresh draft from the same diff.
- **E** — open the draft in your editor (`$EDITOR`, default `nano`), then return
  to the prompt with your edits.

On a successful publish, the CLI prints the resulting status and version.

## How it works

1. Loads and validates configuration from `.env`.
2. Fetches the project's configuration (tone, temperature) from the Rylees API.
3. Opens the local Git repository and computes the diff between `--start` and
   `--end`.
4. Strips noise (binary and lock-file diffs) and truncates the diff to fit the
   LLM context window.
5. Generates a release-note draft in the project's configured tonality.
6. Lets you review, regenerate, or edit the draft — then publishes it.

## Development

Run the test suite from `src/cli`:

```bash
pip install -e ".[dev]"
pytest
```
