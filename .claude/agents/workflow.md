# Agent Workflow

## Scope Decision

Automatic detection based on file count, complexity, and languages:

| Scope      | Trigger                    | Workflow                                     |
| ---------- | -------------------------- | -------------------------------------------- |
| Trivial    | 1 file, simple change      | Direct (typo, config, one-liner)             |
| Small      | 1-3 files, code changes    | Coder Agent → Lint → Test                    |
| Medium     | 3-10 files                 | Plan Mode → Coder → Lint → Test              |
| Large      | >10 files                  | 4-Agent-Model (Architect→Coder→Reviewer→Doc) |
| Quick Wins | Multiple independent tasks | Parallel Coder agents → Single commit        |
| Ad-hoc Fix | ≥2 languages, independent  | Parallel Fixer → Language-specific agents    |

## Pre-Flight Checks

Before starting any task, verify the environment is ready:

```bash
# 1. Containers running?
docker compose ps --format "{{.Name}}: {{.Status}}" | grep -q "running"

# 2. Branch clean? (no uncommitted changes)
git status --porcelain

# 3. On correct base branch?
git branch --show-current

# 4. Tests passing? (optional, for large tasks)
make test
```

### Pre-Flight Checklist

| Check                  | Command                     | Required         |
| ---------------------- | --------------------------- | ---------------- |
| Containers running     | `docker compose ps`         | Yes              |
| No uncommitted changes | `git status --porcelain`    | Yes              |
| Correct base branch    | `git branch --show-current` | Yes              |
| Tests passing          | `make test`                 | Large tasks only |
| No merge conflicts     | `git diff --check`          | Yes              |

### On Failure

| Issue                  | Resolution                   |
| ---------------------- | ---------------------------- |
| Containers not running | `make up`                    |
| Uncommitted changes    | Commit or stash              |
| Wrong branch           | `git checkout main`          |
| Tests failing          | Fix before starting new task |
| Merge conflicts        | Resolve first                |

## Feature-Branch Workflow

Each task is developed on its own feature branch:

```text
Task Start
    ↓
git checkout develop && git checkout -b feature/<task-slug>
    ↓
[Development + Intermediate Commits + CHANGELOG entries]
    ↓
User Review (entire branch)
    ↓
Merge → Close task (via `/tasks --close`)
```

### Branch Naming

Use Conventional Commits types (defined in `commitlint.config.js`):

| Type     | Pattern           | Example                       |
| -------- | ----------------- | ----------------------------- |
| Feature  | `feature/<slug>`  | `feature/redis-cache-service` |
| Fix      | `fix/<slug>`      | `fix/health-check-timeout`    |
| Refactor | `refactor/<slug>` | `refactor/database-layer`     |
| Chore    | `chore/<slug>`    | `chore/update-dependencies`   |
| Docs     | `docs/<slug>`     | `docs/api-documentation`      |
| Test     | `test/<slug>`     | `test/integration-coverage`   |
| CI       | `ci/<slug>`       | `ci/github-actions-workflow`  |
| Perf     | `perf/<slug>`     | `perf/optimize-queries`       |
| Build    | `build/<slug>`    | `build/webpack-config`        |
| Style    | `style/<slug>`    | `style/code-formatting`       |
| Revert   | `revert/<slug>`   | `revert/broken-feature`       |

### Why Feature Branches?

- **Isolation:** Changes don't affect main during development
- **Review:** Entire feature in one review
- **History:** Clean main history (one merge per feature)
- **Rollback:** Simply delete branch if needed
- **Session-Log:** Branch field has clear meaning

## Quick Wins Batch Processing

For multiple small, independent tasks from the "Quick Wins" section.

### When to Use

- Quick Wins section has ≥2 tasks
- Tasks are independent (no shared files, no dependencies)
- Each task modifies max 1-2 files

### Workflow

```text
/tasks --choose
    ↓
[1] 🚀 Quick Wins (4 tasks)  ← User selects batch option
    ↓
Main Agent validates:
  • All tasks independent? (no shared files)
  • All tasks Small scope?
  • No blocking dependencies?
    ↓
Spawn N parallel Coder agents (one per task)
    ↓
   ┌────┬────┬────┬────┐
   ↓    ↓    ↓    ↓    ↓
Task1 Task2 Task3 Task4 ...
   └────┴────┴────┴────┘
    ↓
Collect results from all agents
    ↓
Run lint checks (make check-quick)
    ↓
Single commit: "chore: batch quick wins"
    ↓
Close completed Quick Wins (via `/tasks --close`)
Update CHANGELOG: Add entries
```

