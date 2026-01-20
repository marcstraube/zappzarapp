---
description: Manage project backlog tasks (add, list, prioritize)
context: fork
allowed-tools:
  Read, Write, Edit, Grep, Glob, Bash(date:*), Bash(ls:*), Bash(test:*),
  AskUserQuestion
argument-hint:
  --add | --list | --choose | --remove <task> | --promote <task> | --demote
  <task> | --reprioritize
---

# Backlog Management

Consistent management of project backlog tasks in `.claude/BACKLOG.md`.

## Arguments

Parse `$ARGUMENTS`:

- `--add`: Add a new task (interactive workflow)
- `--list`: Show all tasks (default if no argument)
- `--list --priority <high|medium|low>`: Filter by priority
- `--list --size <small|medium|large>`: Filter by task size
- `--choose`: Interactive task selection to start working on
- `--remove <task-name>`: Remove a task (with reason prompt)
- `--promote <task-name>`: Move task to higher priority
- `--demote <task-name>`: Move task to lower priority
- `--reprioritize`: Analyze all tasks and suggest priority changes

**Target Parameter (for --list and --add):**

- `--target project`: Team backlog (`./documentation/BACKLOG.md`) - committed,
  shared
- `--target user`: Personal project backlog (`./.claude/BACKLOG.md`) - not
  committed
- `--target global`: Personal global backlog (`~/.claude/BACKLOG.md`) -
  cross-project

If `--target` is not specified:

- **--add**: Default to `project` (team backlog); use `--target user/global` for
  personal tasks
- **--list**: Show project + user backlogs (project-relevant only; use
  `--target global` for global)

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

## Backlog Locations

Three backlog files are supported:

| Target    | Path                         | Committed | Purpose                         |
| --------- | ---------------------------- | --------- | ------------------------------- |
| `project` | `./documentation/BACKLOG.md` | Yes       | Team backlog, shared, reviewed  |
| `user`    | `./.claude/BACKLOG.md`       | No        | Personal tasks for this project |
| `global`  | `~/.claude/BACKLOG.md`       | N/A       | Personal cross-project tasks    |

**Auto-detection:**

- If only one backlog exists, use that one
- If multiple exist and no `--target` specified, behavior depends on operation
  (see Arguments)

**Use case guidance:**

- `project`: Team tasks, features, bugs that affect everyone
- `user`: Personal reminders, ideas to explore later in this project
- `global`: Learning topics, tool improvements, cross-project utilities

## File Structure

Both backlogs follow this structure:

```markdown
# Project Backlog

## Task Size Guide

| Size   | Time        | Files                   | AI Context                |
| ------ | ----------- | ----------------------- | ------------------------- |
| Small  | <30 min     | 1-3 files               | Same context OK           |
| Medium | 30 min - 2h | 3-10 files              | Flexible                  |
| Large  | >2h         | Many files, exploration | Fresh context recommended |

## High Priority

### Task Name

**Status:** Open | In Progress | Blocked **Scope:** feature | fix | critical |
breaking | refactor | docs | chore **Size:** Small | Medium | Large **Created:**
YYYY-MM-DD **Created by:** Name <user@example.com> ← (only for project target,
from git config) **Updated:** YYYY-MM-DD by Name <user@example.com> -
Description of change **Context:** Why this task exists

**Problem/Goal:** What needs to be done

**Implementation:** (optional)

- Step 1
- Step 2

**Files to modify/create:**

- `path/to/file.ext`

---

## Medium Priority

### Quick Wins

#### Small Task Name

...

### Category Name

#### Task Name

...

## Low Priority

...
```

---

## Workflow: Add Task (`--add`)

### Step 0: Determine Target and Scope

**Target (which backlog file):**

If `--target` is specified, use that backlog. Otherwise:

1. Default to `project` (`./documentation/BACKLOG.md`)
2. Create the file if it doesn't exist (use template from File Structure
   section)
3. User can override with `--target user` or `--target global` for personal
   tasks
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

### Step 3: Format Task Entry

**High Priority format (project target):**

```markdown
### Task Title

**Status:** Open **Scope:** feature **Size:** Large **Created:** YYYY-MM-DD
**Created by:** Name <user@example.com> **Updated:** YYYY-MM-DD by Name
<user@example.com> - Added subtasks ← (only if modified) **Planning:** Required
← (only if planning needed) **Context:** [How/why this was discovered]

**Problem:** [What's wrong or missing]

**Goal:** [What success looks like]

**Before starting:** Use Plan Mode to analyze: ← (only if Planning: Required)

- [What to investigate]
- [Architecture considerations]

**Implementation:**

1. Step one
2. Step two

**Files to modify:**

- `path/to/file.ext`

---
```

**Medium Priority format (project target, under category):**

