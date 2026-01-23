---
description: Guided commit workflow with quality checks and conventional commit format
context: fork
allowed-tools: Read, Write, Edit, Grep, Glob, Bash(make:*), Bash(git:*), Bash(date:*), AskUserQuestion
argument-hint: [--skip-checks] [--amend]
---

# Commit

Guided commit workflow ensuring quality checks pass and commits follow project
conventions.

## Arguments

Parse `$ARGUMENTS`:

- `--skip-checks`: Skip `make check` (use only if checks were run manually)
- `--amend`: Amend the previous commit instead of creating a new one

Examples:

```bash
/commit                    # Full workflow with quality checks
/commit --skip-checks      # Skip checks (already ran make check)
/commit --amend            # Amend previous commit
```

## Feature-Branch Workflow

This command supports the feature-branch workflow defined in
`.claude/agents/workflow.md`:

```text
Feature-Branch (development)     Main Branch (stable)
─────────────────────────────    ────────────────────
Commit 1: PHP part               │
Commit 2: Node part              │
Commit 3: Tests                  │
         ↓                       │
User reviews branch              │
         ↓                       │
Merge ──────────────────────────→│ Clean history
         ↓                       │
Task closed                      │
```

**Key rules:**

- CHANGELOG entries: Add per commit (on feature branch)
- Task closure: Only at merge to main (after user approval, via
  `/tasks --close`)

## Workflow

### Step 1: Check Working State

```bash
# Show current status
git status

# Show staged changes
git diff --cached --stat

# Show unstaged changes
git diff --stat
```

If nothing to commit, inform user and exit.

### Step 2: Identify Changes

Analyze the changes to understand what's being committed:

```bash
# List changed files
git diff --cached --name-only

# For each file, understand the change type
git diff --cached
```

Categorize changes:

- New files added
- Files modified
- Files deleted
- Files renamed

**Config file check:** If changes include any of these patterns, remind user:

- `compose*.yaml`, `docker/**`, `.env*`
- `.vscode/**`, `.idea/**`
- `Makefile`

```text
⚠️  Config files changed. Run `/sync-check` to verify synchronization.
```

### Step 3: Quality Checks (unless --skip-checks)

Run the project's quality checks:

```bash
make check
```

This runs:

- `make analyse` (PHPStan)
- `make cs-check` (PHP-CS-Fixer)
- `make phpmd` (PHP Mess Detector)
- `make test` (Tests)
- And other configured checks

If any check fails:

1. Report which check failed
2. Ask user: "Fix issues and retry?" or "Abort commit?"
3. Do NOT proceed with commit until checks pass or user explicitly skips

### Step 4: Determine Commit Type

Based on the changes, suggest appropriate Conventional Commit type:

| Type       | When to Use                                         |
| ---------- | --------------------------------------------------- |
| `feat`     | New feature for the user                            |
| `fix`      | Bug fix                                             |
| `docs`     | Documentation only changes                          |
| `style`    | Formatting, missing semicolons (no code change)     |
| `refactor` | Code change that neither fixes bug nor adds feature |
| `perf`     | Performance improvement                             |
| `test`     | Adding or correcting tests                          |
| `build`    | Build system or external dependencies               |
| `ci`       | CI configuration changes                            |
| `chore`    | Other changes (tooling, configs)                    |

### Step 5: Determine Scope (optional)

Suggest scope based on changed files:

| Directory               | Suggested Scope |
| ----------------------- | --------------- |
| `src/php/App/`          | `php`           |
| `src/php/DevDashboard/` | `dev-dashboard` |
| `src/node/backend/`     | `node-backend`  |
| `src/node/frontend/`    | `node-frontend` |
| `docker/`               | `docker`        |
| `.zappzarapp/docs/`     | `docs`          |
| `.github/`, `.gitlab/`  | `ci`            |
| `Makefile`              | `make`          |
| Multiple areas          | Omit scope      |

### Step 6: Draft Commit Message

Create a commit message following Conventional Commits:

```text
<type>(<scope>): <description>

[optional body]

[optional footer]
```

Rules:

- Description: Imperative mood ("add" not "added"), lowercase, no period
- Body: Explain what and why (not how)
- Max 72 characters per line

### Step 7: User Confirmation

Present the draft commit message and ask:

1. "Use this message?" → Proceed
2. "Edit message?" → Let user provide custom message
3. "Abort?" → Cancel commit

### Step 8: Changelog Update

**Always** add an entry to the project CHANGELOG before committing:

- Project: `documentation/CHANGELOG.md` (if exists after `make setup`)
- Boilerplate: `.zappzarapp/CHANGELOG.md` (fallback)