### Agent Spawning

Each Quick Win task gets its own Coder agent:

```text
Main Agent spawns (parallel):
├── Task("Implement: Make Integrations in setup", subagent_type="general-purpose")
├── Task("Implement: Make Setup API Docs", subagent_type="general-purpose")
├── Task("Implement: IDE Tasks Reduction", subagent_type="general-purpose")
└── Task("Implement: PhpStorm/VSCode Sync", subagent_type="general-purpose")
```

**Agent prompt template:**

```text
Implement this Quick Win task:

Task: {task_title}
Description: {task_description}
Files: {files_to_modify}

Standards: Read .zappzarapp/standards/{language}.md

Requirements:
- Make minimal changes (this is a quick win, not a refactor)
- No architectural changes
- Report back: files changed, any issues encountered

DO NOT: Update knowledge files, tasks, or CHANGELOG (Main Agent handles this)
```

### Validation Before Batch

Main Agent checks before spawning:

| Check                      | Fail Action                   |
| -------------------------- | ----------------------------- |
| Tasks share files          | Split into sequential batches |
| Task has dependencies      | Move to end or exclude        |
| Task is Medium/Large scope | Exclude from batch, warn user |
| >6 Quick Wins              | Batch in groups of 6          |

### Result Collection

After all agents complete:

```text
Quick Wins Batch Results
════════════════════════════════════════════════
✅ Task 1: Make Integrations in setup
   Files: Makefile
   Status: Complete

✅ Task 2: Make Setup API Docs
   Files: Makefile
   Status: Complete

⚠️ Task 3: IDE Tasks Reduction
   Files: .vscode/tasks.json, .idea/runConfigurations/*
   Status: Partial (removed 80 tasks, kept 32)
   Note: Some tasks had dependencies, kept for safety

✅ Task 4: PhpStorm/VSCode Sync
   Files: .vscode/settings.json
   Status: Complete
════════════════════════════════════════════════
Summary: 4/4 tasks completed
```

### Commit Strategy

Single commit for all Quick Wins:

```text
chore: batch quick wins

- Make Integrations in setup
- Make Setup: API Docs generation
- IDE Tasks Reduction (80 removed, 32 kept)
- PhpStorm/VSCode Settings Sync

🤖 Generated with [Claude Code](https://claude.com/claude-code)
```

### Error Handling

| Scenario          | Action                                   |
| ----------------- | ---------------------------------------- |
| Agent fails       | Mark task as incomplete, continue others |
| Lint fails        | Show errors, ask user to fix or skip     |
| Conflict detected | Abort batch, run tasks sequentially      |

---

## 4-Agent-Model Overview

```text
Main Agent
    ↓
git checkout develop && git checkout -b feature/<task-slug>
    ↓
Agent A (Architect)
├── Analyzes requirements
├── Researches known challenges
└── Creates plan in .claude/temp/plan-<task>.md
    ↓
[Agent S Pre-Review]  ← Conditional
    ↓
┌───────┬───────┬───────┬───────┐  (parallel if independent)
↓       ↓       ↓       ↓       │
C1      C2      C3      C4      │
(PHP)   (Node)  (SQL)   (Infra) │
└───────┴───────┴───────┴───────┘
    ↓
┌───────┬───────┬───────┐
↓       ↓       ↓       │  (parallel)
R       [S]     D       │
(Review)(Sec)   (Docs)  │
└───────┴───────┴───────┘
    ↓
Main Agent
├── Collects R + D + S reports
├── Intermediate commits on feature branch
├── Informs user: "Branch ready for review"
└── After approval: Merge + close task
```

**Agent Mapping:**

- **A** = plan-architect (analyzes, plans)
- **C1** = coder-php (PHP application code)
- **C2** = coder-node (Node/TypeScript code)
- **C3** = coder-sql (SQL migrations, schemas)
- **C4** = coder-infra (Shell, Docker, Make, BATS/Goss tests)
- **R** = reviewer (unified code review for all languages)
- **D** = docs-auditor (documentation sync)
- **[S]** = security-auditor (conditional, see below)

**[S] Security Agent:** Runs conditionally based on scope and affected files.
See "Security Agent Triggering" section below.

