# Agent C: Reviewer

## Role

Runs code quality checks and tests.

## Variants

| Agent | Responsibility      | Fix Targets                     | Check Targets                  | Test Targets |
| ----- | ------------------- | ------------------------------- | ------------------------------ | ------------ |
| C1    | PHP                 | `cs-fix`                        | `analyse`, `phpmd`, `cs-check` | `test-php`   |
| C2    | Node/TS             | `prettier-fix`, `lint-node-fix` | `lint-node`, `type-check`      | `test-node`  |
| C3    | SQL (MariaDB/PgSQL) | `lint-sql-fix`                  | `lint-sql`                     | `test-sql`   |
| C4    | Markdown (Docs)     | `lint-markdown-fix`             | `lint-markdown`                | —            |
| C5    | Config Sync         | `/sync-check --fix`             | `/sync-check`                  | —            |

**C5 Trigger:** Only runs when changed files include:

- `compose*.yaml`, `docker/**`, `.env*`
- `.vscode/**`, `.idea/**`
- `Makefile`

## Workflow

```text
1. Auto-Fix
   ↓
2. Lint/Check
   ↓
3. Tests
   ↓
4. Config Sync (if config files changed)
   ↓
5. Evaluate results
```

### Step 1: Auto-Fix

```bash
# PHP
make cs-fix

# Node
make prettier-fix lint-node-fix

# SQL
make lint-sql-fix

# Markdown
make lint-markdown-fix
```

### Step 2: Lint/Check

```bash
# PHP
make analyse phpmd cs-check

# Node
make lint-node type-check

# SQL
make lint-sql

# Markdown
make lint-markdown
```

### Step 3: Tests

```bash
# PHP
make test-php

# Node
make test-node

# SQL
make test-sql

# Markdown: No tests (validation only)

# Config Sync: No tests (validation only)
```

### Step 4: Config Sync (C5, conditional)

Only if config files changed:

```bash
# Check sync status
/sync-check

# If issues found and auto-fixable
/sync-check --fix
```

### Step 5: Result

```text
   ┌────┴────┐
   ↓         ↓
  OK      Errors
   ↓         ↓
Continue  Feedback Loop
```

## Feedback Loop (C → B)

```text
Errors found
        ↓
   ┌────────────────┬────────────────┐
   ↓                ↓                ↓
Auto-fixable?   Not fixable     Severity?
   ↓                ↓                ↓
Apply fix       → Back to B     Apply filter
```

## Severity Filter

| Severity | Action                          |
| -------- | ------------------------------- |
| Error    | Must be fixed                   |
| Warning  | Fix if easy, otherwise document |
| Info     | Ignore                          |

## Receives from Main Agent

- List of changed files
- `.zappzarapp/standards/make-targets.md`
- Relevant `.zappzarapp/standards/<language>.md`

## Reports Back

```markdown
## Review Result

### Checks

| Check | Status | Errors |
| ----- | ------ | ------ |

### Tests

| Test Suite | Status | Failed |
| ---------- | ------ | ------ |

### Non-fixable Errors (for Coder)

- [Error 1]: [Description]

### Documented Warnings

- [Warning 1]: [Reason for ignoring]
```

## Retry Limit

Max 2 iterations with Coder, then escalation to Main Agent.
