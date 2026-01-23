---
description: Manage project tasks (add, list, choose, close)
context: fork
allowed-tools:
  Read, Write, Edit, Grep, Glob, Bash(date:*), Bash(ls:*), Bash(test:*),
  Bash(git remote:*), Bash(git config:*), Bash(gh issue:*), Bash(gh label:*),
  Bash(gh milestone:*), Bash(gh project:*), Bash(gh api:*), Bash(gh auth:*),
  Bash(glab issue:*), Bash(glab label:*), Bash(glab milestone:*), Bash(glab
  api:*), Bash(glab auth:*), AskUserQuestion
argument-hint:
  --add [--private] | --list [--private] [--milestone <name>] | --choose
  [--plan|--no-plan] | --milestone <name> <task> | --defer <task> | --close
  <task> | --add-label <labels> <task> | --remove-label <labels> <task> |
  --reprioritize
---

# Task Management

Manage project tasks with GitHub Issues integration and local fallback.

## Storage Mode Detection

**IMPORTANT:** Before processing any command, detect storage mode and platform.

### Step 0: Detect Platform and Storage Mode

```bash
# Check platform via remote URL
REMOTE_URL=$(git remote get-url origin 2>/dev/null)

# GitHub?
echo "$REMOTE_URL" | grep -qE 'github\.com' && PLATFORM="github"

# GitLab?
echo "$REMOTE_URL" | grep -qE 'gitlab\.com|gitlab\.' && PLATFORM="gitlab"

# Upstream zappzarapp?
echo "$REMOTE_URL" | grep -qE '(github|gitlab)\.com[:/]marcstraube/zappzarapp' && IS_UPSTREAM=true
```

**Decision tree:**

1. If `--private` flag → **Local Mode** (`~/.local/share/zappzarapp/`)
2. Else if upstream zappzarapp AND no `.claude/config.local.md`: → **Remote
   Mode** (GitHub Issues or GitLab Issues, depending on platform)
3. Else if `.claude/config.local.md` exists → Check config for
   `Storage: github|gitlab|local`
4. Else → **Local Mode** (`.ai/`)

**Platform CLI prerequisites:**

```bash
# GitHub
gh auth status

# GitLab
glab auth status
```

If not authenticated, inform user to run `gh auth login` or `glab auth login`.

### Platform Differences

| Feature       | GitHub               | GitLab              |
| ------------- | -------------------- | ------------------- |
| CLI           | `gh`                 | `glab`              |
| Scoped Labels | `priority:high`      | `priority::high`    |
| Boards        | Projects (GraphQL)   | Issue Boards (REST) |
| CI Sync       | `.github/workflows/` | `.gitlab-ci.yml`    |

---

## Arguments

Parse `$ARGUMENTS`:

- `--add [--private]`: Add a new task
- `--list [--private] [--milestone <name>]`: List tasks
- `--choose [--plan|--no-plan]`: Select and start a task
- `--milestone <name> <task>`: Assign task to milestone
- `--defer <task>`: Move task to "Backlog" milestone
- `--close <task>`: Close task (completed or not planned)
- `--add-label <labels> <task>`: Add labels to task
- `--remove-label <labels> <task>`: Remove labels from task
- `--reprioritize`: Analyze and suggest priority changes

---

## Type Labels

Standard types (platform-compatible):

| Type          | GitHub          | GitLab          | Description                              |
| ------------- | --------------- | --------------- | ---------------------------------------- |
| Bug fix       | `bug`           | `bug`           | Something isn't working                  |
| Feature       | `enhancement`   | `enhancement`   | New feature or request                   |
| Documentation | `documentation` | `documentation` | Docs improvements                        |
| Maintenance   | `chore`         | `chore`         | Maintenance, tooling, dependencies       |
| Refactoring   | `refactor`      | `refactor`      | Code improvement without behavior change |

## Status Labels

| Status      | GitHub               | GitLab                | When                           |
| ----------- | -------------------- | --------------------- | ------------------------------ |
| In Progress | `status:in-progress` | `status::in-progress` | Task actively being worked on  |
| Blocked     | `status:blocked`     | `status::blocked`     | Waiting on external dependency |
| Review      | `status:review`      | `status::review`      | Ready for review               |