1. Read current changelog to find the active version section
2. Determine category from commit type:

| Commit Type | Changelog Category    |
| ----------- | --------------------- |
| `feat`      | Features / Added      |
| `fix`       | Fixed / Bugfixes      |
| `docs`      | Documentation         |
| `style`     | Style                 |
| `refactor`  | Changed / Refactoring |
| `perf`      | Performance           |
| `test`      | Testing               |
| `build`     | Build System          |
| `ci`        | CI/CD                 |
| `chore`     | Maintenance           |

1. Add entry under appropriate category:

Without task reference:

```markdown
- <Description from commit message>
```

With task reference:

```markdown
- <Description from commit message> (Task: <Task Name>)
```

### Step 9: Task Closure (only on merge to main)

**Important:** Task closure happens only when merging a feature branch to main,
NOT on intermediate commits on the feature branch.

**On Feature-Branch commits:**

- CHANGELOG: ✅ Add entry per commit
- Task: ❌ Do NOT close (task not complete yet)

**On Merge to main (after user approval):**

- CHANGELOG: Already contains entries from feature branch
- Task: ✅ Close via `/tasks --close <id>`

**Detection (at merge time):**

1. Conversation context: Did user approve the feature branch?
2. Match branch name against task slug
3. Match commit messages against task titles

**If task detected:**

- If certain: Close task via `/tasks --close <id>`
- If uncertain: Ask "Schließt dieser Merge den Task **'{task name}'** ab?"

**Important:** Closed tasks are removed/closed, not marked as "Completed".
Changelog = single source of truth.

**If no task:** Skip this step.

### Step 10: Stage All Changes

Stage code changes AND documentation updates:

```bash
# Stage original code changes (if not already staged)
git add <code-files>

# Stage changelog (project or boilerplate)
git add documentation/CHANGELOG.md 2>/dev/null || git add .zappzarapp/CHANGELOG.md

# Stage tasks file (only if using local storage and modified)
git add .ai/TASKS.md 2>/dev/null || true
```

### Step 11: Execute Commit

Now create ONE commit containing everything:

```bash
# Commit with the message
git commit -m "<message>"

# Or amend if --amend flag
git commit --amend -m "<message>"
```

### Step 12: Post-Commit Summary

Show the result:

```bash
git log -1 --oneline
git status
```

Display summary:

```text
╔════════════════════════════════════════════════════════════╗
║ Commit Complete                                            ║
╠════════════════════════════════════════════════════════════╣
║ Commit:    abc1234 fix(docker): resolve hook issue         ║
║ Changelog: Entry added under "Fixed"                       ║
║ Task:      "Pre-Commit Hook Container Dependency" closed   ║
╚════════════════════════════════════════════════════════════╝
```

Without task:

```text
╔════════════════════════════════════════════════════════════╗
║ Commit Complete                                            ║
╠════════════════════════════════════════════════════════════╣
║ Commit:    c434359 feat(claude): add /optimize command     ║
║ Changelog: Entry added under "Features"                    ║
╚════════════════════════════════════════════════════════════╝
```

## Files to Exclude

Never commit these files:

- Lock files: `composer.lock`, `pnpm-lock.yaml`
- Claude state: `.claude/*` (except `.claude/commands/`,
  `.claude/settings.json`)
- Environment: `.env` (only `.env.example` should be committed)
- IDE personal settings: `.idea/workspace.xml`, `.vscode/settings.json`

If any of these are staged, warn user and suggest unstaging:

```bash
git reset HEAD <file>
```

## Breaking Changes

If the commit includes breaking changes:

1. Add exclamation mark after type/scope, e.g.: feat(api)!: change response
   format
2. Add BREAKING CHANGE: footer explaining the impact

## Examples

### Simple Feature

```text
feat(php): add user profile endpoint
```

### Bug Fix with Body

```text
fix(docker): resolve nginx proxy timeout issue

Increased proxy_read_timeout from 60s to 120s to handle
long-running API requests during report generation.
```

### Breaking Change

```text
feat(api)!: change authentication to JWT

BREAKING CHANGE: Session-based auth is removed.
All API clients must now use JWT tokens.
```

## Error Handling

### Merge Conflicts

If there are unresolved merge conflicts:

```bash
git diff --name-only --diff-filter=U
```

Inform user they must resolve conflicts before committing.

### Hooks Failed

If pre-commit hooks fail:

1. Show hook output
2. Ask user to fix issues
3. Do NOT bypass hooks with `--no-verify`

## Notes

- This workflow enforces quality but doesn't replace code review
- Commits should be atomic: one logical change per commit
- When in doubt about commit type, ask user
- Always review staged changes before committing
