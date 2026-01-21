---
description: Manage project backlog tasks (add, list, prioritize)
context: fork
allowed-tools:
  Read, Write, Edit, Grep, Glob, Bash(date:*), Bash(ls:*), Bash(test:*),
  AskUserQuestion
argument-hint:
  --add | --list | --choose [--fast|--review] | --remove <task> | --promote
  <task> | --demote <task> | --reprioritize
---

# Backlog Management

Consistent management of project backlog tasks using the 3-layer architecture.

## Arguments

Parse `$ARGUMENTS`:

- `--add`: Add a new task (interactive workflow)
- `--list`: Show all tasks (default if no argument)
- `--list --priority <high|medium|low>`: Filter by priority
- `--list --size <small|medium|large>`: Filter by task size
- `--choose`: Interactive task selection to start working on
- `--choose --fast`: Skip plan review (override size-based default)
- `--choose --review`: Force plan review (override size-based default)
- `--remove <task-name>`: Remove a task (with reason prompt)
- `--promote <task-name>`: Move task to higher priority
- `--demote <task-name>`: Move task to lower priority
- `--reprioritize`: Analyze all tasks and suggest priority changes

**Target Parameter (for --list and --add):**

- `--target zappzarapp`: Boilerplate backlog (`./.zappzarapp/ai/BACKLOG.md`) -
  committed, for zappzarapp development tasks
- `--target project`: Team backlog (`./.ai/BACKLOG.md`) - committed, shared team
  tasks (user creates this)
- `--target personal`: Personal backlog
  (`~/.local/share/zappzarapp/BACKLOG.md`) - private cross-project tasks

If `--target` is not specified:

- **--add**: Default to `personal`; use `--target project` for team-shared
  tasks, `--target zappzarapp` for boilerplate development
- **--list**: Show personal backlog (use `--target project` for team,
  `--target zappzarapp` for boilerplate)

**Scope Parameter (task change type, for --add):**

- `--scope feature`: New functionality
- `--scope fix`: Bug fix
- `--scope critical`: Critical/security issue
- `--scope breaking`: Breaking change
- `--scope refactor`: Code improvement without behavior change
- `--scope docs`: Documentation only
- `--scope chore`: Maintenance, dependencies, tooling

If `--scope` is not specified during `--add`, determine from task context or ask
user.

## Backlog Locations (3-Layer Architecture)

Three backlog files are supported:

| Target       | Path                                   | Committed | Purpose                        |
| ------------ | -------------------------------------- | --------- | ------------------------------ |
| `zappzarapp` | `./.zappzarapp/ai/BACKLOG.md`          | Yes       | Boilerplate development tasks  |
| `project`    | `./.ai/BACKLOG.md`                     | Yes       | Team backlog, shared, reviewed |
| `personal`   | `~/.local/share/zappzarapp/BACKLOG.md` | N/A       | Personal cross-project tasks   |

**Auto-detection (if no `--target` specified):**

1. If `.claude/config.local.md` exists → personal (user has personal config)
2. Else if `.ai/BACKLOG.md` exists → project (team backlog present)
3. Else → zappzarapp (boilerplate development mode)

**Use case guidance:**

- `zappzarapp`: Tasks for zappzarapp boilerplate development
- `project`: Team tasks, features, bugs that affect everyone (user creates)
- `personal`: Private reminders, learning topics, cross-project utilities

## File Structure (Index + Detail)

Backlogs use a **compact index + detail files** format for context efficiency:

```text
~/.local/share/zappzarapp/         # Personal backlog (outside repo)
├── BACKLOG.md                     # Compact Index
└── backlog/
    └── ...

.ai/                               # Project backlog (created by make setup)
├── BACKLOG.md                     # Compact Index
└── backlog/
    └── ...

.zappzarapp/ai/                    # Boilerplate backlog
├── BACKLOG.md                     # Compact Index (~100 lines)
└── backlog/
    ├── task-slug.md               # Task details
    └── ...
```

**Note:** Only BACKLOG supports all 3 layers. Other knowledge files (LEARNINGS,
DECISIONS, REFERENCES) only exist at project (`.ai/`) and boilerplate
(`.zappzarapp/ai/`) level — no personal layer.

### Index Format (BACKLOG.md)

The index contains only parseable tables for quick scanning:

