# Agent Workflow

## Scope Decision

Based on Task Scope Guide (BACKLOG.md):

| Scope  | Files | Workflow                                                  |
| ------ | ----- | --------------------------------------------------------- |
| Small  | 1-3   | Direct implementation → Lint → Test                       |
| Medium | 3-10  | Plan-Agent → Implementation → Lint → Test                 |
| Large  | >10   | 4-Agent-Model (Architect → Coder → Reviewer → Documenter) |

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
git checkout -b feature/<task-slug>
    ↓
[Development + Intermediate Commits]
    ↓
User Review (entire branch)
    ↓
Merge → Remove BACKLOG task
```

### Branch Naming

| Type     | Pattern           | Example                       |
| -------- | ----------------- | ----------------------------- |
| Feature  | `feature/<slug>`  | `feature/redis-cache-service` |
| Fix      | `fix/<slug>`      | `fix/health-check-timeout`    |
| Refactor | `refactor/<slug>` | `refactor/database-layer`     |

### Why Feature Branches?

- **Isolation:** Changes don't affect main during development
- **Review:** Entire feature in one review
- **History:** Clean main history (one merge per feature)
- **Rollback:** Simply delete branch if needed
- **Session-Log:** Branch field has clear meaning

## 4-Agent-Model Overview

```text
Main Agent
    ↓
git checkout -b feature/<task-slug>
    ↓
Agent A (Architect)
├── Analyzes requirements
├── Researches known challenges
└── Creates plan in .claude/temp/plan-<task>.md
        ↓
   ┌────┴────┬────┐  (parallel if independent)
   ↓         ↓    ↓
Agent B1   B2   B3
(PHP)    (Node) (SQL)
   └────┬────┴────┘
        ↓
   ┌────┴────────────────────────┐  (parallel)
   ↓      ↓     ↓     ↓     ↓    ↓
Agent C1  C2   C3    C4   [C5]  Agent D
(PHP)  (Node) (SQL) (MD) (Cfg)  (Docs)
   └────┬────────────────────────┘
        ↓
Main Agent
├── Collects C + D reports
├── Intermediate commits on feature branch
├── Informs user: "Branch ready for review"
└── After approval: Merge + BACKLOG cleanup
```

## Main Agent Responsibilities

### Branch Management

- **Task Start:** Create feature branch + set BACKLOG status to "In Progress"
- **During Task:** All commits on feature branch
- **Task End:** Inform user, wait for review
- **After Approval:** Perform merge + remove task from BACKLOG

### Coordination

- Spawns agents in correct order
- Decides on parallelization (B1/B2/B3, C1/C2/C3/C4/C5, D)
- C5 (Config Sync) only runs if config files changed
- Collects results
- **Important:** Subagents are coordinated subprocesses, not separate contexts

### Session File (centralized)

- **Only Main Agent** updates session file
- Subagents report back, don't write directly
- Prevents conflicts

### Maintain Standards

When new insights are discovered:

| Insight              | Target File                             |
| -------------------- | --------------------------------------- |
| New suppression      | `.zappzarapp/standards/<language>.md`   |
| New make target      | `.zappzarapp/standards/make-targets.md` |
| Workflow improvement | `.claude/agents/*.md`                   |

### Retry Limits & Escalation

```text
Agent B/C/D: Max 2 attempts
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

| Task Size | Default       | Override                   |
| --------- | ------------- | -------------------------- |
| Small     | Skip review   | `--review` to force review |
| Medium    | Ask user      | `--fast` or `--review`     |
| Large     | Always review | `--fast` to skip           |

Override via `/backlog --choose --fast` or `/backlog --choose --review`.

**ntfy Notifications:**

Send ntfy notification when waiting for user input. Read topic from
`.claude/config.local.md`.

```bash
curl -s -H "Priority: high" -H "Tags: hourglass" \
  -d "[zappzarapp] ⏳ <context> - waiting for input" ntfy.sh/<NTFY_TOPIC>
```

## BACKLOG Status Management

Main Agent is responsible for BACKLOG status updates:

| Event                          | Action                            |
| ------------------------------ | --------------------------------- |
| Feature branch created         | Set status `Open` → `In Progress` |
| Merge to main (after approval) | Remove task from BACKLOG entirely |

**Note:** `/backlog --choose` also sets status to "In Progress", but Main Agent
does it regardless of how the task was started. This ensures consistency.

**Status values:**

- `Open` — Ready to work on
- `In Progress` — Currently being worked on
- `Blocked` — Waiting on external dependency

Completed tasks are **removed**, not marked as "Completed". CHANGELOG is the
single source of truth for completed work.

## Commits & Documentation

### During Development (Feature Branch)

| Action              | CHANGELOG        | BACKLOG       |
| ------------------- | ---------------- | ------------- |
| Intermediate commit | ✅ Add entry     | ❌ Task stays |
| Further commit      | ✅ Another entry | ❌ Task stays |

### After User Approval (Merge)

| Action        | CHANGELOG       | BACKLOG        |
| ------------- | --------------- | -------------- |
| Merge to main | Already entered | ✅ Remove task |

### Commit Workflow on Feature Branch

```text
B3 (SQL) done + C3 (SQL Review) OK  ← (if schema changes needed)
    ↓
Commit: "feat(sql): add redis session tables"
CHANGELOG: Entry under "Features"
    ↓
B1 (PHP) done + C1 (PHP Review) OK
    ↓
Commit: "feat(php): add redis cache service"
CHANGELOG: Entry under "Features"
    ↓
B2 (Node) done + C2 (Node Review) OK
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

# BACKLOG cleanup
# Remove task from BACKLOG.md

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
