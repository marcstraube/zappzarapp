---
name: tasks
description: Manage project tasks (add, list, choose, close)
model: sonnet
context: fork
allowed-tools:
  - Read
  - Write
  - Edit
  - Grep
  - Glob
  - Bash(date:*)
  - Bash(ls:*)
  - Bash(test:*)
  - Bash(git remote:*)
  - Bash(git config:*)
  - Bash(gh issue:*)
  - Bash(gh label:*)
  - Bash(gh milestone:*)
  - Bash(gh project:*)
  - Bash(gh api:*)
  - Bash(gh auth:*)
  - Bash(glab issue:*)
  - Bash(glab label:*)
  - Bash(glab milestone:*)
  - Bash(glab api:*)
  - Bash(glab auth:*)
  - AskUserQuestion
argument-hint:
  '--add [--zappzarapp|--upstream|--private] | --list
  [--all|--zappzarapp|--upstream|--private] [--milestone <name>] | --choose
  [--plan|--no-plan] | --milestone <name> <task> | --defer <task> | --close
  <task> | --add-label <labels> <task> | --remove-label <labels> <task> |
  --reprioritize'
---

# Task Management

Manage project tasks with a 4-tier storage model following Git conventions.

## 4-Tier Storage Model

Tasks can be stored at different levels, following the fork chain:

```text
marcstraube/zappzarapp          <- --zappzarapp (Boilerplate, hardcoded)
    v clone
user-a/my-project               <- --upstream (upstream remote)
    v fork
user-b/my-project               <- --repo / default (origin remote)
    v
$PERSONAL_PATH                  <- --private (local, not shared)
```

| Flag           | Git Remote  | Target                         | Use Case                        |
| -------------- | ----------- | ------------------------------ | ------------------------------- |
| `--zappzarapp` | (hardcoded) | `marcstraube/zappzarapp`       | Feature requests to boilerplate |
| `--upstream`   | `upstream`  | Configured upstream repo       | Contribute to original project  |
| `--repo`       | `origin`    | Your repo (default)            | Your project tasks              |
| `--private`    | -           | Configured path (see Step 0.5) | Personal, offline tasks         |

**Default:** `--repo` (origin remote) - where you push, you track tasks.

---

## Storage Detection

**IMPORTANT:** Before processing any command, detect target and platform.

### Step 0: Detect Target Repository

```bash
# Determine target based on flags
if [[ "$ARGUMENTS" == *"--zappzarapp"* ]]; then
  TARGET_REPO="marcstraube/zappzarapp"
  TARGET_NAME="zappzarapp"
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
| (none)              | Needs Triage - new, not yet evaluated           |
| `Backlog`           | Consciously deferred, not for upcoming releases |
| `v1.0`, `v1.1`, ... | Scheduled for release                           |

**Prioritization** (no explicit priority labels needed):

1. **Milestone** (v1.0 before v1.1 before Backlog)
2. **Type** (bug > enhancement > documentation > chore)
3. **Age** (older issues first at same type)

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
============================================

v1.0 (3 issues)
  #42 [bug]           Fix Auth Bug
  #38 [enhancement]   Add Retry Logic
  #35 [documentation] Update README

Backlog (1 issue)
  #40 [enhancement]   Future Idea

============================================
Total: 4 open issues
```

### All Tiers (`--list --all`)

Aggregate from all available tiers.

---

## Workflow: Close (`--close <task>`)

Close a task with reason.

### Step 1: Ask Reason

```text
Why is this task being closed?

o Completed - Task is done
o Not planned - Won't be implemented
o Duplicate - Already covered by another task
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

## Notes

- **Default is origin** - where you push code, you track tasks
- Use `--upstream` for bugs/features in the project you forked from
- Use `--zappzarapp` for boilerplate-specific requests
- Use `--private` for personal notes, learning goals, offline work
- Task slugs/numbers should be unique and descriptive
- Context is crucial - future you needs to understand why
- Use milestones for release planning, not priority
- Prioritization emerges from: Milestone -> Type -> Age
- Review tasks periodically with `/tasks --reprioritize`