```markdown
# Project Backlog Index

## High Priority

| Slug         | Title             | Size  | Status  |
| ------------ | ----------------- | ----- | ------- |
| v1.0-release | v1.0 Release Prep | Large | Planned |
| critical-fix | Fix Auth Bypass   | Small | Open    |

## Medium Priority

### Quick Wins

| Slug            | Title               | Size  | Status |
| --------------- | ------------------- | ----- | ------ |
| add-retry-logic | Add Retry to Health | Small | Open   |

## Completed

| Slug             | Title               | Completed  |
| ---------------- | ------------------- | ---------- |
| service-examples | Service Integration | 2026-01-21 |
```

### Detail File Format (backlog/<slug>.md)

Each task has its own detail file:

```markdown
# Task Title

**Status:** Open | In Progress | Blocked | Complete **Size:** Small | Medium |
Large **Scope:** feature | fix | critical | refactor | docs | chore **Created:**
YYYY-MM-DD **Planning:** Required | Not required

## Context

Why this task exists.

## Goal

What success looks like.

## Implementation

1. Step one
2. Step two

## Files

- `path/to/file.ext`

## Notes

Additional context, references, decisions.
```

### Benefits of Index + Detail Format

1. **Context efficiency**: `--list` reads only ~100 lines instead of 1500+
2. **Parallel editing**: Multiple agents can work on different task files
3. **Version control**: Easier diffs, granular commit history
4. **Search**: `grep -l "keyword" backlog/*.md` finds relevant tasks

---

## Workflow: Add Task (`--add`)

### Step 0: Determine Target and Scope

**Target (which backlog file):**

If `--target` is specified, use that backlog. Otherwise:

1. Default to `personal` (`~/.local/share/zappzarapp/BACKLOG.md`)
2. Create the file if it doesn't exist (use template from File Structure
   section)
3. User can override with `--target project` for team-shared tasks or
   `--target zappzarapp` for boilerplate development
4. For `project` target: Read author from `git config user.name` and
   `git config user.email`

**Scope (change type):**

If `--scope` is specified, use it. Otherwise determine from task description or
ask:

```text
What type of change is this task?

○ Feature - New functionality (Recommended)
○ Fix - Bug fix
○ Critical - Security or critical issue
○ Breaking - Breaking change
○ Refactor - Code improvement
○ Docs - Documentation
○ Chore - Maintenance, tooling
```

### Step 1: Gather Information

Collect task details interactively or from context:

**Required:**

- **Title**: Short, descriptive name
- **Priority**: High / Medium / Low
- **Scope**: Small / Medium / Large (for AI context planning)
- **Context**: Why does this task exist? (discovered during X, user requested,
  etc.)
- **Problem/Goal**: What needs to be done?

**Scope Guidelines:**

- **Small**: <30 min, 1-3 files → Same context OK
- **Medium**: 30 min - 2h, 3-10 files → Flexible
- **Large**: >2h, many files, exploration → Fresh context recommended

**Optional:**

- **Category**: For Medium priority (Quick Wins, Code Quality, Testing, etc.)
- **Implementation steps**: How to accomplish it
- **Files to modify**: Known files that will be changed
- **Prerequisites**: Other tasks that must complete first
- **Requires Planning**: Ask user if task needs Plan Mode analysis before
  implementation

**Planning Question (ask via AskUserQuestion):**

```text
Does this task require planning before implementation?

○ Yes - Complex task, needs codebase analysis first (Recommended for new features, architectural changes)
○ No - Straightforward implementation, can start directly
```

If "Yes": Add `**Planning:** Required` field and "Before starting" section to
task.

### Step 2: Determine Placement

Based on priority and category:

| Priority | Placement                                          |
| -------- | -------------------------------------------------- |
| High     | Directly under `## High Priority`                  |
| Medium   | Under appropriate category in `## Medium Priority` |
| Low      | Under `## Low Priority`                            |

**Medium Priority Categories:**

- Quick Wins (small, easy tasks)
- Code Quality
- Testing Enhancements
- Infrastructure
- UI/UX
- IDE Integration
- Future Ideas (Brainstorm)

If no category fits, create a new one or place directly under Medium Priority.

### Step 3: Generate Task Slug

Create a URL-safe slug from the title:

- Lowercase
- Replace spaces with hyphens
- Remove special characters
- Max 40 characters

Examples:

- "Fix Auth Bypass" → `fix-auth-bypass`
- "Make Setup: API Docs" → `make-setup-api-docs`
- "v1.0 Release Preparation" → `v1.0-release-preparation`

### Step 4: Create Detail File

