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
  --add [--zappzarapp|--upstream|--private] | --list [--all|--zappzarapp|
  --upstream|--private] [--milestone <name>] | --choose [--plan|--no-plan] |
  --milestone <name> <task> | --defer <task> | --close <task> | --add-label
  <labels> <task> | --remove-label <labels> <task> | --reprioritize
---

# Task Management

Manage project tasks with a 4-tier storage model following Git conventions.

## 4-Tier Storage Model

Tasks can be stored at different levels, following the fork chain:

```text
marcstraube/zappzarapp          ← --zappzarapp (Boilerplate, hardcoded)
    ↓ clone
user-a/my-project               ← --upstream (upstream remote)
    ↓ fork
user-b/my-project               ← --repo / default (origin remote)
    ↓
$PERSONAL_PATH                  ← --private (local, not shared)
```

| Flag           | Git Remote  | Target                       | Use Case                        |
| -------------- | ----------- | ---------------------------- | ------------------------------- |
| `--zappzarapp` | (hardcoded) | `marcstraube/zappzarapp`     | Feature requests to boilerplate |
| `--upstream`   | `upstream`  | Configured upstream repo     | Contribute to original project  |
| `--repo`       | `origin`    | Your repo (default)          | Your project tasks              |
| `--private`    | —           | Configured path (see Step 0.5) | Personal, offline tasks         |

**Default:** `--repo` (origin remote) — where you push, you track tasks.

---

## Storage Detection

**IMPORTANT:** Before processing any command, detect target and platform.

### Step 0: Detect Target Repository

```bash
# Determine target based on flags
if [[ "$ARGUMENTS" == *"--zappzarapp"* ]]; then
  TARGET_REPO="marcstraube/zappzarapp"
  TARGET_NAME="zappzarapp"
  # TODO: Once zappzarapp is published with a GitHub Project Board,
  # set PROJECT_NUMBER here for --zappzarapp to enable board sync
elif [[ "$ARGUMENTS" == *"--upstream"* ]]; then
  TARGET_REPO=$(git remote get-url upstream 2>/dev/null | sed -E 's|.*[:/]([^/]+/[^/]+)\.git$|\1|; s|.*[:/]([^/]+/[^/]+)$|\1|')
  TARGET_NAME="upstream"
elif [[ "$ARGUMENTS" == *"--private"* ]]; then
  TARGET_REPO=""
  TARGET_NAME="private"
else
  # Default: origin (--repo)
  TARGET_REPO=$(git remote get-url origin 2>/dev/null | sed -E 's|.*[:/]([^/]+/[^/]+)\.git$|\1|; s|.*[:/]([^/]+/[^/]+)$|\1|')
  TARGET_NAME="repo"
fi
```

### Step 0.5: Load Personal Configuration

```bash
# Default personal storage path
PERSONAL_PATH="$HOME/.local/share/zappzarapp"

# Override from config.local.md if exists
if [[ -f ".claude/config.local.md" ]]; then
  CONFIGURED_PATH=$(grep -oP '^personal_knowledge_path\s*[=:]\s*\K.+' .claude/config.local.md 2>/dev/null | head -1 | xargs)
  if [[ -n "$CONFIGURED_PATH" ]]; then
    # Expand ~ to $HOME
    PERSONAL_PATH="${CONFIGURED_PATH/#\~/$HOME}"
  fi
fi
```

### Step 1: Detect Platform

```bash
if [[ -n "$TARGET_REPO" ]]; then
  # Check platform from repo URL or config
  REMOTE_URL=$(git remote get-url origin 2>/dev/null)

  echo "$REMOTE_URL" | grep -qE 'github\.com' && PLATFORM="github"
  echo "$REMOTE_URL" | grep -qE 'gitlab\.com|gitlab\.' && PLATFORM="gitlab"
fi
```

### Step 2: Verify Access

```bash
# For remote targets, verify CLI authentication
if [[ "$PLATFORM" == "github" ]]; then
  gh auth status || echo "Run: gh auth login"
elif [[ "$PLATFORM" == "gitlab" ]]; then
  glab auth status || echo "Run: glab auth login"
fi

# For --upstream, verify remote exists
if [[ "$TARGET_NAME" == "upstream" && -z "$TARGET_REPO" ]]; then
  echo "No upstream remote configured."
  echo "Add with: git remote add upstream <url>"
  exit 1
fi
```

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

**Target flags (mutually exclusive):**

- `--zappzarapp`: Target marcstraube/zappzarapp
- `--upstream`: Target upstream remote
- `--repo`: Target origin remote (default, implicit)
- `--private`: Target local storage

**Action flags:**

- `--add`: Add a new task
- `--list [--all]`: List tasks (--all shows all tiers)
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

GitHub Projects V2 exist at User/Org level and can be linked to repositories.

**Configuration** (in `.claude/config.local.md`):

