import os
import sys
import time
import shutil
import textwrap
import itertools
import threading
import tempfile
import subprocess
from contextlib import contextmanager
from typing import Annotated
import typer

app = typer.Typer(name="rylees")

SPINNER_FRAMES = "⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏"


@contextmanager
def _spinner(message: str):
    """Animate a braille spinner on stderr while a slow task runs.

    Writes to stderr so the generated draft on stdout stays clean and
    pipeable. Renders nothing when stderr isn't a TTY (e.g. CI logs).
    """
    if not sys.stderr.isatty():
        yield
        return

    done = threading.Event()

    def spin():
        for frame in itertools.cycle(SPINNER_FRAMES):
            if done.is_set():
                break
            sys.stderr.write(f"\r{frame} {message}")
            sys.stderr.flush()
            time.sleep(0.08)

    thread = threading.Thread(target=spin, daemon=True)
    thread.start()
    try:
        yield
    finally:
        done.set()
        thread.join()
        # Clear the spinner line so it leaves no residue behind.
        sys.stderr.write("\r\033[K")
        sys.stderr.flush()


class Checklist:
    """A live, multi-line task checklist rendered on stderr.

    Each step shows a grey tick while pending, a round spinner while active,
    and a green tick once done (red cross on failure). On a non-TTY (CI logs,
    piped output) it degrades to one plain line per completed/failed step.
    """

    PENDING, ACTIVE, DONE, FAIL = "pending", "active", "done", "fail"

    GREY, GREEN, RED, CYAN, RESET = (
        "\033[90m", "\033[32m", "\033[31m", "\033[36m", "\033[0m",
    )
    ROUND_FRAMES = "◐◓◑◒"

    def __init__(self, labels: list[str]):
        self._labels = list(labels)
        self._states = [self.PENDING] * len(labels)
        self._enabled = sys.stderr.isatty()
        self._frame = 0
        self._lock = threading.Lock()
        self._stop = threading.Event()
        self._thread: threading.Thread | None = None
        self._drawn = False

    def _glyph(self, i: int) -> str:
        state = self._states[i]
        if state == self.DONE:
            return f"{self.GREEN}✓{self.RESET}"
        if state == self.FAIL:
            return f"{self.RED}✗{self.RESET}"
        if state == self.ACTIVE:
            frame = self.ROUND_FRAMES[self._frame % len(self.ROUND_FRAMES)]
            return f"{self.CYAN}{frame}{self.RESET}"
        return f"{self.GREY}✓{self.RESET}"  # pending — grey tick

    def _render(self) -> None:
        out = []
        if self._drawn:
            out.append(f"\033[{len(self._labels)}A")  # cursor up to block top
        for i, label in enumerate(self._labels):
            out.append(f"\r\033[K  {self._glyph(i)} {label}\n")
        sys.stderr.write("".join(out))
        sys.stderr.flush()
        self._drawn = True

    def _animate(self) -> None:
        while not self._stop.is_set():
            with self._lock:
                self._frame += 1
                self._render()
            time.sleep(0.12)

    def _ensure_thread(self) -> None:
        if self._thread is None:
            self._stop.clear()
            self._thread = threading.Thread(target=self._animate, daemon=True)
            self._thread.start()

    def _halt_thread(self) -> None:
        self._stop.set()
        if self._thread is not None:
            self._thread.join()
            self._thread = None

    def begin(self) -> None:
        """Draw the full checklist (all steps pending) and start animating."""
        if not self._enabled:
            return
        with self._lock:
            self._render()
        self._ensure_thread()

    def active(self, i: int) -> None:
        if not self._enabled:
            return
        with self._lock:
            self._states[i] = self.ACTIVE
            if not self._drawn:
                self._render()
        self._ensure_thread()

    def done(self, i: int) -> None:
        if not self._enabled:
            sys.stderr.write(f"  ✓ {self._labels[i]}\n")
            sys.stderr.flush()
            return
        with self._lock:
            self._states[i] = self.DONE
            self._render()

    def fail(self, i: int) -> None:
        if not self._enabled:
            sys.stderr.write(f"  ✗ {self._labels[i]}\n")
            sys.stderr.flush()
            return
        self._halt_thread()
        with self._lock:
            self._states[i] = self.FAIL
            self._render()
        sys.stderr.write("\n")
        sys.stderr.flush()

    def pause(self) -> None:
        """Freeze the current block so other output can print below it."""
        if not self._enabled:
            return
        self._halt_thread()
        with self._lock:
            self._render()
        sys.stderr.write("\n")
        sys.stderr.flush()

    def resume(self) -> None:
        """Re-draw the checklist fresh at the cursor (after paused output)."""
        if not self._enabled:
            return
        self._drawn = False
        with self._lock:
            self._render()
        self._ensure_thread()

    def stop(self) -> None:
        self._halt_thread()
        if self._enabled:
            with self._lock:
                self._render()


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