Create `backlog/<slug>.md` with full task details:

```markdown
# Task Title

**Status:** Open **Size:** Large **Scope:** feature **Created:** YYYY-MM-DD
**Planning:** Required

## Context

[Why this task exists]

## Goal

[What success looks like]

## Implementation

1. Step one
2. Step two

## Files

- `path/to/file.ext`
```

### Step 5: Update Index

Add a row to the appropriate table in BACKLOG.md:

```markdown
| slug-name | Task Title | Size | Status |
```

**Placement:**

- High Priority: Under `## High Priority` table
- Medium Priority: Under appropriate category table (Quick Wins, Security, etc.)
- Low Priority: Under `## Low Priority` table

### Step 6: Confirmation

Show the user:

```text
Task Added to Backlog
════════════════════════════════════════════════
Title:    [Task Title]
Slug:     [task-slug]
Priority: [High/Medium/Low]
Size:     [Small/Medium/Large]
Category: [Category if Medium]
Planning: [Required/Not required]
Created:  [Date]
Files:
  - BACKLOG.md (index updated)
  - backlog/[slug].md (detail created)
════════════════════════════════════════════════
```

---

## Workflow: List Tasks (`--list`)

**Context efficient**: Reads only BACKLOG.md index (~100 lines), not detail
files.

### Target Handling

1. If `--target zappzarapp`: Show only `./.zappzarapp/ai/BACKLOG.md`
2. If `--target project`: Show only `./.ai/BACKLOG.md`
3. If `--target personal`: Show only `~/.local/share/zappzarapp/BACKLOG.md`
4. If no `--target`:
   - If `.claude/config.local.md` exists → personal (user has personal config)
   - Else if `.ai/BACKLOG.md` exists → project (team backlog present)
   - Else → zappzarapp (boilerplate development mode)

### Without Filter

Parse index tables and display summary:

```text
Boilerplate Backlog (./.zappzarapp/ai/BACKLOG.md)
════════════════════════════════════════════════

High Priority (3 tasks)
  1. [feature/Large]  v1.0 Release Preparation
  2. [chore/Large]    Makefile Target Testing (BATS + Goss)
  3. [docs/Large]     Service Integration Examples

Medium Priority (8 tasks)
  Quick Wins (2)
    • [fix/Small]     pnpm Update
    • [fix/Small]     ESLint Errors in PHPStorm
  Code Quality (2)
    • [refactor/Medium] PHP SuppressWarnings Cleanup
    • [chore/Small]     Shell Compatibility
  ...

Low Priority (1 task)
  • [chore/Small] Service List Sorting Consistency

════════════════════════════════════════════════
Total: 12 tasks

(Use --target project for ./.ai/BACKLOG.md)
(Use --target personal for ~/.local/share/zappzarapp/BACKLOG.md)

Size: Small=same context OK | Medium=flexible | Large=fresh context
```

### With Priority Filter (`--priority <level>`)

Show only tasks of specified priority with full details.

---

## Workflow: Choose Task (`--choose`)

Interactive selection of a task to work on.

**Two-phase read**: Index first (for selection), then detail file (for
execution).

### Step 1: Check for Quick Wins Batch Option

Before presenting individual tasks, check if Quick Wins batch is available:

1. Parse BACKLOG.md index for `### Quick Wins` table
2. Count rows in Quick Wins table
3. If ≥2 Quick Wins exist, show batch option first

### Step 2: Present Task Selection

Use `AskUserQuestion` to present available tasks:

```text
Which task would you like to work on?

○ 🚀 Quick Wins (4 tasks) — parallel batch processing
    • Make Integrations in setup
    • Make Setup: API Docs
    • IDE Tasks Reduction
    • PhpStorm/VSCode Sync

○ v1.0 Release Preparation (High, Large)
○ Makefile Target Testing (High, Large)
○ PHP SuppressWarnings Cleanup (Medium, Medium)
○ Mutation Testing Integration (Medium, Medium)
```

**Selection logic:**

- **First option**: Quick Wins batch (if ≥2 tasks available)
- Show High Priority tasks next
- Include category for Medium Priority tasks
- Limit to ~10 most relevant tasks (High + top Medium)
- User can select "Other" to specify by name

### Step 2a: Quick Wins Batch Selected

If user selects "🚀 Quick Wins" batch option:

1. **Validate tasks are independent:**
   - Extract file lists from each Quick Win task
   - Check for overlapping files
   - If overlap found: warn and offer to exclude conflicting task

