# Agent Workflow

A suggested, scope-based workflow: pick the lightest path that fits the task and
escalate to the full agent workflow only for large ones. Adapt it to your team —
nothing here is enforced.

## Scope Decision

Pick the lightest workflow that fits, based on file count, complexity and the
number of languages touched.

| Scope      | Trigger                       | Workflow                              |
| ---------- | ----------------------------- | ------------------------------------- |
| Trivial    | 1 file, typo/config/one-liner | Direct edit                           |
| Small      | 1–3 files, code changes       | Coder agent → lint → test             |
| Medium     | 3–10 files                    | Plan (`/plan`) → coder → lint → test  |
| Large      | >10 files or multi-language   | Full workflow (below)                 |
| Quick Wins | ≥2 independent small tasks    | Parallel coder agents → single commit |

## Roster

The project ships specialist **coder** agents that encode its standards (see
`.zappzarapp/standards/`). Planning, review and security use built-in Claude
Code capabilities — no custom agent needed.

| Role         | Who                                            |
| ------------ | ---------------------------------------------- |
| Plan         | `/plan` (built-in)                             |
| Code (PHP)   | `coder-php`                                    |
| Code (Node)  | `coder-node`                                   |
| Code (SQL)   | `coder-sql`                                    |
| Code (Infra) | `coder-infra` (shell, Docker, make, GOSS/BATS) |
| Review       | `/review` (built-in)                           |
| Security     | `/security-review` (built-in, when relevant)   |
| Docs         | `docs-auditor`                                 |

## Full Workflow (Large tasks)

```text
1. Plan       /plan — analyse, identify files, sketch the approach
2. Implement  coder-php / coder-node / coder-sql / coder-infra
              (spawn in parallel when the parts are independent)
3. Review     /review — all changed code
   Security   /security-review — when security-relevant (see below)
   Docs       docs-auditor — check docs match the code
4. Commit     main agent commits on a feature branch and reports back
```

The main agent coordinates: it spawns the coders, then runs review / security /
docs, collects the results, commits on a feature branch off the base branch, and
asks for review before merge. Subagents report back — only the main agent writes
knowledge files (prevents conflicts).

## Security Review — When

Run `/security-review` when the change touches security-relevant surface:

- Scope `critical` → always; `breaking` → recommended
- Files matching: `**/Auth/**`, `**/Security/**`, `**/User/**`, `**/Payment/**`,
  `**/Api/**`, `**/migrations/**`, `**/Repository/**`, `**/*login*`,
  `**/*password*`, `**/*token*`, `**/*session*`, `.env*`, `**/secrets*`,
  `Dockerfile*`

Skip it for pure docs/chore changes.

## Quick Wins — Parallel Batch

For ≥2 small, independent tasks (no shared files, no dependencies):

```text
1. Validate independence (no shared files, all Small scope)
2. Spawn one coder agent per task, in parallel
3. Collect results → make check → single commit
```

If tasks share files or depend on each other, run them sequentially instead.

## Feature Branches

Each task is developed on its own branch off the base branch, reviewed as a
whole, then merged (one merge per feature). Branch names follow the Conventional
Commits types defined in `commitlint.config.js`:

| Type     | Example                       |
| -------- | ----------------------------- |
| feature  | `feature/redis-cache-service` |
| fix      | `fix/health-check-timeout`    |
| refactor | `refactor/database-layer`     |
| chore    | `chore/update-dependencies`   |
| docs     | `docs/api-documentation`      |
| ci       | `ci/github-actions-workflow`  |

Intermediate commits stay on the branch; the task is closed after the merge
(`/tasks --close`). The CHANGELOG is generated from the Conventional Commit
messages (commitlint-enforced) — do not hand-write entries.