```markdown
#### Task Title

**Status:** Open **Scope:** fix **Size:** Medium **Created:** YYYY-MM-DD
**Created by:** Name <user@example.com> **Planning:** Required ← (only if
planning needed) **Context:** [Brief context]

**Task:** [What needs to be done]

**Before starting:** Use Plan Mode to analyze: ← (only if Planning: Required)

- [What to investigate]

**Files to check/modify:**

- `path/to/file.ext`

---
```

**Quick Win format (minimal):**

```markdown
#### Task Title

**Status:** Planned **Scope:** chore **Size:** Small **Created:** YYYY-MM-DD
**Created by:** Name <user@example.com>

**Task:** [Brief description]

**Steps:**

1. Do X
2. Verify with Y

---
```

**Note:** For `--target user` and `--target global`, omit `**Created by:**` and
`**Updated:**` (personal backlogs).

**When to add `**Updated:**` (project target only):**

- Status change (Open → In Progress)
- Adding/completing subtasks
- Modifying scope, size, or priority
- Adding implementation details
- Any significant change by someone other than the creator

### Step 4: Insert Task

1. Read current BACKLOG.md
2. Find correct insertion point based on priority/category
3. Insert formatted task entry
4. Ensure proper spacing (blank lines, separators)

### Step 5: Confirmation

Show the user:

```text
Task Added to Backlog
════════════════════════════════════════════════
Title:    [Task Title]
Priority: [High/Medium/Low]
Scope:    [Small/Medium/Large]
Category: [Category if Medium]
Planning: [Required/Not required]
Created:  [Date]
════════════════════════════════════════════════
```

---

## Workflow: List Tasks (`--list`)

### Target Handling

1. If `--target project`: Show only `./documentation/BACKLOG.md`
2. If `--target user`: Show only `./.claude/BACKLOG.md`
3. If `--target global`: Show only `~/.claude/BACKLOG.md`
4. If no `--target`: Show project + user (project-relevant backlogs only)

### Without Filter

Display summary of all tasks:

```text
Project Backlog (./documentation/BACKLOG.md)
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

User Backlog (./.claude/BACKLOG.md)
════════════════════════════════════════════════

Medium Priority (2 tasks)
  • [feature/Small] Claude Settings Consolidation
  • [chore/Small]   DevDashboard Update Check

════════════════════════════════════════════════
Total: 2 tasks

(Use --target global to see ~/.claude/BACKLOG.md)

Size: Small=same context OK | Medium=flexible | Large=fresh context
```

### With Priority Filter (`--priority <level>`)

Show only tasks of specified priority with full details.

---

## Workflow: Choose Task (`--choose`)

Interactive selection of a task to work on.

### Step 1: Present Task Selection

Use `AskUserQuestion` to present available tasks grouped by priority:

```text
Which task would you like to work on?

○ v1.0 Release Preparation (High, Large)
○ Makefile Target Testing (High, Large)
○ pnpm Update (Medium, Small) ← same context OK
○ ESLint Errors in PHPStorm (Medium, Small) ← same context OK
○ PHP SuppressWarnings Cleanup (Medium, Medium)
○ Mutation Testing Integration (Medium, Medium)
```

**Selection logic:**

- Show High Priority tasks first
- Include category for Medium Priority tasks
- Limit to ~10 most relevant tasks (High + top Medium)
- User can select "Other" to specify by name

### Step 2: Display Task Details

After selection, show full task details:

```text
Selected Task
════════════════════════════════════════════════
Title:    Pre-Commit Hook Container Dependency
Priority: High
Status:   Open
Created:  2026-01-19

Context:
Pre-commit hook fails when containers aren't running

Problem:
The CaptainHook pre-commit hook uses `docker compose exec`
which requires the PHP container to be running.

Files to modify:
- captainhook.json
════════════════════════════════════════════════
```

### Step 3: Update Status

Set task status to "In Progress":

1. Find task in BACKLOG.md
2. Update `**Status:** Open` → `**Status:** In Progress`
3. Confirm status change

### Step 4: Begin Work

After displaying details:

```text
Task marked as "In Progress".
Ready to start working on: Pre-Commit Hook Container Dependency

What would you like to do?
○ Start implementation
○ Just show details (don't start yet)
○ Cancel
```

If "Start implementation" selected, acknowledge and wait for user direction.

---

## Workflow: Remove Task (`--remove <task>`)

Remove a task from the backlog with documentation.

### Step 1: Find Task

1. Search BACKLOG.md for task by name (fuzzy match)
2. If multiple matches, use AskUserQuestion to clarify
3. If no match, inform user and list similar tasks

### Step 2: Confirm and Ask Reason

Display task details and ask for removal reason:

```text
Task to Remove
════════════════════════════════════════════════
Title:    ESLint Errors in PHPStorm
Priority: Medium (Quick Wins)
Created:  2026-01-19
════════════════════════════════════════════════

Why is this task being removed?

○ Completed (handled outside normal workflow)
○ Duplicate (already covered by another task)
○ No longer relevant (requirements changed)
○ Won't fix (decided against implementation)
○ Other (provide reason)
```