2. **Confirm batch processing:**

   ```text
   Quick Wins Batch Processing
   ════════════════════════════════════════════════
   Tasks to process in parallel:

   1. Make Integrations in setup
      Files: Makefile

   2. Make Setup: API Docs
      Files: Makefile

   ⚠️  Conflict: Tasks 1 and 2 both modify Makefile
      → Will process sequentially instead of parallel

   3. IDE Tasks Reduction
      Files: .vscode/tasks.json, .idea/runConfigurations/*

   4. PhpStorm/VSCode Sync
      Files: .vscode/settings.json
   ════════════════════════════════════════════════

   Proceed with batch processing?
   ○ Yes, process all (2 parallel + 2 sequential)
   ○ Yes, but exclude conflicting tasks
   ○ No, let me choose individual tasks
   ```

3. **Hand off to Main Agent** for Quick Wins batch workflow (see
   `.claude/agents/workflow.md` → "Quick Wins Batch Processing")

### Step 2b: Individual Task Selected

Continue with normal single-task workflow (Step 3 onwards).

### Step 3: Load Task Details

After selection, read the detail file `backlog/<slug>.md`:

1. Extract slug from user selection (from index table)
2. Read `backlog/<slug>.md` for full task details
3. Display task details to user:

```text
Selected Task
════════════════════════════════════════════════
Title:    Pre-Commit Hook Container Dependency
Slug:     pre-commit-hook-dependency
Priority: High
Status:   Open
Size:     Small
Created:  2026-01-19

Context:
Pre-commit hook fails when containers aren't running

Goal:
Change CaptainHook config to use `docker compose run`
instead of `docker compose exec`.

Files to modify:
- captainhook.json
════════════════════════════════════════════════
```

### Step 4: Update Status

Set task status to "In Progress":

1. Update `**Status:** Open` → `**Status:** In Progress` in detail file
2. Update status in index table (BACKLOG.md)
3. Confirm status change

### Step 5: Begin Work

After displaying details:

```text
Task marked as "In Progress".
Ready to start working on: Pre-Commit Hook Container Dependency

What would you like to do?
○ Start implementation
○ Just show details (don't start yet)
○ Cancel
```

If "Start implementation" selected, hand off to Main Agent for agent workflow.

### Step 6: Trigger Agent Workflow

Hand off to Main Agent with task context. Main Agent responsibilities:

1. **Create feature branch:** `git checkout -b feature/<slug>`
2. **Update status:** Set task to "In Progress" (if not already)
3. **Select workflow** based on task size (from detail file):

| Task Size | Workflow                                                  |
| --------- | --------------------------------------------------------- |
| Small     | Direct implementation → Lint → Test                       |
| Medium    | Plan-Agent → Implementation → Lint → Test                 |
| Large     | 4-Agent-Model (Architect → Coder → Reviewer → Documenter) |

See `.claude/agents/workflow.md` for full workflow details.

### Step 7: Plan Review Mode (for Medium/Large Tasks)

When Architect creates a plan, determine review behavior:

**Size-based defaults:**

| Task Size | Default Behavior | With `--fast` | With `--review` |
| --------- | ---------------- | ------------- | --------------- |
| Small     | Skip review      | (same)        | Force review    |
| Medium    | Ask user         | Skip review   | Force review    |
| Large     | Always review    | Skip review   | (same)          |

**After Architect creates plan:**

- If review required: Send ntfy notification, wait for user approval
- If skip: Continue directly to Coder agents

```bash
# ntfy notification for plan review (read topic from .claude/config.local.md)
curl -s -H "Priority: high" -H "Tags: hourglass" \
  -d "[zappzarapp] ⏳ Plan ready for review" ntfy.sh/<NTFY_TOPIC>
```

### Workflow Reference

For full agent workflow documentation, see:

- `.claude/agents/workflow.md` — Scope decision, Main Agent responsibilities
- `.claude/agents/architect.md` — Agent A: Analysis, plan creation
- `.claude/agents/coder.md` — Agent B: Implementation
- `.claude/agents/reviewer.md` — Agent C: Code review
- `.claude/agents/documenter.md` — Agent D: Documentation

---

## Workflow: Remove Task (`--remove <task>`)

Remove a task from the backlog with documentation.

### Step 1: Find Task

1. Search BACKLOG.md index for task by name or slug (fuzzy match)
2. If multiple matches, use AskUserQuestion to clarify
3. If no match, inform user and list similar tasks

