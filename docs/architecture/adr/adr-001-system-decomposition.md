# ADR-001: System Decomposition into CLI Tool, Web UI & API

## Status

Accepted

## Context

The goal of the system is to simplify the creation and publication of customer-facing release notes for software projects.

The system must support two distinct usage contexts:

1. **Software developers** need to generate release notes based on source code changes between Git tags or branches. This process requires direct access to Git repositories and integration with a Large Language Model (LLM) to transform technical changes into customer-friendly language.

2. **Developers and customers** need a web-based platform to manage projects and access published release notes. The platform must provide a user-friendly interface and central access to release note history.

In addition, customer, project, user and release note data must be stored centrally and governed by consistent business rules.

Several architectural alternatives were considered:

### Alternative A: Single Web Application

A web application containing Git analysis, LLM integration, administration and publication functionality.

**Advantages**

- Single deployment unit
- Reduced number of components

**Disadvantages**

- Limited access to local Git repositories
- Poor integration with existing developer workflows
- Reduced automation capabilities
- Increased complexity of the web application

### Alternative B: CLI Tool with Direct Database Access

A standalone CLI tool interacting directly with the database.

**Advantages**

- Simpler architecture
- Fewer runtime components

**Disadvantages**

- Business logic duplication
- Tight coupling between CLI and persistence layer
- Increased security risks
- Lack of centralized governance

### Alternative C: Microservice Architecture

Independent services for customer management, project management, release notes and authentication.

**Advantages**

- Independent scalability
- Strong service isolation

**Disadvantages**

- Increased operational complexity
- Additional infrastructure requirements
- Complexity not justified by the project scope

## Decision

The system shall be decomposed into three primary architectural components:

### CLI Tool

The CLI Tool is responsible for:

- Analyzing Git differences
- Preparing structured change information
- Interacting with the LLM
- Generating release notes
- Publishing generated release notes to the backend

### Web UI

The Web UI is responsible for:

- User interaction
- Customer management
- Project management
- Reviewing and publishing release notes
- Displaying published release notes

### Backend API

The Backend API acts as the central application core and is responsible for:

- Business logic
- Authentication and authorization
- Data persistence
- Providing services to both the CLI Tool and Web UI

The Backend API serves as the single source of truth for all domain-related operations.

## Consequences

### Positive Consequences

- Clear separation of responsibilities
- Improved maintainability through component isolation
- Reuse of business logic across multiple clients
- Support for developer-centric workflows
- Seamless integration into CI/CD pipelines
- Independent evolution of generation and publication capabilities
- Consistent enforcement of business rules

### Negative Consequences

- Increased number of deployable components
- Additional network communication between components
- Higher implementation effort compared to a monolithic web application
- Dependency on the Backend API for all client interactions

### Impact on Quality Goals

This decision directly supports the project's primary quality goals:

| Quality Goal | Contribution |
|--------------|-------------|
| Maintainability | Clear separation of concerns and responsibilities |
| Extensibility | Independent evolution of system components |
| Testability | Components can be tested in isolation |
| Usability | Dedicated interfaces for different user groups |
| Future-proofing | Enables future replacement or extension of individual components |

## Governance

The architectural decomposition defined by this ADR shall be considered a foundational architectural decision.

The following governance rules apply:

- All business logic must be implemented within the Backend API.
- Neither the CLI Tool nor the Web UI may directly access the database.
- The CLI Tool is the only component responsible for Git analysis and LLM interaction.
- The Web UI must exclusively communicate with the Backend API for domain operations.
- New functionality shall be assigned to the component that best matches its responsibility according to this ADR.
- Any deviation from this architectural decomposition requires a new Architecture Decision Record (ADR) and an explicit review of its impact on the established quality goals.

This ADR serves as the basis for subsequent architectural decisions, including the modular monolith architecture of the Backend API, the integration of LLM services and the publication workflow for release notes.

## Notes

- **Approval date:** 2026-06-01