### Step 3: Archive Task

Move task to `.claude/archive/backlog-removed.md`:

```markdown
# Removed Backlog Tasks

## ESLint Errors in PHPStorm

**Removed:** 2026-01-19 **Reason:** No longer relevant - PHPStorm was
misconfigured, not an ESLint issue **Original Priority:** Medium (Quick Wins)
**Original Created:** 2026-01-19

<original task content>

---
```

### Step 4: Remove from Backlog

1. Delete task section from BACKLOG.md
2. Maintain proper spacing and separators

### Step 5: Confirmation

```text
Task Removed
════════════════════════════════════════════════
Title:    ESLint Errors in PHPStorm
Reason:   No longer relevant
Archived: .claude/archive/backlog-removed.md
════════════════════════════════════════════════
```

---

## Workflow: Promote Task (`--promote <task>`)

Move a task to higher priority:

1. Find task by name (fuzzy match)
2. Determine current priority
3. Move to next higher level:
   - Low → Medium (ask for category)
   - Medium → High
   - High → Already highest (inform user)
4. Update task format if needed (#### → ###)
5. Show confirmation

---

## Workflow: Demote Task (`--demote <task>`)

Move a task to lower priority:

1. Find task by name (fuzzy match)
2. Determine current priority
3. Move to next lower level:
   - High → Medium (ask for category)
   - Medium → Low
   - Low → Already lowest (inform user)
4. Update task format if needed (### → ####)
5. Show confirmation

---

## Workflow: Reprioritize (`--reprioritize`)

Analyze all tasks and suggest priority changes based on multiple criteria.

### Step 1: Read All Backlogs

Read both project and user backlogs (if they exist) to get a complete picture.

### Step 2: Filter and Analyze Tasks

**Exclude from analysis:**

- Tasks with `**Status:** In Progress` (actively being worked on)
- Tasks with `**Status:** Blocked` (waiting on external dependency)

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

### Step 3: Generate Recommendations

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

### Step 4: User Approval

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

### Step 5: Apply Changes

For each approved change:

1. **Promote/Demote**: Use existing promote/demote logic
2. **Consolidate**: Merge task descriptions, keep most recent metadata
3. **Remove**: Use existing remove workflow (with archiving)

### Step 6: Summary

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

**user/global → project (making task public):**

- Add `**Created by:**` field from `git config user.name` and
  `git config user.email`
- Keep all other data intact

**project → user/global (making task personal):**

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

### Removing a Task

```text
$ /backlog --remove "eslint phpstorm"

Task to Remove
════════════════════════════════════════════════
Title:    ESLint Errors in PHPStorm
Priority: Medium (Quick Wins)
════════════════════════════════════════════════

Why is this task being removed?
● No longer relevant (requirements changed)

Reason details: PHPStorm was using wrong ESLint config,
fixed in IDE settings - not a project issue.

Task Removed
════════════════════════════════════════════════
Title:    ESLint Errors in PHPStorm
Reason:   No longer relevant
Archived: .claude/archive/backlog-removed.md
════════════════════════════════════════════════
```

### Promoting a Task

```text
$ /backlog --promote "pnpm update"

Task Promoted
════════════════════════════════════════════════
Task:     pnpm Update
Previous: Medium Priority (Quick Wins)
New:      High Priority
════════════════════════════════════════════════
```

### Listing with Target Filter

```text
$ /backlog --list --target global

Global Backlog (~/.claude/BACKLOG.md)
════════════════════════════════════════════════

High Priority (1 task)
  1. [feature/Medium] Cross-Project Test Utility Library

Medium Priority (2 tasks)
  • [chore/Small] Update Claude global config
  • [docs/Small]  Research MCP server options

════════════════════════════════════════════════
Total: 3 tasks
```

### Adding to Global Backlog

```text
$ /backlog --add --target global --scope feature

Title: Create shared ESLint config package
Priority: Medium
Size: Medium
Context: Same ESLint rules needed across multiple projects

Task Added to Global Backlog
════════════════════════════════════════════════
Title:    Create shared ESLint config package
Priority: Medium
Size:     Medium
Scope:    feature
Target:   Global (~/.claude/BACKLOG.md)
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
Target:   Project (./documentation/BACKLOG.md)
Created:  2026-01-20
════════════════════════════════════════════════
```

### Adding Personal Task for Current Project

```text
$ /backlog --add --target user --scope chore

Title: Clean up test fixtures
Priority: Low
Size: Small
Context: Personal reminder to tidy up test data

Task Added to User Backlog
════════════════════════════════════════════════
Title:    Clean up test fixtures
Priority: Low
Size:     Small
Scope:    chore
Target:   User (./.claude/BACKLOG.md)
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