```markdown
## Tasks

- Projects:
  - repo: "My Project Board"
  - upstream: ""
```

If Project is empty or missing, no Project sync occurs.

### GitLab Issue Boards

GitLab Boards are **label-based by default** — no extra configuration needed!

- Moving an issue on the board automatically changes its label
- Changing a label via CLI automatically moves the issue on the board

**Setup:**

1. Create an Issue Board in GitLab (Settings → Boards)
2. Add lists for each status label (`status::in-progress`, `status::review`,
   etc.)
3. Done — bidirectional sync is automatic

---

## Local File Structure (--private)

```text
$PERSONAL_PATH/
├── TASKS.md              # Private Index
└── tasks/
    ├── learn-mcp.md
    └── done/
        └── 2026/
            └── 01/
                └── completed-task.md
```

### Index Format (TASKS.md)

```markdown
# Tasks Index

## Active

| Slug      | Title             | Type        | Status |
| --------- | ----------------- | ----------- | ------ |
| learn-mcp | Learn MCP Servers | enhancement | open   |

## Backlog

| Slug        | Title            | Type        | Status |
| ----------- | ---------------- | ----------- | ------ |
| future-idea | Some Future Idea | enhancement | open   |
```

### Task Detail Format

```markdown
# Task Title

**Type:** bug **Status:** open | in-progress | review | done **Labels:**
security, backend **Created:** 2026-01-22

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

**First:** Detect target (see above).

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
  --repo "$TARGET_REPO" \
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
  --repo "$TARGET_REPO" \
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

### Private Mode (`--private`)

1. Gather information (same as above)
2. Generate slug from title
3. Create task detail file: `$PERSONAL_PATH/tasks/<slug>.md`
4. Update index: Add row to `TASKS.md`
5. Show confirmation

---

## Workflow: List Tasks (`--list`)

**First:** Detect target.

### Single Target (default)

**GitHub:**

```bash
gh issue list --repo "$TARGET_REPO" --state open --json number,title,labels,milestone
```

**GitLab:**

```bash
glab issue list --repo "$TARGET_REPO" --all
```

**Output format:**

```text
Tasks (repo: marcstraube/zappzarapp)
════════════════════════════════════════════════

v1.0 (3 issues)
  #42 [bug]           Fix Auth Bug
  #38 [enhancement]   Add Retry Logic
  #35 [documentation] Update README

Backlog (1 issue)
  #40 [enhancement]   Future Idea

════════════════════════════════════════════════
Total: 4 open issues
```

### All Tiers (`--list --all`)

Aggregate from all available tiers:

```text
Tasks (all tiers)
════════════════════════════════════════════════

zappzarapp (marcstraube/zappzarapp): 12 issues
  #101 [enhancement] Add feature X

upstream (user-a/project): 5 issues
  #42 [bug] Fix critical bug

repo (user-b/project): 3 issues
  #7 [enhancement] My feature

private ($PERSONAL_PATH): 2 tasks
  learn-mcp [enhancement] Learn MCP Servers

════════════════════════════════════════════════
Total: 22 tasks across 4 tiers
```

### Private Mode

Read `$PERSONAL_PATH/TASKS.md` index and display.

---

## Workflow: Choose Task (`--choose`)

**First:** Detect target.

### Step 1: List and Select

Present tasks sorted by priority (Milestone → Type → Age):

```text
Which task would you like to work on?

v1.0:
  ○ #42 [bug] Fix Auth Bug
  ○ #38 [enhancement] Add Retry Logic

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
gh issue edit <number> --repo "$TARGET_REPO" --add-label "status:in-progress"
gh issue edit <number> --repo "$TARGET_REPO" --add-assignee "@me"
```

**GitLab:**

```bash
glab issue update <number> --repo "$TARGET_REPO" --label "status::in-progress"
glab issue update <number> --repo "$TARGET_REPO" --assignee "@me"
```

**Private Mode:**

Update status in task file and index.

### Step 4: Show Task Details

Display full task context, then begin work or enter Plan Mode.

---

## Workflow: Milestone (`--milestone <name> <task>`)

Assign or change task milestone.

**GitHub:**

```bash
gh issue edit <number> --repo "$TARGET_REPO" --milestone "v1.0"
```

**GitLab:**

```bash
glab issue update <number> --repo "$TARGET_REPO" --milestone "v1.0"
```

**Private Mode:**

1. Update milestone in task detail file
2. Move row to correct table in `TASKS.md` index

---

## Workflow: Defer (`--defer <task>`)

Move task to "Backlog" milestone.

Equivalent to `--milestone Backlog <task>`.

**Auto-create Backlog milestone** if it doesn't exist:

**GitHub:**

```bash
gh api repos/$TARGET_REPO/milestones --jq '.[] | select(.title=="Backlog")' | grep -q . || \
gh api repos/$TARGET_REPO/milestones --method POST \
  -f title="Backlog" \
  -f description="Consciously deferred tasks, not scheduled for upcoming releases"
