---
description: Guided commit workflow with quality checks and conventional commit format
context: fork
allowed-tools: Read, Grep, Glob, Bash(make:*), Bash(git:*), AskUserQuestion
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
| `documentation/`        | `docs`          |
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

1. "Use this message?" → Proceed to commit
2. "Edit message?" → Let user provide custom message
3. "Abort?" → Cancel commit

### Step 8: Execute Commit

```bash
# Stage any additional files if needed (ask user first)
git add <files>

# Commit with the message
git commit -m "<message>"

# Or amend if --amend flag
git commit --amend -m "<message>"
```

### Step 9: Post-Commit

Show the result:

```bash
# Show the new commit
git log -1 --oneline

# Show current status
git status
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

1. Add `!` after type/scope: `feat(api)!: change response format`
2. Add `BREAKING CHANGE:` footer explaining the impact

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
