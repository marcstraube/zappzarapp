---
name: worktree
description: Manage git worktrees for parallel development
model: haiku
context: fork
allowed-tools:
  - Read
  - Write
  - Bash(git:*)
  - Bash(ls:*)
  - Bash(mkdir:*)
  - Bash(rm:*)
  - AskUserQuestion
argument-hint: '--create <branch> | --list | --remove <name> | --status'
---

# Git Worktree Management

Manage git worktrees for parallel development with isolated environments.

## What are Worktrees?

Git worktrees allow you to have multiple branches checked out simultaneously in
separate directories. This enables:

- Working on multiple features in parallel
- Running tests on one branch while developing on another
- Keeping a stable version available while experimenting

## Arguments

Parse `$ARGUMENTS`:

- `--create <branch>`: Create a new worktree with the specified branch
- `--list`: List all worktrees with their status
- `--remove <name>`: Remove a worktree (with clean check)
- `--status`: Show current worktree context

## Port Convention

Each worktree should use different ports to avoid conflicts:

| Worktree | HTTP | HTTPS | Node | PgSQL | Redis |
| -------- | ---- | ----- | ---- | ----- | ----- |
| main     | 8080 | 8443  | 3000 | 5432  | 6379  |
| wt-1     | 8081 | 8444  | 3001 | 5433  | 6380  |
| wt-2     | 8082 | 8445  | 3002 | 5434  | 6381  |
| wt-3     | 8083 | 8446  | 3003 | 5435  | 6382  |

## Workflow: Create Worktree

```bash
/worktree --create feature/new-auth
```

### Steps

1. **Validate branch name**

   ```bash
   # Check if branch exists remotely
   git ls-remote --heads origin feature/new-auth

   # If not, will create new branch
   ```

2. **Determine worktree path**

   ```bash
   # Default: sibling directory with wt- prefix
   MAIN_DIR=$(basename $(pwd))
   WORKTREE_DIR="../${MAIN_DIR}-wt-feature-new-auth"
   ```

3. **Create worktree**

   ```bash
   # For existing branch
   git worktree add "$WORKTREE_DIR" feature/new-auth

   # For new branch (from current HEAD)
   git worktree add -b feature/new-auth "$WORKTREE_DIR"
   ```

4. **Setup environment**

   ```bash
   # Copy .env if it doesn't exist
   cp .env "$WORKTREE_DIR/.env" 2>/dev/null || true

   # Adjust ports in .env (increment based on worktree number)
   ```

5. **Show port configuration**

   ```text
   Worktree Created
   ================
   Path:   ../project-wt-feature-new-auth
   Branch: feature/new-auth

   Port Configuration (update .env):
     HTTP_PORT=8081
     HTTPS_PORT=8444
     NODE_PORT=3001
     POSTGRES_PORT=5433

   Next steps:
     cd ../project-wt-feature-new-auth
     make up
   ```

## Workflow: List Worktrees

```bash
/worktree --list
```

### Output

```text
Git Worktrees
=============

| Path                          | Branch              | Status       |
| ----------------------------- | ------------------- | ------------ |
| /home/user/project            | develop (primary)   | OK clean     |
| /home/user/project-wt-auth    | feature/new-auth    | OK 3 changes |
| /home/user/project-wt-hotfix  | fix/urgent-bug      | ! stale      |

Total: 3 worktrees

Notes:
- Primary worktree contains .git directory
- Stale worktrees have missing branches or are prunable
```

### Commands Used

```bash
# List all worktrees
git worktree list --porcelain

# Check for stale worktrees
git worktree list | while read path; do
  git -C "$path" status --short
done
```

## Workflow: Remove Worktree

```bash
/worktree --remove wt-auth
```

### Steps

1. **Find worktree by name**

   ```bash
   # Match by partial name
   git worktree list | grep -i "wt-auth"
   ```

2. **Check for uncommitted changes**

   ```bash
   cd "$WORKTREE_PATH"
   git status --short
   ```

3. **If changes exist, prompt user**

   ```text
   Worktree has uncommitted changes:
     M src/Auth/Login.php
     A tests/AuthTest.php

   Options:
   o Stash changes and remove
   o Commit changes first
   o Force remove (lose changes)
   o Cancel
   ```

4. **Remove worktree**

   ```bash
   git worktree remove "$WORKTREE_PATH"

   # Or force remove
   git worktree remove --force "$WORKTREE_PATH"
   ```

5. **Prune stale references**

   ```bash
   git worktree prune
   ```

## Workflow: Show Status

```bash
/worktree --status
```

### Output

```text
Current Worktree
================

Path:     /home/user/project-wt-feature-auth
Branch:   feature/new-auth
Type:     linked (secondary)
Primary:  /home/user/project

Git Status:
  Branch: feature/new-auth
  Ahead:  2 commits
  Behind: 0 commits
  Changes: 3 modified

Docker Status:
  Containers: Running on ports 8081, 8444

Session:
  Log: .claude/sessions/2026/01/session-2026-01-26-1234-new-auth.md
```

## Session Isolation

Sessions are automatically isolated per worktree because:

- Each worktree has its own `.claude/sessions/` directory
- Session files are not shared between worktrees

The SESSION-TEMPLATE.md includes a Worktree field:

```markdown
## Context

- **Worktree**: [worktree-path or "primary"]
- **Branch**: [branch-name]
```

## Best Practices

1. **Use descriptive worktree names**
   - Good: `project-wt-feature-auth`
   - Bad: `project-wt-1`

2. **Always adjust ports**
   - Update `.env` before running `make up`
   - Avoid port conflicts between worktrees

3. **Keep worktrees clean**
   - Remove worktrees when branches are merged
   - Run `git worktree prune` periodically

4. **Separate dependencies**
   - Run `make composer-install` and `make pnpm-install` in each worktree
   - Dependencies are per-worktree

## Common Issues

### "fatal: is already checked out"

The branch is already checked out in another worktree.

```bash
# Find where it's checked out
git worktree list | grep <branch>

# Remove or switch branches in that worktree first
```

### Stale worktree references

```bash
# Prune references to removed worktrees
git worktree prune

# Force prune
git worktree prune --dry-run  # Preview
git worktree prune            # Execute
```

### Port conflicts

Check which ports are in use:

```bash
lsof -i :8080
lsof -i :5432
```

## Integration

- `/status` shows worktree context
- Session logs include worktree path
- Docker environments are isolated per worktree