**Note:** GitLab uses `::` for scoped labels (mutually exclusive within scope).

## Effort Labels (Optional)

| Effort      | GitHub      | GitLab       | Time estimate |
| ----------- | ----------- | ------------ | ------------- |
| Extra Small | `effort:xs` | `effort::xs` | < 15 min      |
| Small       | `effort:s`  | `effort::s`  | 15-30 min     |
| Medium      | `effort:m`  | `effort::m`  | 30 min - 2h   |
| Large       | `effort:l`  | `effort::l`  | 2-4h          |
| Extra Large | `effort:xl` | `effort::xl` | > 4h          |

---

## Milestone Concept

| Milestone           | Meaning                                         |
| ------------------- | ----------------------------------------------- |
| (none)              | Needs Triage — new, not yet evaluated           |
| `Backlog`           | Consciously deferred, not for upcoming releases |
| `v1.0`, `v1.1`, ... | Scheduled for release                           |

**Prioritization** (no explicit priority labels needed):

1. **Milestone** (v1.0 before v1.1 before Backlog)
2. **Type** (bug > enhancement > documentation > chore)
3. **Age** (older issues first at same type)

---

## Board Integration (GitHub Projects / GitLab Issue Boards)

When configured, tasks sync bidirectionally with a Kanban board.

**Column mapping:**

| Status      | Board Column         |
| ----------- | -------------------- |
| open (new)  | "To Do" or "Backlog" |
| in-progress | "In Progress"        |
| review      | "Review"             |
| done        | "Done"               |

### GitHub Projects

Requires explicit configuration and a GitHub Action for bidirectional sync.

**Configuration** (in `.claude/config.local.md`):

```markdown
## Tasks

- Storage: github
- Repository: user/repo
- Project: "Project Board Name"
```

If `Project` is empty or missing, no Project sync occurs.

**Sync mechanism:** `.github/workflows/project-sync.yml`

### GitLab Issue Boards

GitLab Boards are **label-based by default** — no extra configuration needed!

- Moving an issue on the board automatically changes its label
- Changing a label via CLI automatically moves the issue on the board

**Setup:**

1. Create an Issue Board in GitLab (Settings → Boards)
2. Add lists for each status label (`status::in-progress`, `status::review`,
   etc.)
3. Done — bidirectional sync is automatic

**No CI pipeline needed** — GitLab handles this natively.

---

## Local File Structure

```text
.ai/
├── TASKS.md              # Index
└── tasks/
    ├── fix-auth-bug.md
    └── done/
        └── 2026/
            └── 01/
                └── completed-task.md

~/.local/share/zappzarapp/
├── TASKS.md              # Private Index
└── tasks/
    └── ...
```

### Index Format (TASKS.md)

```markdown
# Tasks Index

## v1.0

| Slug     | Title        | Type | Status |
| -------- | ------------ | ---- | ------ |
| fix-auth | Fix Auth Bug | bug  | open   |

## v1.1

| Slug         | Title                 | Type          | Status |
| ------------ | --------------------- | ------------- | ------ |
| improve-docs | Improve Documentation | documentation | open   |

## Backlog

| Slug        | Title            | Type        | Status |
| ----------- | ---------------- | ----------- | ------ |
| future-idea | Some Future Idea | enhancement | open   |
```

### Task Detail Format

```markdown
# Task Title

**Type:** bug **Status:** open | in-progress | review | done **Milestone:** v1.0
**Labels:** security, backend **Created:** 2026-01-22

## Context

Why this task exists.

## Goal

What success looks like.

## Implementation

1. Step one
2. Step two

## Files

- `src/path/to/file.ext`
```

---

## Workflow: Add Task (`--add`)

**First:** Detect storage mode (see above).

### Remote Mode (GitHub / GitLab)

1. Gather information:
   - Title (required)
   - Type: bug, enhancement, documentation, chore, refactor
   - Milestone: v1.0, v1.1, Backlog, or none (Needs Triage)
   - Description/Context
   - Optional: Labels (effort, custom)