### Step 2: Load Task Details

Read the detail file `backlog/<slug>.md` to display full context.

### Step 3: Confirm and Ask Reason

Display task details and ask for removal reason:

```text
Task to Remove
════════════════════════════════════════════════
Title:    ESLint Errors in PHPStorm
Slug:     eslint-errors-phpstorm
Priority: Medium (Quick Wins)
Created:  2026-01-19

Context:  [from detail file]
════════════════════════════════════════════════

Why is this task being removed?

○ Completed (handled outside normal workflow)
○ Duplicate (already covered by another task)
○ No longer relevant (requirements changed)
○ Won't fix (decided against implementation)
○ Other (provide reason)
```

### Step 4: Archive Task

1. Move detail file to archive: `backlog/<slug>.md` →
   `backlog/archive/<slug>.md`
2. Add removal metadata to the archived file:

```markdown
<!-- Archived: 2026-01-21 -->
<!-- Reason: No longer relevant - PHPStorm was misconfigured -->
<!-- Original Priority: Medium (Quick Wins) -->

# ESLint Errors in PHPStorm

[original content preserved]
```

### Step 5: Remove from Index

1. Delete the row from appropriate table in BACKLOG.md
2. Maintain proper table alignment

### Step 6: Confirmation

```text
Task Removed
════════════════════════════════════════════════
Title:    ESLint Errors in PHPStorm
Slug:     eslint-errors-phpstorm
Reason:   No longer relevant
Archived: backlog/archive/eslint-errors-phpstorm.md
Index:    Row removed from BACKLOG.md
════════════════════════════════════════════════
```

---

## Workflow: Promote Task (`--promote <task>`)

Move a task to higher priority.

### Step 1: Find Task

1. Search BACKLOG.md index for task by name or slug (fuzzy match)
2. Identify current priority from table location

### Step 2: Determine New Priority

- Low → Medium (ask for category)
- Medium → High
- High → Already highest (inform user and exit)

### Step 3: Update Index

1. Remove row from current priority table
2. Add row to new priority table
3. Maintain table alignment

### Step 4: Update Detail File (if priority tracked)

If the detail file contains priority metadata, update it:

```markdown
<!-- Priority: High (promoted from Medium on 2026-01-21) -->
```

### Step 5: Confirmation

```text
Task Promoted
════════════════════════════════════════════════
Task:     Shell Script Linting
Slug:     shell-script-linting
Previous: Medium Priority (Infrastructure)
New:      High Priority
Files:
  - BACKLOG.md (index moved)
  - backlog/shell-script-linting.md (metadata updated)
════════════════════════════════════════════════
```

---

## Workflow: Demote Task (`--demote <task>`)

Move a task to lower priority.

### Step 1: Find Task

1. Search BACKLOG.md index for task by name or slug (fuzzy match)
2. Identify current priority from table location

### Step 2: Determine New Priority

- High → Medium (ask for category)
- Medium → Low
- Low → Already lowest (inform user and exit)

### Step 3: Update Index

1. Remove row from current priority table
2. Add row to new priority table (ask for category if moving to Medium)
3. Maintain table alignment

### Step 4: Update Detail File (if priority tracked)

If the detail file contains priority metadata, update it:

```markdown
<!-- Priority: Low (demoted from Medium on 2026-01-21) -->
```

### Step 5: Confirmation

```text
Task Demoted
════════════════════════════════════════════════
Task:     WAF Integration
Slug:     waf-integration
Previous: Low Priority
New:      Future/v2.0 (or removed from active backlog)
Files:
  - BACKLOG.md (index moved)
  - backlog/waf-integration.md (metadata updated)
════════════════════════════════════════════════
```

---

## Workflow: Reprioritize (`--reprioritize`)

Analyze all tasks and suggest priority changes based on multiple criteria.

### Step 1: Read Index Files

Read BACKLOG.md index files from zappzarapp and project targets (if they exist).

**Context efficient**: Only reads index files (~100 lines each), not detail
files.

### Step 2: Load Details for Analysis

For tasks that need deeper analysis (blockers, prerequisites), load specific
detail files as needed.

### Step 3: Filter and Analyze Tasks

**Exclude from analysis:**

- Tasks with `In Progress` status (actively being worked on)
- Tasks with `Blocked` status (waiting on external dependency)

**Evaluate remaining tasks** against these criteria:

| Criterion             | Impact                                      |
| --------------------- | ------------------------------------------- |
| **Blocker Analysis**  | Task X blocks Task Y → X should be higher   |
| **Prerequisites**     | Dependencies must be completed first        |
| **Quick Wins**        | Small scope + high value → promote          |
| **Release Relevance** | v1.0 blockers → High Priority               |
| **Age**               | Tasks open >30 days → review relevance      |
| **Technical Debt**    | Accumulated debt → address medium-term      |
| **Duplicates**        | Similar tasks → suggest consolidation       |
| **Stale Tasks**       | No progress, unclear goal → suggest removal |

### Step 4: Generate Recommendations

Present findings grouped by action type:

```text
Backlog Reprioritization Analysis
════════════════════════════════════════════════

📊 Summary: 15 tasks analyzed, 5 recommendations

⬆️  PROMOTE (2 tasks)
────────────────────────────────────────────────
1. Shell Script Linting
   Current:  Medium Priority (Infrastructure)
   Suggest:  High Priority
   Reason:   Prerequisite for "Markdown Code Linting" task

2. Docker Compose Validation
   Current:  Medium Priority (Infrastructure)
   Suggest:  High Priority
   Reason:   Quick win (Small scope), blocks other infra tasks

⬇️  DEMOTE (1 task)
────────────────────────────────────────────────
1. WAF Integration
   Current:  Low Priority
   Suggest:  Future/v2.0 (or remove from backlog)
   Reason:   Large scope, not v1.0 relevant, no dependencies

🔀 CONSOLIDATE (1 suggestion)
────────────────────────────────────────────────
1. Merge "Markdown Code Linting" subtasks
   Tasks:    SQL Linting, Shell Linting in Markdown
   Reason:   Both are part of the same feature

🗑️  REVIEW/REMOVE (1 task)
────────────────────────────────────────────────
1. [Task Name]
   Created:  2026-01-01 (20 days ago)
   Reason:   No clear goal, may be obsolete
   Action:   Confirm still needed or remove

════════════════════════════════════════════════
```

### Step 5: User Approval

Use `AskUserQuestion` to let user select which changes to apply:

```text
Which changes would you like to apply?

☐ Promote: Shell Script Linting → High
☐ Promote: Docker Compose Validation → High
☐ Demote: WAF Integration → Future/v2.0
☐ Review: [Task Name] (will ask for removal reason)
○ Apply all recommendations
○ Apply selected only
○ Cancel (no changes)
```

### Step 6: Apply Changes

For each approved change:

1. **Promote/Demote**: Update both index (move row) and detail file (metadata)
2. **Consolidate**: Merge detail files, update index
3. **Remove**: Archive detail file, remove from index

### Step 7: Summary

Show final summary of applied changes:

```text
Reprioritization Complete
════════════════════════════════════════════════
Applied: 3 changes
- Promoted: Shell Script Linting → High
- Promoted: Docker Compose Validation → High
- Removed: [Task Name] (archived)

Skipped: 2 changes (user declined)
════════════════════════════════════════════════
```

---

## Moving Tasks Between Targets

When moving a task from one backlog to another:

**zappzarapp/personal → project (making task team-shared):**

- Add `**Created by:**` field from `git config user.name` and
  `git config user.email`
- Keep all other data intact

**project → zappzarapp/personal (making task non-team-shared):**

- Keep `**Created by:**` field (historical record of original author)
- Keep all other data intact

---

## Task Status Values

| Status        | Meaning                        |
| ------------- | ------------------------------ |
| `Open`        | Ready to be worked on          |
| `Planned`     | Scheduled for future work      |
| `In Progress` | Currently being worked on      |
| `Blocked`     | Waiting on external dependency |
| `Deferred`    | Postponed to later version     |

**Note:** Completed tasks are removed by `/commit` and transferred to
CHANGELOG.md.

---

## Priority Guidelines

**High Priority:**

- Blocks other work
- Security issues
- Critical bugs
- Release blockers

**Medium Priority:**

- Improvements to existing functionality
- Non-critical bugs
- Developer experience enhancements
- Documentation gaps

**Low Priority:**

- Nice-to-have features
- Far-future ideas
- Experimental concepts

---

## Examples

### Adding a Quick Win

```text
$ /backlog --add

Title: Add retry logic to health checks
Priority: Medium
Category: Quick Wins
Context: Health endpoint sometimes fails on slow container startup

Task Added to Backlog
════════════════════════════════════════════════
Title:    Add retry logic to health checks
Priority: Medium
Category: Quick Wins
Created:  2026-01-19
════════════════════════════════════════════════
```