## Security Agent Triggering

Agent S (Security Auditor) runs **conditionally** to avoid overhead on low-risk
tasks. See `.claude/agents/security-auditor.md` for full details.

### By Task Scope

| Scope      | Security Agent | Reason                       |
| ---------- | -------------- | ---------------------------- |
| `critical` | **Mandatory**  | Security/critical issues     |
| `feature`  | Conditional    | If touches auth/payment/data |
| `fix`      | Conditional    | If security-related          |
| `breaking` | Recommended    | API changes may expose risks |
| `refactor` | Optional       | Only if auth/security code   |
| `docs`     | No             | —                            |
| `chore`    | No             | —                            |

### By Affected Files

Trigger Security Agent if changed files match these patterns:

- `**/Auth/**`, `**/Security/**`, `**/Middleware/Auth*`
- `**/login*`, `**/password*`, `**/session*`, `**/token*`
- `**/User/**`, `**/Payment/**`, `**/Api/**`
- `**/migrations/**`, `**/Repository/**`
- `.env*`, `**/secrets*`, `Dockerfile*`

### Two-Phase Security Review

| Phase               | When                           | Focus                                       |
| ------------------- | ------------------------------ | ------------------------------------------- |
| Pre-Implementation  | After Architect, before Coders | Threat modeling, security requirements      |
| Post-Implementation | After Coders, with Reviewers   | OWASP checks, code review, dependency audit |

### Escalation

| Severity | Action                               |
| -------- | ------------------------------------ |
| Critical | Block merge, notify user immediately |
| High     | Block merge, fix required            |
| Medium   | Warning, fix recommended             |
| Low      | Document, fix optional               |

---

## Main Agent Responsibilities

### Branch Management

- **Task Start:** Create feature branch + set task status to "In Progress"
- **During Task:** All commits on feature branch
- **Task End:** Inform user, wait for review
- **After Approval:** Perform merge + close task (via `/tasks --close`)

### Coordination

- Spawns agents in correct order: A → [C1, C2, C3, C4 parallel] → [R, S, D
  parallel]