2. Create issue:

**GitHub:**

```bash
gh issue create \
  --title "Task Title" \
  --body "$(cat <<'EOF'
## Context
[Why this task exists]

## Goal
[What success looks like]

## Implementation
1. Step one
2. Step two

## Files
- `path/to/file.ext`
EOF
)" \
  --label "bug" \
  --milestone "v1.0"
```

**GitLab:**

```bash
glab issue create \
  --title "Task Title" \
  --description "$(cat <<'EOF'
## Context
[Why this task exists]

## Goal
[What success looks like]

## Implementation
1. Step one
2. Step two

## Files
- `path/to/file.ext`
EOF
)" \
  --label "bug" \
  --milestone "v1.0"
```

1. If Board configured, add to board:

**GitHub:**

```bash
gh project item-add <project-number> --owner <owner> --url <issue-url>
```

**GitLab:** Issues are automatically visible on Issue Boards based on labels.

1. Show confirmation with issue number and URL.

### Local Mode

1. Gather information (same as above)
2. Generate slug from title
3. Create task detail file: `tasks/<slug>.md`
4. Update index: Add row to appropriate milestone table in `TASKS.md`
5. Show confirmation

### Private Mode (`--private`)

Same as Local Mode, but in `~/.local/share/zappzarapp/`.

---

## Workflow: List Tasks (`--list`)

**First:** Detect storage mode.

### Remote Mode (GitHub / GitLab)

**GitHub:**

```bash
# All open issues grouped by milestone
gh issue list --state open --json number,title,labels,milestone

# Filter by milestone
gh issue list --milestone "v1.0" --json number,title,labels
```

**GitLab:**

```bash
# All open issues
glab issue list --all

# Filter by milestone
glab issue list --milestone "v1.0"
```

**Output format:**

```text
Tasks (GitHub: marcstraube/zappzarapp)
# or: Tasks (GitLab: marcstraube/zappzarapp)
════════════════════════════════════════════════

v1.0 (3 issues)
  #42 [bug]           Fix Auth Bug
  #38 [enhancement]   Add Retry Logic
  #35 [documentation] Update README

v1.1 (2 issues)
  #44 [enhancement]   New Feature
  #43 [chore]         Update Dependencies

Backlog (1 issue)
  #40 [enhancement]   Future Idea

Needs Triage (1 issue)
  #47 [bug]           Reported Bug (no milestone)

════════════════════════════════════════════════
Total: 7 open issues

View on GitHub: https://github.com/marcstraube/zappzarapp/issues
```

### Local Mode

Read `TASKS.md` index and display grouped by milestone.

---

## Workflow: Choose Task (`--choose`)

**First:** Detect storage mode.

### Step 1: List and Select

Present tasks sorted by priority (Milestone → Type → Age):

```text
Which task would you like to work on?

v1.0:
  ○ #42 [bug] Fix Auth Bug
  ○ #38 [enhancement] Add Retry Logic

v1.1:
  ○ #44 [enhancement] New Feature

Backlog:
  ○ #40 [enhancement] Future Idea
```

### Step 2: Plan Decision

Unless `--plan` or `--no-plan` specified, ask:

```text
Create a plan before starting?

○ Yes — Enter Plan Mode first (Recommended for complex tasks)
○ No — Start implementation directly
```

### Step 3: Mark In Progress

**GitHub:**

```bash
# Add label
gh issue edit <number> --add-label "status:in-progress"

# Assign to self
gh issue edit <number> --add-assignee "@me"

# If Project configured, move to "In Progress" column
```

**GitLab:**

```bash
# Add label (note: :: for scoped labels)
glab issue update <number> --label "status::in-progress"

# Assign to self
glab issue update <number> --assignee "@me"
```

**Local Mode:**

Update status in task file and index.

### Step 4: Show Task Details

Display full task context, then begin work or enter Plan Mode.

---

## Workflow: Milestone (`--milestone <name> <task>`)

Assign or change task milestone.

**GitHub:**

```bash
gh issue edit <number> --milestone "v1.0"
```

**GitLab:**