### Adding a High Priority Task

```text
$ /backlog --add

Title: Fix authentication bypass vulnerability
Priority: High
Context: Security audit found issue in session handling
Problem: Session tokens not properly validated
Files: src/php/App/Auth/SessionHandler.php

Task Added to Backlog
════════════════════════════════════════════════
Title:    Fix authentication bypass vulnerability
Priority: High
Created:  2026-01-19
════════════════════════════════════════════════
```

### Adding a Task That Requires Planning

```text
$ /backlog --add

Title: Add .env.local override support
Priority: Medium
Category: Environment Configuration
Context: Feature request for flexible local configuration

Does this task require planning before implementation?
● Yes - Complex task, needs codebase analysis first

What should be analyzed in Plan Mode?
> Docker Compose env handling, PHP/Node dotenv libraries, impact on workflows

Task Added to Backlog
════════════════════════════════════════════════
Title:    Add .env.local override support
Priority: Medium
Category: Environment Configuration
Planning: Required
Created:  2026-01-19
════════════════════════════════════════════════
```

### Listing with Filter

```text
$ /backlog --list --priority high

High Priority Tasks
════════════════════════════════════════════════

### Database Password Configuration Issue
Status:  Open
Created: 2026-01-19
Context: Discovered during health endpoint testing

Problem: PostgreSQL returns "password authentication failed"
when health checks attempt database connections.

Files: .env.example, compose.yaml

---

### Pre-Commit Hook Container Dependency
Status:  Open
Created: 2026-01-19
...
```

### Choosing a Task

```text
$ /backlog --choose

Which task would you like to work on?
● Pre-Commit Hook Container Dependency (High)

Selected Task
════════════════════════════════════════════════
Title:    Pre-Commit Hook Container Dependency
Priority: High
Status:   Open → In Progress
Created:  2026-01-19

Problem: docker compose exec requires running container
Fix: Change to docker compose run in captainhook.json
════════════════════════════════════════════════

Ready to start implementation.
```

### Choosing Quick Wins Batch

```text
$ /backlog --choose

Which task would you like to work on?
● 🚀 Quick Wins (4 tasks)

Quick Wins Batch Processing
════════════════════════════════════════════════
Tasks to process in parallel:

1. Make Integrations in setup
   Files: Makefile

2. Make Setup: API Docs
   Files: Makefile

⚠️  Conflict: Tasks 1 and 2 both modify Makefile
   → Will process sequentially instead of parallel

3. IDE Tasks Reduction
   Files: .vscode/tasks.json, .idea/runConfigurations/*

4. PhpStorm/VSCode Sync
   Files: .vscode/settings.json
════════════════════════════════════════════════

Proceed with batch processing?
● Yes, process all (2 parallel + 2 sequential)

Processing Quick Wins...
════════════════════════════════════════════════
[1/4] Make Integrations in setup... ✅
[2/4] Make Setup: API Docs... ✅
[3/4] IDE Tasks Reduction... ⏳ (parallel)
[4/4] PhpStorm/VSCode Sync... ⏳ (parallel)
[3/4] IDE Tasks Reduction... ✅
[4/4] PhpStorm/VSCode Sync... ✅

Running lint checks... ✅

Quick Wins Batch Complete
════════════════════════════════════════════════
Completed: 4/4 tasks
Commit: chore: batch quick wins

Changes:
- Makefile (2 changes)
- .vscode/tasks.json (reduced from 140 to 28 tasks)
- .vscode/settings.json (synced with PhpStorm)
- .idea/runConfigurations/* (reduced from 130 to 28)

BACKLOG: 4 Quick Wins removed
CHANGELOG: 4 entries added
════════════════════════════════════════════════
```

### Removing a Task

```text
$ /backlog --remove "eslint phpstorm"

Task to Remove
════════════════════════════════════════════════
Title:    ESLint Errors in PHPStorm
Slug:     eslint-errors-phpstorm
Priority: Medium (Quick Wins)
════════════════════════════════════════════════

Why is this task being removed?
● No longer relevant (requirements changed)

Reason details: PHPStorm was using wrong ESLint config,
fixed in IDE settings - not a project issue.

Task Removed
════════════════════════════════════════════════
Title:    ESLint Errors in PHPStorm
Slug:     eslint-errors-phpstorm
Reason:   No longer relevant
Archived: backlog/archive/eslint-errors-phpstorm.md
Index:    Row removed from BACKLOG.md
════════════════════════════════════════════════
```

