"""LangSmith tracing setup for the CLI's LLM requests.

LangChain's ``ChatOpenAI`` auto-detects LangSmith from a handful of environment
variables at invoke time. This module turns the validated Config into those env
vars so tracing is opt-in, safely degrading, and consistently named — every
LLM call then shows up as a trace in the configured LangSmith project.
"""
import os
import sys

# Default LangSmith project used when none is configured explicitly.
DEFAULT_PROJECT = "rylees-cli"

def configure_tracing(
    *,
    enabled: bool,
    api_key: str | None,
    project: str | None,
    endpoint: str | None,
) -> bool:
    """Enable LangChain → LangSmith tracing via environment variables.

    Returns True when tracing was activated, False otherwise. Never raises: a
    misconfigured trace setup must not break a release-note run.
    """
    if not enabled:
        # Explicitly off so a stray env var can't silently turn tracing on.
        os.environ["LANGSMITH_TRACING"] = "false"
        return False

    if not api_key:
        os.environ["LANGSMITH_TRACING"] = "false"
        print(
            "LangSmith tracing requested but LANGSMITH_API_KEY is missing; "
            "continuing without tracing.",
            file=sys.stderr,
        )
        return False

    os.environ["LANGSMITH_TRACING"] = "true"
    os.environ["LANGSMITH_API_KEY"] = api_key
    os.environ["LANGSMITH_PROJECT"] = project or DEFAULT_PROJECT
    if endpoint:
        os.environ["LANGSMITH_ENDPOINT"] = endpoint
    return True