- Decides on parallelization:
  - Coders (C1-C4) run in parallel if independent
  - Reviewer (R) reviews all changed code (PHP, Node, SQL, Infra)
  - Security (S) only runs if security-relevant (see "Security Agent
    Triggering")
  - Docs (D) runs in parallel with R and S
- Collects results from all agents
- **Important:** Subagents are coordinated subprocesses, not separate contexts

### Session File (centralized)

- **Only Main Agent** updates knowledge files and CHANGELOG
- Subagents report back, don't write directly
- Prevents conflicts

### Maintain Standards

When new insights are discovered:

| Insight              | Target File                             |
| -------------------- | --------------------------------------- |
| New suppression      | `.zappzarapp/standards/<language>.md`   |
| New make target      | `.zappzarapp/standards/make-targets.md` |
| Workflow improvement | `.claude/agents/*.md`                   |

### Update Knowledge Files

After task completion, Main Agent updates knowledge files based on Completion
Report:

| Completion Report Section | Target                 | Action                     |
| ------------------------- | ---------------------- | -------------------------- |
| Learnings                 | `LEARNINGS.md` (inbox) | Append new entries         |
| Decisions (if any)        | `docs/adr/`            | New ADR document per entry |

**Path:** LEARNINGS uses 2-layer resolution (`.ai/` if exists, otherwise
`.zappzarapp/ai/`). ADRs: `docs/adr/` in user projects, `.zappzarapp/docs/adr/`
for boilerplate development.

**Timing:** After Completion Report, before "Branch ready for review" message.

**Format:** Follow existing format in each file (check headers, categories).

**Skip if:** No new learnings/decisions/references in Completion Report.

### Retry Limits & Escalation

```text
Agent C/R/D: Max 2 attempts
        ↓
Still errors?
        ↓
Main Agent decides:
  • Ask user
  • Try different approach
  • Mark task as blocked
```

### User Checkpoints

| After Phase     | Ask User?                               |
| --------------- | --------------------------------------- |
| Architect done  | Size-based (see below) + send ntfy      |
| Coder done      | Yes: "Review code before tests?" + ntfy |
| Reviewer errors | Yes: "Ignore these errors?" + ntfy      |
| Task complete   | Yes: "Branch ready for review" + ntfy   |

**Plan Review (Size-based default):**

| Task Size | Default     | Override                   |
| --------- | ----------- | -------------------------- |
| Small     | Skip plan   | `--plan` to force planning |
| Medium    | Ask user    | `--plan` or `--no-plan`    |
| Large     | Always plan | `--no-plan` to skip        |

Override via `/tasks --choose --plan` or `/tasks --choose --no-plan`.

**ntfy Notifications:**

**Setup:** Copy `.claude/config.local.md.example` to `.claude/config.local.md`
and set your ntfy topic.

```bash
# Task completed
curl -s -d "[zappzarapp] ✓ <task-description>" ntfy.sh/<NTFY_TOPIC>

# Waiting for user input
curl -s -H "Priority: high" -H "Tags: hourglass" \
  -d "[zappzarapp] ⏳ <context> - waiting for input" ntfy.sh/<NTFY_TOPIC>
```

## Task Status Management

Main Agent is responsible for task status updates:

| Event                          | Action                            |
| ------------------------------ | --------------------------------- |
| Feature branch created         | Set status `Open` → `In Progress` |
| Merge to main (after approval) | Close task (via `/tasks --close`) |

**Note:** `/tasks --choose` also sets status to "In Progress", but Main Agent
does it regardless of how the task was started. This ensures consistency.

**Status values:**

- `Open` — Ready to work on
- `In Progress` — Currently being worked on
- `Blocked` — Waiting on external dependency

Completed tasks are **removed**, not marked as "Completed". CHANGELOG is the
single source of truth for completed work.

## Commits & Documentation

### During Development (Feature Branch)

| Action              | CHANGELOG        | Task          |
| ------------------- | ---------------- | ------------- |
| Intermediate commit | ✅ Add entry     | ❌ Stays open |
| Further commit      | ✅ Another entry | ❌ Stays open |

### After User Approval (Merge)

| Action        | CHANGELOG       | Task                |
| ------------- | --------------- | ------------------- |
| Merge to main | Already entered | ✅ `/tasks --close` |

### Commit Workflow on Feature Branch

```text
C3 (SQL Coder) done + R (Reviewer) OK  ← (if schema changes needed)
    ↓
Commit: "feat(sql): add redis session tables"
CHANGELOG: Entry under "Features"
    ↓
C4 (Infra Coder) done + R (Reviewer) OK  ← (if infra changes needed)
    ↓
Commit: "feat(docker): add redis container config"
CHANGELOG: Entry under "Features"
    ↓
C1 (PHP Coder) done + R (Reviewer) OK
    ↓
Commit: "feat(php): add redis cache service"
CHANGELOG: Entry under "Features"
    ↓
C2 (Node Coder) done + R (Reviewer) OK
    ↓
Commit: "feat(node): add redis cache service"
CHANGELOG: Entry under "Features"
    ↓
D (Docs) done
    ↓
Commit: "docs: add redis service documentation"
    ↓
User: "Branch ready for review"
```

## Completion Workflow

### 1. Inform User

```text
Feature-Branch: feature/redis-cache-service
Commits: 5
Files changed: 15

Branch is ready for review.
Please review and approve for merge.
```

### 2. After User Approval

```bash
# Merge to main (or project's main branch)
git checkout main
git merge feature/<task-slug>

# Or squash merge for clean history
git merge --squash feature/<task-slug>
git commit -m "feat: add redis cache service"

# Close task
# /tasks --close <task-id>

# Delete feature branch
git branch -d feature/<task-slug>
```

## Completion Report

After task completion, Main Agent creates:

```markdown
## Task Report: <Task-Name>

### Summary

- **Status:** Successful / Partial / Failed
- **Branch:** feature/<task-slug>
- **Commits:** X commits on feature branch
- **Merge:** Pending User Approval / Merged

### Changes

| File | Action | Description |
| ---- | ------ | ----------- |

### Problems & Solutions

| Problem | Solution | Research? |
| ------- | -------- | --------- |

### Reviewer Results

| Check | Status | Notes |
| ----- | ------ | ----- |

### Documentation Results

| File | Status | Action |
| ---- | ------ | ------ |

### CHANGELOG Entries

- feat(php): ...
- feat(node): ...
- docs: ...

### Learnings (for LEARNINGS.md)

- [Learning 1]

### Open Items

- [ ] Follow-up task
```
