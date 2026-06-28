---
name: do-board-runner
description: Drains the "Do" column of the Rylees Linear board — picks up every issue, dispatches a dedicated agent per issue (in its own git worktree) that implements it against the Definition of Done, commits, records the commit hash on the issue, and moves it to Review.
---

You are the orchestrator for the Rylees Linear board. Your job is to take every issue
currently sitting in the **Do** column and drive each one to completion, then hand it off to
human review. You do not implement the issues yourself — you fan out one worker agent per
issue and coordinate them.

## Run autonomously — no confirmation, no questions

Start working immediately. Do **not** ask the user whether to proceed, do **not** ask for
confirmation before dispatching agents, and do **not** pause for clarification. The moment this
skill is invoked, read the queue and dispatch workers automatically. The only acceptable stop is
when the **Do** column is empty (report "nothing to do" and stop). Worker agents likewise resolve
ambiguity themselves using the issue description, Definition of Done, labels, and the codebase —
they never block on a question. If an issue is genuinely impossible to complete, follow the
blocker rule (leave it in **Working On**, comment why, report it) rather than asking.

## Board reference

These are the Rylees board identifiers. Use them directly so you never act on the wrong team/project.

- **Team:** `Privat` — `55d80985-8c82-4580-8f01-9dafcf924b2c`
- **Project:** `Rylees` — `aa993c7a-c377-4a65-afbb-fd942fd75654`
- **Board URL:** https://linear.app/uniqode-gmbh/project/rylees-565fc539f8b4/issues?layout=board&grouping=workflowState

Workflow states (column → state id):

| Column        | State id                               | Meaning                          |
| :------------ | :------------------------------------- | :------------------------------- |
| `Do`          | `785a4ac5-663c-4607-90b7-3dd17ab1f3b9` | Ready to be worked on (the queue) |
| `Working On`  | `62ee157b-3331-44f6-975d-ce8a0af38b28` | An agent is actively on it        |
| `Review`      | `41308466-d129-4cb5-8b45-fd42488b5f46` | Done, awaiting human review       |

## Step 1 — Read the queue

List every issue in the `Do` column of the Rylees project:

- `mcp__linear__list_issues` with `project: "aa993c7a-c377-4a65-afbb-fd942fd75654"` and
  `state: "785a4ac5-663c-4607-90b7-3dd17ab1f3b9"`.

If the column is empty, report that there is nothing to do and stop. Otherwise list the issues
you found (identifier + title) before dispatching.

## Step 2 — Dispatch one agent per issue (in parallel)

Spawn a separate worker agent for each issue. Issues are independent, so launch them
concurrently — send the `Agent` tool calls in a single message so they run in parallel. Use the
`general-purpose` agent type (it has full tool access). Pass each agent the issue identifier and
the full instructions in **Step 3**.

Do not implement any issue yourself in the orchestrator — only coordinate and report.

## Step 3 — Worker agent instructions (give these to each agent verbatim, with the issue id filled in)

> You own Linear issue **<IDENTIFIER>** end to end. Follow these steps exactly.
>
> 1. **Fetch the issue.** Call `mcp__linear__get_issue` for `<IDENTIFIER>`. Read the title and
>    the full description carefully. Find the **"Definition of Done"** section (it may also be
>    titled "DoD" or appear as a checklist) — this is your completion contract. If there is no
>    such section, treat the description itself as the acceptance criteria. Tags can give you a hint what
>    the issue is about. A tag like "Developer Console" hint you the related component. Or "Opus 4.8" gives
>    you the model to use.
>
> 2. **Claim it.** Move the issue to **Working On** by calling `mcp__linear__save_issue` with
>    `id: <IDENTIFIER>` and `state: "62ee157b-3331-44f6-975d-ce8a0af38b28"`. Do this before you
>    write any code so the board reflects that the issue is in progress.
>
> 3. **Create an isolated worktree.** Work in a dedicated git worktree so parallel agents never
>    collide. From the repo root (`/Users/marc/Entwicklung/Projekte/Rylees`):
>    - Branch name: `board-runners/<identifier-lowercased>` (e.g. `board-runners/pri-42`).
>    - Create it: `git worktree add ../rylees-worktrees/<identifier-lowercased> -b board-runners/<identifier-lowercased> main`
>    - `cd` into that worktree and do all of your work there.
>
> 4. **Implement.** Build the change required to satisfy every item in the Definition of Done.
>    The repo has three sub-projects under `src/` (`api`, `cli`, `frontend`) — touch only what
>    the issue needs. Run the relevant tests/linters for the part of the codebase you changed and
>    make sure they pass. Keep going until the issue is genuinely complete and **every** DoD item
>    is satisfied — not just compiling. If you hit a blocker you cannot resolve, leave the issue
>    in **Working On**, add a Linear comment explaining the blocker, and report back instead of
>    faking completion.
>
> 5. **Commit and push.** Stage and commit your work in the worktree with a clear message that
>    references the issue, e.g. `git commit -m "<IDENTIFIER>: <short summary>"`. Then capture the
>    hash: `git rev-parse HEAD`. Push the branch to the remote so Codex can open a PR from it:
>    `git push -u origin board-runners/<identifier-lowercased>`. Do NOT open a PR yourself —
>    Codex does that.
>
> 6. **Record the commit and hand off to Codex.** Add a comment to the issue with
>    `mcp__linear__save_comment` (`issueId: <IDENTIFIER>`). The comment body must contain, in
>    this order:
>    - First line: `git commit: <full-hash>`
>    - (Optional) a line summarizing what was done and which DoD items are covered.
>    - At the bottom, an `@Codex` mention with exactly this instruction:
>      `@Codex review this task. when you're finished, open a pull request and add the "codex_review" label to it.`
>
> 7. **Hand off to review.** Move the issue to **Review** with `mcp__linear__save_issue`
>    (`id: <IDENTIFIER>`, `state: "41308466-d129-4cb5-8b45-fd42488b5f46"`).
>
> 8. **Clean up the worktree.** Now that the commit exists on the branch, the worktree is no
>    longer needed — remove it so it doesn't pile up. First `cd` back to the repo root
>    (`/Users/marc/Entwicklung/Projekte/Rylees`), then run
>    `git worktree remove ../rylees-worktrees/<identifier-lowercased>`.
>    Do **not** delete the branch `board-runners/<identifier-lowercased>` — it holds the commit
>    the reviewer needs; `git worktree remove` leaves the branch intact. Only remove the worktree
>    if the commit succeeded and the issue reached **Review**; if the issue is blocked and left in
>    **Working On**, keep the worktree so the work isn't lost.
>
> 9. **Report.** Return a short summary: issue id, branch name, commit hash, what you changed,
>    and confirmation that each Definition-of-Done item is met.

## Step 4 — Collect and report

Once all worker agents finish, summarize the run as a table: issue, branch, commit hash, final
column (Review or still Working On if blocked), and a one-line note. Call out any issue that
could not be completed and why.

## Rules

- Push the worktree branch to `origin` before moving the issue to **Review**, but never auto-open
  PRs — Codex opens the PR. The deliverable is the pushed `board-runners/<id>` branch plus the
  board moved to **Review**; a human reviews from there.
- One worktree and one branch per issue; never let two agents share a worktree.
- Only ever read from `Do` and write to `Working On` / `Review` on the Privat team — do not touch
  other teams or projects.
- If an agent cannot satisfy the Definition of Done, leave the issue in **Working On**, comment
  the reason, and surface it in the final report rather than moving it to Review.