```bash
glab issue update <number> --milestone "v1.0"
```

**Local Mode:**

1. Update milestone in task detail file
2. Move row to correct table in `TASKS.md` index

---

## Workflow: Defer (`--defer <task>`)

Move task to "Backlog" milestone.

Equivalent to `--milestone Backlog <task>`.

**Auto-create Backlog milestone** if it doesn't exist:

**GitHub:**

```bash
# Check if Backlog milestone exists
gh api repos/{owner}/{repo}/milestones --jq '.[] | select(.title=="Backlog")'

# If not, create it
gh api repos/{owner}/{repo}/milestones --method POST \
  -f title="Backlog" \
  -f description="Consciously deferred tasks, not scheduled for upcoming releases"
```

**GitLab:**

```bash
# Check if Backlog milestone exists
glab api projects/:id/milestones --jq '.[] | select(.title=="Backlog")'

# If not, create it
glab api projects/:id/milestones --method POST \
  -f title="Backlog" \
  -f description="Consciously deferred tasks, not scheduled for upcoming releases"
```

---

## Workflow: Close (`--close <task>`)

Close a task with reason.

### Step 1: Ask Reason

```text
Why is this task being closed?

○ Completed — Task is done
○ Not planned — Won't be implemented
○ Duplicate — Already covered by another task
```

### Step 2: Close

**GitHub:**

```bash
# Completed
gh issue close <number> --reason "completed"

# Not planned
gh issue close <number> --reason "not planned" --comment "Reason: [user reason]"

# Duplicate
gh issue close <number> --reason "not planned" --comment "Duplicate of #XX"
```

**GitLab:**

```bash
# Close issue
glab issue close <number>

# Add closing note
glab issue note <number> --message "Closed: [reason]"

# For duplicates
glab issue note <number> --message "Duplicate of #XX"
glab issue close <number>
```

**Local Mode:**

1. Update status to `done` in task file
2. Move file to `tasks/done/YYYY/MM/`
3. Remove from active index, add to completed section

---

## Workflow: Add Label (`--add-label <labels> <task>`)

Add one or more labels (comma-separated).

### Remote Mode (GitHub / GitLab)

1. Fetch existing labels:
   - GitHub: `gh label list --json name`
   - GitLab: `glab label list`

2. For each label to add:
   - If exists → add it
   - If not exists → **Fuzzy match** for typos
   - For GitLab: Convert `:` to `::` for scoped labels

**Typo detection:**

```text
Label "effor:small" does not exist.

Did you mean:
  → effort:small
  → effort:s

○ Use effort:small
○ Use effort:s
○ Create new label "effor:small"
○ Continue without label
```

**If user wants to create new label:**

Check permission, then:

**GitHub:**

```bash
gh label create "label-name" --color "c5def5" --description "Description"
```

**GitLab:**

```bash
glab label create "label-name" --color "#c5def5" --description "Description"
```

Note: GitLab colors require `#` prefix.

**If no permission:**

```text
Label "custom-label" does not exist.
You don't have permission to create labels.

Available labels:
  bug                  Something isn't working
  enhancement          New feature or request
  effort:xs            Extra small task
  effort:s             Small task
  ...

→ Choose different label or continue without?
```

### Local Mode

Add to Labels field in task detail file.

---

## Workflow: Remove Label (`--remove-label <labels> <task>`)

Remove one or more labels (comma-separated).

**GitHub:**

```bash
gh issue edit <number> --remove-label "label-name"
```

**GitLab:**

```bash
glab issue update <number> --unlabel "label-name"
```

**Local Mode:**

Remove from Labels field in task detail file.

---

## Workflow: Reprioritize (`--reprioritize`)

Analyze all tasks and suggest changes.

### Analysis Criteria

| Criterion             | Suggestion                       |
| --------------------- | -------------------------------- |
| No milestone          | → "Needs Triage" warning         |
| Bug without milestone | → Suggest adding to next release |
| Old issue (>30 days)  | → Review relevance               |
| Overloaded milestone  | → Suggest moving some to next    |
| Stale (no activity)   | → Suggest closing or deferring   |

### Output Format

