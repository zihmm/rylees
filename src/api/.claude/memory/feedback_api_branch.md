---
name: feedback-api-branch
description: "Always work on the implementations/api branch (or a worktree off it) for backend Laravel API changes"
metadata:
  node_type: memory
  type: feedback
---

For any backend Laravel API work (the `src/api/` modular monolith), use the
**`implementations/api`** branch. Before starting any change or feature, FIRST check out
`implementations/api` — or create a git worktree off it — instead of working on `main`.

**Why:** The user keeps backend API development isolated on a dedicated feature branch;
committing API work directly to `main` is not wanted.

**How to apply:**
- Start API work with `git checkout implementations/api` (exists locally and on origin), or
  `git worktree add ../rylees-api implementations/api`.
- Confirm you are on that branch before editing files under `src/api/`.
- Only merge into `main` when the user explicitly asks.
- Related: [[feedback-laravel-quality]].
