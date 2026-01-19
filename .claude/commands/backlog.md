---
description: Manage project backlog tasks (add, list, prioritize)
context: fork
allowed-tools: Read, Write, Edit, Grep, Glob, Bash(date:*), AskUserQuestion
argument-hint: --add | --list | --choose | --remove <task> | --promote <task> | --demote <task>
---

# Backlog Management

Consistent management of project backlog tasks in `.claude/BACKLOG.md`.

## Arguments

Parse `$ARGUMENTS`:

- `--add`: Add a new task (interactive workflow)
- `--list`: Show all tasks (default if no argument)
- `--list --priority <high|medium|low>`: Filter by priority
- `--choose`: Interactive task selection to start working on
- `--remove <task-name>`: Remove a task (with reason prompt)
- `--promote <task-name>`: Move task to higher priority
- `--demote <task-name>`: Move task to lower priority

## File Structure

The backlog follows this structure:

```markdown
# Project Backlog

## High Priority
### Task Name
**Status:** Open | In Progress | Blocked
**Created:** YYYY-MM-DD
**Context:** Why this task exists

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

### Step 1: Gather Information

Collect task details interactively or from context:

**Required:**
- **Title**: Short, descriptive name
- **Priority**: High / Medium / Low
- **Context**: Why does this task exist? (discovered during X, user requested, etc.)
- **Problem/Goal**: What needs to be done?

**Optional:**
- **Category**: For Medium priority (Quick Wins, Code Quality, Testing, etc.)
- **Implementation steps**: How to accomplish it
- **Files to modify**: Known files that will be changed
- **Prerequisites**: Other tasks that must complete first

### Step 2: Determine Placement

Based on priority and category:

| Priority | Placement |
|----------|-----------|
| High | Directly under `## High Priority` |
| Medium | Under appropriate category in `## Medium Priority` |
| Low | Under `## Low Priority` |

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

**High Priority format:**

```markdown
### Task Title

**Status:** Open
**Created:** YYYY-MM-DD
**Context:** [How/why this was discovered]

**Problem:** [What's wrong or missing]

**Goal:** [What success looks like]

**Implementation:**
1. Step one
2. Step two

**Files to modify:**
- `path/to/file.ext`

---
```

**Medium Priority format (under category):**

```markdown
#### Task Title

**Status:** Open
**Created:** YYYY-MM-DD
**Context:** [Brief context]

**Task:** [What needs to be done]

**Files to check/modify:**
- `path/to/file.ext`

---
```

**Quick Win format (minimal):**

```markdown
#### Task Title

**Status:** Planned
**Created:** YYYY-MM-DD

**Task:** [Brief description]

**Steps:**
1. Do X
2. Verify with Y

---
```

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
Category: [Category if Medium]
Created:  [Date]
════════════════════════════════════════════════
```

---

## Workflow: List Tasks (`--list`)

### Without Filter

Display summary of all tasks:

```text
Project Backlog Summary
════════════════════════════════════════════════

High Priority (3 tasks)
  1. [Open]     Database Password Configuration Issue
  2. [Open]     Pre-Commit Hook Container Dependency
  3. [Planned]  v1.0 Release Preparation

Medium Priority (12 tasks)
  Quick Wins (4)
    • Container Security Scanning: Add Node Images
    • pnpm Update
    • ESLint Errors in PHPStorm
    • Claude Settings Consolidation
  Code Quality (2)
    • PHP Code Quality: SuppressWarnings Cleanup
    • Shell Compatibility (Brace Expansion)
  Testing (2)
    • Mutation Testing Integration
    • Security Static Analysis (SAST)
  ...

Low Priority (0 tasks)
  (none)

════════════════════════════════════════════════
Total: 15 tasks
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

○ Database Password Configuration Issue (High)
○ Pre-Commit Hook Container Dependency (High)
○ v1.0 Release Preparation (High)
○ Container Security Scanning: Add Node Images (Medium/Quick Wins)
○ pnpm Update (Medium/Quick Wins)
○ PHP Code Quality: SuppressWarnings Cleanup (Medium/Code Quality)
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

**Removed:** 2026-01-19
**Reason:** No longer relevant - PHPStorm was misconfigured, not an ESLint issue
**Original Priority:** Medium (Quick Wins)
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

## Task Status Values

| Status | Meaning |
|--------|---------|
| `Open` | Ready to be worked on |
| `Planned` | Scheduled for future work |
| `In Progress` | Currently being worked on |
| `Blocked` | Waiting on external dependency |
| `Deferred` | Postponed to later version |

**Note:** Completed tasks are removed by `/commit` and transferred to CHANGELOG.md.

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
- Review backlog periodically with `/optimize --all`
