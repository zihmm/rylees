# Release Notes CLI – Generate Release Notes 

UML sequence diagram for CLI workflow to generate a release note.

## Legend

| Element | Description |
| --- | --- |
| **CLI module** | `CodeAnalyzer`, `ReleaseNotesGenerator` |
| **External services** | `Git Repository`, `LLM` |
| **Actor** | `Entwickler` |
| `->>` | Sync call |
| `-->>` | Sync response |

## Diagram

```mermaid
sequenceDiagram
    %% Legend:
    %%   CLI moduld:        CodeAnalyzer, ReleaseNotesGenerator
    %%   External services: Git Repository, LLM
    %%   ->>  = Sync call
    %%   -->> = Sync response

    actor Developer as Developer
    participant CA as CodeAnalyzer
    participant Git as Git Repository
    participant RNG as ReleaseNotesGenerator
    participant LLM as LLM

    Developer->>CA: rylees generate --from commit1 --to commit2

    CA->>Git: repo.get_commits(commit1, commit2)
    Git-->>CA: tupel(commit1, commit2)

    CA->>CA: diff = generate_diff(commit1, commit2)

    CA->>RNG: create_note(diff)

    loop Until RN accepted by user
        RNG->>LLM: llm.invoke(prompt, diff, context)
        LLM-->>RNG: llm_response_text
        RNG->>RNG: validate()
        RNG->>Entwickler: display_response(llm_response_text)
        Developer->>RNG: accept() || decline()
    end
```