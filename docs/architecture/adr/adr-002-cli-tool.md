# ADR-002: Use of a Dedicated CLI Tool for Release Note Generation

---

## Status

Accepted

## Context

The primary users responsible for creating release notes are software developers. The generation process requires access to source code repositories, Git metadata, and code differences between releases.

The solution must support both manual execution by developers and future automation through CI/CD pipelines. Furthermore, the release note generation process includes interactions with a Large Language Model (LLM), which transforms technical code changes into customer-friendly release notes.

Several alternatives were considered:

### Alternative A: Release Note Generation within the Web Application

The generation functionality is implemented directly within the Web UI and Backend API.

**Advantages**

- Single user interface
- Reduced number of application components

**Disadvantages**

- Limited access to local Git repositories
- More complex web application
- Reduced integration with developer workflows
- More difficult automation in CI/CD environments

### Alternative B: Desktop Application

A dedicated desktop application is used to generate release notes.

**Advantages**

- Rich user experience
- Local repository access

**Disadvantages**

- Additional distribution and maintenance effort
- Platform-specific considerations
- Limited automation capabilities

### Alternative C: Generation as a Backend Service

Git repositories are accessed and processed entirely by the backend.

**Advantages**

- Centralized implementation
- Simplified client applications

**Disadvantages**

- Repository access becomes more complex
- Increased infrastructure requirements
- Additional security considerations
- Less flexibility for developers

## Decision

A dedicated Command Line Interface (CLI) Tool shall be used for generating release notes.

The CLI Tool is responsible for:

- Accessing Git repositories
- Analyzing differences between tags or branches
- Preparing structured change information
- Building prompts for the LLM
- Generating release notes using the configured LLM
- Publishing generated release notes to the Backend API

The CLI Tool operates independently from the Web UI and communicates with the Backend API through defined interfaces.

## Consequences

### Positive Consequences

- Direct integration into developer workflows
- Native access to local Git repositories
- Easy integration into CI/CD pipelines
- Clear separation between generation and publication responsibilities
- Independent evolution of generation functionality
- Reduced complexity within the Web UI

### Negative Consequences

- Additional component to develop and maintain
- Separate distribution mechanism required
- Authentication required for communication with the Backend API
- Additional documentation needed for CLI usage

### Impact on Quality Goals

| Quality Goal | Contribution |
|--------------|-------------|
| Usability | Optimized workflow for developers |
| Maintainability | Separation of generation and management concerns |
| Extensibility | Independent evolution of CLI functionality |
| Testability | CLI can be tested independently from the Web UI |
| Automation | Supports integration into CI/CD processes |

## Governance

The CLI Tool is the only component responsible for release note generation.

The following governance rules apply:

- Git repository access shall be implemented exclusively within the CLI Tool.
- LLM integration shall be encapsulated within the CLI Tool.
- The CLI Tool shall not directly access the database.
- All persistence operations shall be performed through the Backend API.
- Generated release notes shall be submitted to the Backend API using documented interfaces.
- Future automation scenarios shall reuse the CLI Tool rather than introducing alternative generation mechanisms.

Any change to the responsibility boundaries defined in this ADR requires a new Architecture Decision Record and an assessment of its impact on maintainability, automation capabilities and architectural consistency.

## Notes

- **Approval date:** 2026-06-01