### Promoting a Task

```text
$ /backlog --promote "pnpm update"

Task Promoted
════════════════════════════════════════════════
Task:     pnpm Update
Slug:     pnpm-update
Previous: Medium Priority (Quick Wins)
New:      High Priority
Files:
  - BACKLOG.md (index moved)
  - backlog/pnpm-update.md (metadata updated)
════════════════════════════════════════════════
```

### Listing with Target Filter

```text
$ /backlog --list --target personal

Personal Backlog (~/.local/share/zappzarapp/BACKLOG.md)
════════════════════════════════════════════════

High Priority (1 task)
  1. [feature/Medium] Cross-Project Test Utility Library

Medium Priority (2 tasks)
  • [chore/Small] Update Claude global config
  • [docs/Small]  Research MCP server options

════════════════════════════════════════════════
Total: 3 tasks
```

### Adding to Personal Backlog

```text
$ /backlog --add --target personal --scope feature

Title: Create shared ESLint config package
Priority: Medium
Size: Medium
Context: Same ESLint rules needed across multiple projects

Task Added to Personal Backlog
════════════════════════════════════════════════
Title:    Create shared ESLint config package
Priority: Medium
Size:     Medium
Scope:    feature
Target:   Personal (~/.local/share/zappzarapp/BACKLOG.md)
Created:  2026-01-20
════════════════════════════════════════════════
```

### Adding Critical Fix to Project Backlog

```text
$ /backlog --add --target project --scope critical

Title: Fix SQL injection in search endpoint
Priority: High
Size: Small
Context: Security audit identified vulnerability

Task Added to Project Backlog
════════════════════════════════════════════════
Title:    Fix SQL injection in search endpoint
Priority: High
Size:     Small
Scope:    critical
Target:   Project (./.ai/BACKLOG.md)
Created:  2026-01-20
════════════════════════════════════════════════
```

### Adding Task to Boilerplate Backlog

```text
$ /backlog --add --target zappzarapp --scope chore

Title: Clean up test fixtures
Priority: Low
Size: Small
Context: Personal reminder to tidy up test data

Task Added to Boilerplate Backlog
════════════════════════════════════════════════
Title:    Clean up test fixtures
Priority: Low
Size:     Small
Scope:    chore
Target:   Boilerplate (./.zappzarapp/ai/BACKLOG.md)
Created:  2026-01-20
════════════════════════════════════════════════
```

### Reprioritizing the Backlog

```text
$ /backlog --reprioritize

Backlog Reprioritization Analysis
════════════════════════════════════════════════

📊 Summary: 18 tasks analyzed, 4 recommendations

⬆️  PROMOTE (2 tasks)
────────────────────────────────────────────────
1. Shell Script Linting
   Current:  Medium Priority (Infrastructure)
   Suggest:  High Priority
   Reason:   Prerequisite for "Markdown Code Linting"

2. Docker Compose Validation
   Current:  Medium Priority (Infrastructure)
   Suggest:  High Priority
   Reason:   Quick win (Small), improves DX

⬇️  DEMOTE (1 task)
────────────────────────────────────────────────
1. GitHub Pages Documentation
   Current:  Low Priority
   Suggest:  Future/v2.0
   Reason:   Not v1.0 relevant, can wait

🗑️  REVIEW (1 task)
────────────────────────────────────────────────
1. Frontend Testing Research
   Created:  2026-01-20 (today)
   Reason:   Very broad scope, consider splitting

════════════════════════════════════════════════

Which changes would you like to apply?
● Apply all recommendations

Reprioritization Complete
════════════════════════════════════════════════
Applied: 3 changes
- Promoted: Shell Script Linting → High
- Promoted: Docker Compose Validation → High
- Demoted: GitHub Pages Documentation → Future/v2.0

Skipped: 1 (user will review later)
════════════════════════════════════════════════
```

---

## Integration with Other Commands

- `/commit` removes completed tasks and adds them to CHANGELOG.md
- `/status --todo` shows high-priority backlog items
- `/session-start` reviews backlog for session planning

---

## Notes

- Task names should be unique and descriptive
- Always include creation date for tracking
- Context is crucial - future you needs to understand why
- Keep tasks atomic - split large tasks into subtasks
- Review backlog periodically with `/backlog --reprioritize`
