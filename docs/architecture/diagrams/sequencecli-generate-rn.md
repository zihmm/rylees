# Release Notes CLI – Generate Release Notes

UML sequence diagram for the CLI `generate` command workflow, including the HITL review loop and API publish call.

## Legend

| Element | Description |
| --- | --- |
| **CLI modules** | `cli.py`, `CodeAnalyzer`, `ReleaseNotesGenerator`, `Validator`, `RNPublisher` |
| **External services** | `Git Repository`, `OpenAI API`, `Backend API` |
| **Actor** | `Developer` |
| `->>` | Sync call |
| `-->>` | Sync response |

## Diagram

```mermaid
sequenceDiagram
    actor Developer as Developer
    participant CLI as cli.py
    participant API as Backend API
    participant Git as Git Repository
    participant CA as CodeAnalyzer
    participant RNG as ReleaseNotesGenerator
    participant LLM as OpenAI API

    Developer->>CLI: rylees gen --start v1.2.0 --end v1.3.0

    CLI->>CLI: Load config (.env) — fail fast on missing vars

    CLI->>API: GET /projects/{RYLEES_PROJECT_TOKEN}
    API-->>CLI: { name, description, customer, llm: { temperature, tonality } }

    CLI->>Git: get_diff(start_ref, end_ref, ref_type)
    Git-->>CLI: (commits[], diff_string)

    CLI->>CA: analyze(commits, diff)
    CA->>CA: strip binary/lock-file diffs, truncate to 8000 tokens
    CA-->>CLI: AnalysisResult(diff, commit_messages)

    loop Until Developer accepts or cancels
        CLI->>RNG: generate(analysis, project_config)
        RNG->>LLM: ChatOpenAI.invoke(system_prompt, user_prompt)
        LLM-->>RNG: draft_text
        RNG->>RNG: Validator.validate(draft_text) — retry up to 3×
        RNG-->>CLI: draft_text

        CLI->>Developer: display draft between separator lines
        Note over Developer,CLI: [A] Accept   [R] Regenerate   [E] Edit

        alt Developer presses A
            CLI->>API: POST /projects/{token}/release-history
            API-->>CLI: { status: "published", version: "1.3.0" }
            CLI->>Developer: Published: published — version 1.3.0
        else Developer presses R
            Note over CLI: Re-run generate() with same analysis
        else Developer presses E
            CLI->>Developer: Open draft in $EDITOR
            Developer-->>CLI: Edited text
            Note over CLI: Re-display prompt with edited text
        end
    end
```

## Notes

- When `--publish` is passed, the HITL loop is skipped entirely. A warning is printed to stderr and the first valid draft is sent directly to the publish endpoint.
- The `Validator` checks that the draft is non-empty, ≥ 10 characters, and ≤ 2000 characters. After 3 consecutive failures a `GenerationError` is raised.
- Version computation is server-side: the CLI sends only `versionBump` (`major` / `minor` / `patch`), not the target version number.