```text
Task Analysis
════════════════════════════════════════════════

⚠️  Needs Triage (2 issues without milestone)
  #47 [bug] Reported Bug — created 5 days ago
  #48 [enhancement] Feature Request — created 2 days ago

🐛 Bugs without milestone (1)
  #47 → Suggest: v1.0 (bugs should be prioritized)

📋 Milestone v1.0 has 8 issues
  Consider moving 2-3 to v1.1?

📅 Stale issues (>30 days, no activity)
  #32 [enhancement] Old Feature — last activity 45 days ago

════════════════════════════════════════════════

Apply suggestions?
○ Assign #47, #48 to milestones (will ask which)
○ Move issues from v1.0 to v1.1
○ Review stale issues
○ Apply all
○ Skip
```

---

## Label Initialization

For new repositories, run the init script:

```bash
# GitHub
./.zappzarapp/scripts/init-github-labels.sh

# GitLab
./.zappzarapp/scripts/init-gitlab-labels.sh

# Or via Make (auto-detects platform)
make ai-setup
```

**Manual creation (GitHub):**

```bash
gh label create "chore" --color "fef2c0" --description "Maintenance, tooling, dependencies"
gh label create "status:in-progress" --color "fbca04" --description "Currently being worked on"
```

**Manual creation (GitLab):**

```bash
glab label create "chore" --color "#fef2c0" --description "Maintenance, tooling, dependencies"
glab label create "status::in-progress" --color "#fbca04" --description "Currently being worked on"
```

Note: GitLab uses `::` for scoped labels and requires `#` prefix for colors.

---

## Examples

### Adding a Task (GitHub)

```text
$ /tasks --add

Title: Fix authentication bypass
Type: bug
Milestone: v1.0
Context: Security audit found issue in session handling

Creating GitHub Issue...

Issue Created
════════════════════════════════════════════════
Number:   #49
Title:    Fix authentication bypass
URL:      https://github.com/marcstraube/zappzarapp/issues/49
Labels:   bug
Milestone: v1.0
════════════════════════════════════════════════
```

### Listing Tasks

```text
$ /tasks --list

Tasks (GitHub: marcstraube/zappzarapp)
════════════════════════════════════════════════

v1.0 (2 issues)
  #49 [bug]         Fix authentication bypass
  #42 [enhancement] Add retry logic

Backlog (1 issue)
  #40 [enhancement] Future idea

════════════════════════════════════════════════
```

### Choosing a Task

```text
$ /tasks --choose

Which task would you like to work on?
● #49 [bug] Fix authentication bypass (v1.0)

Create a plan before starting?
● No — Start implementation directly

Task Selected
════════════════════════════════════════════════
Number:   #49
Title:    Fix authentication bypass
Status:   → in-progress
Assignee: → @you
════════════════════════════════════════════════

Ready to start implementation.
```

### Deferring a Task

```text
$ /tasks --defer #40

Task Deferred
════════════════════════════════════════════════
Number:   #40
Title:    Future idea
Milestone: enhancement → Backlog
════════════════════════════════════════════════
```

### Adding Labels with Typo Correction

```text
$ /tasks --add-label "effor:m" #49

Label "effor:m" does not exist.

Did you mean:
  → effort:m
  → effort:l

● Use effort:m

Label Added
════════════════════════════════════════════════
Issue:  #49
Added:  effort:m
════════════════════════════════════════════════
```

### Adding Private Task

```text
$ /tasks --add --private

Title: Research MCP server options
Type: enhancement
Context: Personal learning goal

Task Added (Private)
════════════════════════════════════════════════
Location: ~/.local/share/zappzarapp/tasks/
Slug:     research-mcp-server-options
════════════════════════════════════════════════
```

---

## Integration with Other Commands

- `/commit` can reference closed tasks in commit message
- `/status --todo` shows high-priority tasks from current milestone

---

## Notes

- Task slugs/numbers should be unique and descriptive
- Context is crucial — future you needs to understand why
- Use milestones for release planning, not priority
- Prioritization emerges from: Milestone → Type → Age
- Review tasks periodically with `/tasks --reprioritize`
