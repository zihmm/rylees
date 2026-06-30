---
name: rylees-release-note
description: Generate and publish customer-facing release notes with the `rylees` CLI. Use when asked to publish a release note, produce a release history, or turn a commit/tag range into a versioned note for a Rylees project. Covers the generate command, version bumping, commit-range semantics, and the local-dev model/setup gotchas.
---

# Rylees release-note CLI

`rylees` is an LLM-assisted release-note generator and publisher. It diffs two
points in Git history, asks an LLM to draft a note in the project's configured
tone and language, optionally lets a human review it, and publishes it to the
Rylees backend. The published version (e.g. `0.4.2`) is computed server-side.

## When to use this skill

- The user asks to publish a release note, build a release history, or version
  a set of commits into customer notes.
- The user names a commit range or tag range to "turn into a release note".

## Prerequisites (run from the repo root)

The CLI reads a `.env` from the current working directory (searching upward),
and opens the Git repo at `.`. Always run it from the project root.

Required `.env` keys: `RYLEES_API_TOKEN`, `RYLEES_PROJECT_TOKEN`,
`OPENAI_API_KEY`. Optional: `RYLEES_API_URL` (defaults to the prod API),
`RYLEES_LLM_MODEL`, `RYLEES_LLM_TEMPERATURE`.

**Local-dev gotcha — model override.** The project's default model is `GPT-5.4`,
which a normal OpenAI key cannot access (the generate step fails with a 404
`model_not_found`). Override it for the session:

```bash
export RYLEES_LLM_MODEL=gpt-4o   # any model the key can access; gpt-4o honors custom temperature
```

The note's **language and tonality come from the project config** (fetched from
the API), not from flags — e.g. a German project produces German notes.

## Command

```bash
rylees generate --type commit --start <ref> --end <ref> [--major|--minor|--patch] [--publish]
```

| Flag | Meaning |
| --- | --- |
| `--type` | `tag` (default) or `commit`. Use `commit` for raw hashes. |
| `--start` / `-s` | Start ref (**required**). |
| `--end` / `-e` | End ref (default `HEAD`). |
| `--major` / `--minor` / `--patch` | Version bump; exactly one. Defaults to `--minor`. |
| `--publish` / `-p` | Skip the human-review (HITL) prompt and publish immediately. Use for automation. |

Without `--publish`, the CLI shows the draft and waits for a key:
`[A] Accept  [R] Regenerate  [E] Edit  [C] Cancel`.

## How versioning works (important)

The version is **not** taken from a flag value — the server starts from the
latest published note's version (or `0.0.0` for a project with no notes) and
applies the bump:

- `major` → `X+1.0.0`  ·  `minor` → `X.Y+1.0`  ·  `patch` → `X.Y.Z+1`

So a clean project (no notes) bumped `minor ×4` then `patch ×2` lands on
`0.4.2`. To hit a specific target version you must control the starting state
and the sequence of bumps. To reset a project to `0.0.0`, delete its
`release_notes` rows (e.g. via the Postgres container in local dev).

## Commit-range semantics (avoid gaps/overlaps)

The diff is `git diff <start> <end>` over the range `<start>..<end>`, which
**excludes the start commit** and includes everything up to and including end.
When splitting history into contiguous chunks, set each chunk's
`--start` to the **previous chunk's `--end`**:

```
chunk 1: start = <root>   end = E1
chunk 2: start = E1       end = E2
chunk 3: start = E2       end = E3   ...
```

The very first commit's own diff is unavoidably excluded (nothing precedes it).
Verify boundaries form an ancestor chain before publishing:
`git merge-base --is-ancestor <earlier> <later>`.

## Recipe: publish a multi-chunk release history

1. Decide the chunk boundaries from `git log --oneline --reverse`; record the
   start/end hash of each chunk.
2. Ensure the target project is at the expected starting version (wipe notes for
   a clean `0.0.0` if needed).
3. `export RYLEES_LLM_MODEL=gpt-4o` (local dev).
4. Publish each chunk **in order**, contiguous boundaries, correct bump flags:

```bash
rylees generate --type commit --start <prevEnd> --end <chunkEnd> --minor --publish
```

5. Confirm each returned `Published: published — version X.Y.Z` matches the plan.
6. Verify the result:
   `curl -s "$RYLEES_API_URL/public/release-history/<customerSlug>/<projectKey>"`.

**Batching gotcha — zsh does not word-split.** The default shell here is zsh,
where unquoted `$var` is NOT split on whitespace (`set -- $row` / `for x in $row`
keep the whole string as one field). A loop that splits a `"bump start end"`
row that way silently builds a malformed command, every publish fails, and a
naive `[ "$got" = "$expected" ]` check passes on empty-equals-empty. Drive the
batch with `while read -r bump start end expected; do … done <<'ROWS' … ROWS`
(here-doc + `read` splits reliably), redirect the CLI's stdin from `/dev/null`,
and only print OK when the version is **non-empty AND equals** the expected one.

## Notes

- Publishing is an outward-facing, hard-to-reverse action. Confirm the target
  project and version plan before running with `--publish`.
- The CLI redacts tokens in error output; still avoid echoing `.env` values.
