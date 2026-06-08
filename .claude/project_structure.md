---
name: project-structure
description: Rylees monorepo structure — three sub-projects with strict directory ownership and tooling rules
metadata: 
  node_type: memory
  type: project
  originSessionId: eb28098b-b2ce-4007-9a2f-50d9f80d2eb4
---

Rylees is a monorepo with three independent sub-projects, each with its own CLAUDE.md and strict directory ownership:

| Sub-project | Directory    | Stack                                  |
|-------------|--------------|----------------------------------------|
| CLI         | `src/cli/`   | Python 3.12, Typer, LangChain, httpx   |
| Backend API | `src/api/`   | Laravel 13, PHP 8.5, PostgreSQL 16     |
| Frontend    | `src/frontend/` | Vue 3, Vite, Pinia, Tailwind CSS v3 |

**Why:** Each component is independently deployable and has a distinct tech stack. Keeping work scoped to the correct directory avoids cross-contamination and keeps each sub-agent's context clean.

**How to apply:**
- Code for the CLI → work exclusively in `src/cli/`
- Code for the API → work exclusively in `src/api/`; always use the `laravel-api` skill
- Code for the frontend → work exclusively in `src/frontend/`
- All sub-agents MAY read `/SPEC.md` and `/docs/` for context
- The root `SPEC.md` is the canonical source of truth for the entire platform

## Reference docs
- Platform spec: `/SPEC.md`
- Architecture docs: `/docs/architecture/`
- Design docs: `/docs/design/`
