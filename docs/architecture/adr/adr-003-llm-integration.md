# ADR-003: Use of a Large Language Model for Release Note Generation

---

## Status 

Accepted

## Context

The primary purpose of the system is to transform technical software changes into customer-friendly release notes.

Source information such as Git diffs, commit messages and code modifications are typically written for developers and are often difficult for customers to understand. Creating release notes manually requires developers to analyze technical changes and translate them into business-oriented language, which is time-consuming and prone to inconsistencies.

Several alternatives were considered:

### Alternative A: Manual Creation of Release Notes

Developers manually write release notes based on code changes.

**Advantages**

- High level of control over content
- No dependency on external AI services

**Disadvantages**

- Significant manual effort
- Inconsistent writing style
- Increased risk of incomplete documentation
- Poor scalability for frequent releases

### Alternative B: Template-Based Generation

Release notes are generated using predefined templates and rules.

**Advantages**

- Predictable output
- No dependency on AI services

**Disadvantages**

- Limited flexibility
- Requires extensive maintenance of templates
- Difficulty handling complex changes
- Often produces repetitive and unnatural text

### Alternative C: Rule-Based Text Generation

Release notes are generated through custom logic and predefined mappings.

**Advantages**

- Full control over generated content
- Deterministic behavior

**Disadvantages**

- High implementation complexity
- Limited language quality
- Difficult to maintain and extend

## Decision

A Large Language Model (LLM) shall be used to generate customer-facing release notes.

The LLM receives structured change information derived from Git diffs and commit metadata and transforms this information into understandable, non-technical release notes.

The LLM integration is implemented within the CLI Tool and follows a controlled generation process consisting of:

1. Git change extraction
2. Change preprocessing and structuring
3. Prompt generation
4. LLM invocation
5. Result validation
6. Human review before publication

The generated output serves as a recommendation and is not published automatically.

## Consequences

### Positive Consequences

- Significant reduction of manual effort
- Improved readability for non-technical stakeholders
- Consistent writing style across projects
- Ability to summarize complex technical changes
- Faster release documentation process
- Improved scalability for frequent releases

### Negative Consequences

- Dependency on external AI services or models
- Additional operational costs
- Potential generation errors or hallucinations
- Output quality depends on prompt quality and input data
- Generated content requires validation before publication

### Impact on Quality Goals

| Quality Goal | Contribution |
|--------------|-------------|
| Usability | Improves readability for customers |
| Maintainability | Reduces manual documentation effort |
| Extensibility | Supports future improvements through model replacement |
| Efficiency | Accelerates release note creation |
| Consistency | Produces standardized output across releases |

## Governance

The use of LLMs within the system shall follow the following principles:

- The LLM is used exclusively to generate draft release notes.
- Generated content must be reviewed and approved by a developer before publication.
- Sensitive information such as credentials, secrets or access tokens must never be included in prompts.
- Prompt templates shall be standardized and maintained centrally.
- The LLM integration shall be abstracted through a dedicated client layer to reduce vendor lock-in.
- The system shall remain capable of replacing the underlying model or provider without significant architectural changes.
- Any future extension of LLM usage beyond release note generation requires a separate Architecture Decision Record.

This ADR establishes the foundation for AI-assisted release note generation while ensuring that quality, security and human oversight remain integral parts of the process.

## Notes

- **Approval date:** 2026-06-01