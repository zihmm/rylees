import os
import tempfile
import subprocess
from typing import Annotated
import typer

app = typer.Typer(name="rylees", no_args_is_help=True)

SEPARATOR = "─" * 57


def _scrub(message: str, *secrets: str) -> str:
    """Redact secrets (tokens/keys) that error messages may echo back.

    httpx embeds the request URL in its error strings, and the project token
    travels in that URL — so a failed API call would otherwise leak it to
    stderr/CI logs.
    """
    for secret in secrets:
        if secret:
            message = message.replace(secret, "***")
    return message


def _display_draft(draft: str) -> None:
    print(SEPARATOR)
    print("Generated release note:\n")
    print(draft)
    print()
    print(SEPARATOR)
    print("[A] Accept and publish   [R] Regenerate     [E] Edit")


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


def _run_generate(
    start: str,
    end: str,
    type_: str,
    major: bool,
    minor: bool,
    patch: bool,
    publish: bool,
) -> None:
    from app.config import Config, ConfigError
    from app.api_client import ApiClient
    from app.git_connector import GitConnector, GitConnectorError
    from app.code_analyzer import CodeAnalyzer
    from app.release_notes_generator import ReleaseNotesGenerator, GenerationError
    from app.rn_publisher import RNPublisher

    # Version bump mutual exclusivity
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

    # --type validation
    if type_ not in ("tag", "commit"):
        typer.echo("Error: --type must be 'tag' or 'commit'.", err=True)
        raise typer.Exit(code=1)
    ref_type_api = "tag" if type_ == "tag" else "commits"

    # Step 1 — Load config
    try:
        config = Config.load()
    except ConfigError as e:
        typer.echo(str(e), err=True)
        raise typer.Exit(code=1)

    # Step 2 — Fetch project config from API
    api_client = ApiClient(api_token=config.api_token, base_url=config.api_url)
    try:
        project = api_client.get_project(config.project_token)
    except Exception as e:
        msg = _scrub(str(e), config.project_token, config.api_token)
        typer.echo(f"Failed to fetch project config: {msg}", err=True)
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
    try:
        generator = ReleaseNotesGenerator(model=config.llm_model, temperature=temperature)
        draft = generator.generate(analysis, project)
    except GenerationError as e:
        typer.echo(str(e), err=True)
        raise typer.Exit(code=1)
    except Exception as e:
        msg = _scrub(str(e), config.openai_api_key, config.project_token, config.api_token)
        typer.echo(f"Release note generation failed: {msg}", err=True)
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
            msg = _scrub(str(e), config.project_token, config.api_token)
            typer.echo(f"Publish failed: {msg}", err=True)
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
                msg = _scrub(str(e), config.project_token, config.api_token)
                typer.echo(f"Publish failed: {msg}", err=True)
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
            except Exception as e:
                msg = _scrub(str(e), config.openai_api_key, config.project_token, config.api_token)
                typer.echo(f"Release note generation failed: {msg}", err=True)
                raise typer.Exit(code=1)

        elif choice == "e":
            # Open in editor
            current_draft = _open_in_editor(current_draft)

        else:
            # Any other input — re-display prompt
            continue


@app.command(name="generate")
def generate(
    start: Annotated[str, typer.Option("--start", "-s", help="Start tag or commit hash")] = ...,
    end: Annotated[str, typer.Option("--end", "-e", help="End tag or commit hash")] = "HEAD",
    type_: Annotated[str, typer.Option("--type", "-t", help="'tag' or 'commit'")] = "tag",
    major: Annotated[bool, typer.Option("--major", help="Bump major version")] = False,
    minor: Annotated[bool, typer.Option("--minor", help="Bump minor version")] = False,
    patch: Annotated[bool, typer.Option("--patch", help="Bump patch version")] = False,
    publish: Annotated[bool, typer.Option("--publish", "-p", help="Skip HITL and publish immediately")] = False,
):
    _run_generate(start, end, type_, major, minor, patch, publish)


@app.command(name="gen", hidden=True)
def gen(
    start: Annotated[str, typer.Option("--start", "-s", help="Start tag or commit hash")] = ...,
    end: Annotated[str, typer.Option("--end", "-e", help="End tag or commit hash")] = "HEAD",
    type_: Annotated[str, typer.Option("--type", "-t", help="'tag' or 'commit'")] = "tag",
    major: Annotated[bool, typer.Option("--major", help="Bump major version")] = False,
    minor: Annotated[bool, typer.Option("--minor", help="Bump minor version")] = False,
    patch: Annotated[bool, typer.Option("--patch", help="Bump patch version")] = False,
    publish: Annotated[bool, typer.Option("--publish", "-p", help="Skip HITL and publish immediately")] = False,
):
    _run_generate(start, end, type_, major, minor, patch, publish)


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


if __name__ == "__main__":
    app()