```

**GitLab:**

```bash
glab api projects/:id/milestones --jq '.[] | select(.title=="Backlog")' | grep -q . || \
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
gh issue close <number> --repo "$TARGET_REPO" --reason "completed"

# Not planned
gh issue close <number> --repo "$TARGET_REPO" --reason "not planned" --comment "Reason: [user reason]"

# Duplicate
gh issue close <number> --repo "$TARGET_REPO" --reason "not planned" --comment "Duplicate of #XX"
```

**GitLab:**

```bash
glab issue close <number> --repo "$TARGET_REPO"
glab issue note <number> --repo "$TARGET_REPO" --message "Closed: [reason]"
```

**Private Mode:**

1. Update status to `done` in task file
2. Move file to `tasks/done/YYYY/MM/`
3. Remove from active index, add to completed section

---

## Workflow: Add Label (`--add-label <labels> <task>`)

Add one or more labels (comma-separated).

### Remote Mode (GitHub / GitLab)

1. Fetch existing labels:
   - GitHub: `gh label list --repo "$TARGET_REPO" --json name`
   - GitLab: `glab label list --repo "$TARGET_REPO"`

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

### Private Mode

Add to Labels field in task detail file.

---

## Workflow: Remove Label (`--remove-label <labels> <task>`)

Remove one or more labels (comma-separated).

**GitHub:**

```bash
gh issue edit <number> --repo "$TARGET_REPO" --remove-label "label-name"
```

**GitLab:**

```bash
glab issue update <number> --repo "$TARGET_REPO" --unlabel "label-name"
```

**Private Mode:**

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
Task Analysis (repo: marcstraube/zappzarapp)
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

---

## Examples

### Adding a Task (default: origin)

```text
$ /tasks --add

Title: Fix authentication bypass
Type: bug
Milestone: v1.0
Context: Security audit found issue in session handling

Creating GitHub Issue (repo: user/my-project)...

Issue Created
════════════════════════════════════════════════
Number:   #49
Title:    Fix authentication bypass
URL:      https://github.com/user/my-project/issues/49
Labels:   bug
Milestone: v1.0
════════════════════════════════════════════════
```

### Adding a Task to Upstream

```text
$ /tasks --add --upstream

Title: Fix shared utility bug
Type: bug
Context: Found bug in shared component

Creating GitHub Issue (upstream: original-author/project)...

Issue Created
════════════════════════════════════════════════
Number:   #142
Title:    Fix shared utility bug
URL:      https://github.com/original-author/project/issues/142
════════════════════════════════════════════════
```

### Feature Request to Boilerplate

```text
$ /tasks --add --zappzarapp

Title: Add support for PostgreSQL
Type: enhancement
Context: MySQL is default, PostgreSQL would be useful

Creating GitHub Issue (zappzarapp: marcstraube/zappzarapp)...

Issue Created
════════════════════════════════════════════════
Number:   #89
Title:    Add support for PostgreSQL
URL:      https://github.com/marcstraube/zappzarapp/issues/89
════════════════════════════════════════════════
```

### Private Task

```text
$ /tasks --add --private

Title: Learn MCP server development
Type: enhancement
Context: Personal learning goal

Task Added (private)
════════════════════════════════════════════════
Location: $PERSONAL_PATH/tasks/
Slug:     learn-mcp-server-development
════════════════════════════════════════════════
```

### Listing All Tiers

```text
$ /tasks --list --all

Tasks (all tiers)
════════════════════════════════════════════════

zappzarapp (marcstraube/zappzarapp): 2 issues
  #89 [enhancement] Add PostgreSQL support

upstream (original-author/project): 1 issue
  #142 [bug] Fix shared utility bug

repo (user/my-project): 3 issues
  #49 [bug] Fix authentication bypass
  #48 [enhancement] Add dark mode
  #45 [chore] Update dependencies

private ($PERSONAL_PATH): 1 task
  learn-mcp [enhancement] Learn MCP server development

════════════════════════════════════════════════
Total: 7 tasks across 4 tiers
```

---

## Integration with Other Commands

- `/commit` can reference closed tasks in commit message
- `/status --todo` shows high-priority tasks from current milestone (origin)

---

## Notes

- **Default is origin** — where you push code, you track tasks
- Use `--upstream` for bugs/features in the project you forked from
- Use `--zappzarapp` for boilerplate-specific requests
- Use `--private` for personal notes, learning goals, offline work
- Task slugs/numbers should be unique and descriptive
- Context is crucial — future you needs to understand why
- Use milestones for release planning, not priority
- Prioritization emerges from: Milestone → Type → Age
- Review tasks periodically with `/tasks --reprioritize`