def _render_box(text: str, actions: str | None = None) -> None:
    """Print text inside a heavy-bordered (bold) box.

    Has spacing outside the box (blank line above/below) and inside the box
    (padding around the text). Long lines wrap to the terminal width. When
    ``actions`` is given it is embedded into the bottom border line.
    """
    PAD_X = 3
    term_width = shutil.get_terminal_size((80, 24)).columns
    inner_width = max(24, min(76, term_width - 2 - PAD_X * 2))

    wrapped: list[str] = []
    for paragraph in text.splitlines() or [""]:
        if paragraph.strip() == "":
            wrapped.append("")
        else:
            wrapped.extend(textwrap.wrap(paragraph, width=inner_width) or [""])

    width = max((len(line) for line in wrapped), default=0)
    span = width + PAD_X * 2
    if actions is not None:
        span = max(span, len(actions) + 6)  # room for "━━ <actions> ━…"
        width = span - PAD_X * 2            # keep content lines aligned

    blank = "┃" + " " * span + "┃"
    if actions is not None:
        fill = span - len(actions) - 4     # 2 leading ━, a space each side
        bottom = "┗━━ " + actions + " " + "━" * max(fill, 0) + "┛"
    else:
        bottom = "┗" + "━" * span + "┛"

    print()                              # spacing outside the box (top)
    print("┏" + "━" * span + "┓")
    print(blank)                         # spacing inside the box (top)
    for line in wrapped:
        print("┃" + " " * PAD_X + line.ljust(width) + " " * PAD_X + "┃")
    print(blank)                         # spacing inside the box (bottom)
    print(bottom)
    print()                              # spacing outside the box (bottom)


def _read_key() -> str:
    """Read a single keypress (lowercased), no Enter required.

    Uses cbreak mode so the key is captured immediately while Ctrl-C still
    raises. Falls back to line-based reading when stdin isn't a TTY (tests,
    pipes).
    """
    if not sys.stdin.isatty():
        return sys.stdin.readline().strip().lower()[:1]

    import termios
    import tty

    fd = sys.stdin.fileno()
    old = termios.tcgetattr(fd)
    try:
        tty.setcbreak(fd)
        ch = sys.stdin.read(1)
    finally:
        termios.tcsetattr(fd, termios.TCSADRAIN, old)
    return ch.lower()


def _display_draft(draft: str) -> None:
    _render_box(draft, actions="[A] Accept   [R] Regenerate   [E] Edit   [C] Cancel")


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

    # Step 4 — Open Git repository (need the branch name for the checklist label)
    try:
        connector = GitConnector()
        branch = connector.current_branch()
    except GitConnectorError as e:
        typer.echo(str(e), err=True)
        raise typer.Exit(code=1)

    checklist = Checklist([
        f"Diffing from {type_} {start} from {branch} branch",
        "LLM generates release note",
        "Publishing to project",
    ])
    checklist.begin()

    # Step 4 (cont.) — Compute diff + Step 5 — Analyze (strip noise, truncate)
    checklist.active(0)
    try:
        commits, diff = connector.get_diff(start, end, type_)
        analysis = CodeAnalyzer().analyze(commits, diff)
    except GitConnectorError as e:
        checklist.fail(0)
        typer.echo(str(e), err=True)
        raise typer.Exit(code=1)
    checklist.done(0)

    # Step 6 — Generate release note draft
    checklist.active(1)
    try:
        generator = ReleaseNotesGenerator(model=config.llm_model, temperature=temperature)
        draft = generator.generate(analysis, project)
    except GenerationError as e:
        checklist.fail(1)
        typer.echo(str(e), err=True)
        raise typer.Exit(code=1)
    except Exception as e:
        checklist.fail(1)
        msg = _scrub(str(e), config.openai_api_key, config.project_token, config.api_token)
        typer.echo(f"Release note generation failed: {msg}", err=True)
        raise typer.Exit(code=1)
    checklist.done(1)

    publisher = RNPublisher(api_client, config.project_token)

    def _publish(body: str):
        checklist.active(2)
        try:
            result = publisher.publish(
                body=body,
                version_bump=version_bump,
                start_ref=start,
                end_ref=end,
                ref_type=ref_type_api,
            )
        except Exception as e:
            checklist.fail(2)
            msg = _scrub(str(e), config.project_token, config.api_token)
            typer.echo(f"Publish failed: {msg}", err=True)
            raise typer.Exit(code=1)
        checklist.done(2)
        checklist.stop()
        typer.echo(f"Published: {result['status']} — version {result['version']}")

    # Freeze the checklist so the generated note prints in its box below it.
    checklist.pause()

    # Step 7 — --publish bypass (no HITL): all three steps run back-to-back
    if publish:
        _render_box(draft)
        typer.echo(
            "⚠  Publishing release note without human review (--publish flag active).",
            err=True,
        )
        checklist.resume()
        _publish(draft)
        return

    # Step 8–13 — Interactive HITL loop. _display_draft renders the note box;
    # the publishing step resumes the checklist on accept.
    current_draft = draft
    while True:
        _display_draft(current_draft)

        # Wait for a single, valid keypress (no Enter needed).
        while True:
            choice = _read_key()
            if choice in ("a", "r", "e", "c"):
                break
            if choice in ("", "q", "\x03", "\x04"):  # EOF / quit / Ctrl-C / Ctrl-D
                typer.echo("Nothing published.", err=True)
                raise typer.Exit(code=1)

        if choice == "c":
            # Cancel — quit immediately without publishing
            typer.echo("Nothing published.")
            raise typer.Exit(code=0)

        if choice == "a":
            # Accept and publish
            checklist.resume()
            _publish(current_draft)
            return

        elif choice == "r":
            # Regenerate from same analysis
            try:
                with _spinner("Regenerating release note…"):
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


@app.callback(invoke_without_command=True)
def main(
    ctx: typer.Context,
    version: Annotated[
        bool,
        typer.Option("--version", "-V", callback=version_callback, is_eager=True),
    ] = False,
):
    # Called without a subcommand: show the `generate` help (the primary command).
    if ctx.invoked_subcommand is None:
        generate_cmd = ctx.command.get_command(ctx, "generate")
        with typer.Context(
            generate_cmd, info_name="generate", parent=ctx
        ) as sub_ctx:
            typer.echo(generate_cmd.get_help(sub_ctx))
        raise typer.Exit()


if __name__ == "__main__":
    app()
