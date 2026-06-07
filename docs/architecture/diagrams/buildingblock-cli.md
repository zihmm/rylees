# CLI Application Workflow

```mermaid
flowchart TD
    Dev(["Developer"])

    subgraph CLI["CLI application"]
        direction TB
        IH["Input handler"]
        subgraph DOM["Domain"]
            direction TB
            CA["Code Analizer"]
            RNG["Release Notes<br/>Generator"]
        end
        GC["Git Connector"]
        VAL["Validator"]
        RNP["RN Publisher"]
        RN[/"Release Notes"/]
    end

    GitRepo[("Git Repo")]
    LLM[("LLM")]
    API[("API")]

    %% Control / data flow
    Dev --> IH
    IH --> GC
    GitRepo -- "Commit A, B" --> GC
    GC -- "Diffs" --> CA
    CA --> RNG
    RNG <-- "generate" --> LLM
    RNG <--> VAL
    RNG -.-> RN
    RN --> RNP
    RNP -- "publish" --> API

    %% Styling (mirrors the original colour coding)
    classDef domain fill:#ffdc4a,stroke:#b89500,color:#333;
    classDef external fill:#e7e7e7,stroke:#999,color:#333;
    classDef artifact fill:#adf0c7,stroke:#5fbd86,color:#333;
    classDef entry fill:#595959,stroke:#333,color:#fff;

    class CA,RNG,GC,VAL,RNP domain;
    class GitRepo,LLM,API external;
    class RN artifact;
    class IH entry;
```

## Legend

- **Yellow** – the application's own components (`Code Analizer`, `Release Notes Generator`, `Git Connector`, `Validator`, `RN Publisher`).
- **Grey** – external systems the CLI talks to (`Git Repo`, `LLM`, `API`).
- **Green** – the produced artifact (`Release Notes`).
- **Dark** – the CLI entry point (`Input Handler`).
- **Domain** subgroup – core business logic (analysis + generation), kept separate from the connectors/adapters.

## Flow

1. The **Developer** runs the CLI → `Input Handler`.
2. `Input Handler` triggers the `Git Connector`.
3. `Git Repo` supplies **Commit A, B** to the `Git Connector`.
4. `Git Connector` passes the **Diffs** to the `Code Analizer`.
5. `Code Analizer` hands its result to the `Release Notes Generator`.
6. The generator calls the **LLM** to *generate* text and runs the output through the `Validator` (both bidirectional).
7. The generator emits the **Release Notes** artifact.
8. `RN Publisher` *publishes* the notes to the external `API`.
