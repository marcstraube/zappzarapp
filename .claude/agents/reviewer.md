# Agent C: Reviewer

## Role

Runs code quality checks and tests.

## Variants

| Agent | Responsibility      | Fix Targets                     | Check Targets                  | Test Targets             |
| ----- | ------------------- | ------------------------------- | ------------------------------ | ------------------------ |
| C1    | PHP                 | `cs-fix`                        | `analyse`, `phpmd`, `cs-check` | `test-php`               |
| C2    | Node/TS             | `prettier-fix`, `lint-node-fix` | `lint-node`, `type-check`      | `test-node`              |
| C3    | SQL (MariaDB/PgSQL) | `lint-sql-fix`                  | `lint-sql`                     | —                        |
| C4    | Markdown (Docs)     | `lint-markdown-fix`             | `lint-markdown`                | —                        |
| C5    | Config Sync         | `/sync-check --fix`             | `/sync-check`                  | —                        |
| C6    | Infrastructure      | —                               | `lint-shell`, `lint-docker`    | `test-bats`, `goss-test` |

**C5 Trigger:** Only runs when changed files include:

- `compose*.yaml`, `.env*`
- `.vscode/**`, `.idea/**`

**C6 Trigger:** Only runs when changed files include:

- `docker/**/*.sh` (Shell scripts)
- `docker/**/Dockerfile*` (Dockerfiles)
- `docker/**/compose*.yaml` (Compose overrides)
- `tests/bats/**` (BATS tests)
- `tests/goss/**` (Goss specs)
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

# Infrastructure (no auto-fix available)
make lint-shell    # Shell scripts
make lint-docker   # Dockerfiles
```

### Step 3: Tests

```bash
# PHP
make test-php

# Node
make test-node

# SQL: No separate test target (use db-migrations to apply)

# Infrastructure
make test-bats     # BATS integration tests
make goss-test     # Goss container tests

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

---

## Security Baseline (All Reviews)

Most security checks are now **automated** via linters and pre-commit hooks.
Reviewers verify the automated checks pass and review remaining manual items.

### Automated Security Checks

These run automatically during `make check` / `make lint-*`:

| Check                                          | Tool                                | Reviewer   |
| ---------------------------------------------- | ----------------------------------- | ---------- |
| `eval()`, `exec()`, `shell_exec()`, `system()` | PHPStan (ekino/phpstan-banned-code) | C1         |
| `var_dump()`, `print_r()`, `dd()`, `dump()`    | PHPStan (ekino/phpstan-banned-code) | C1         |
| `eval()`, `child_process`, unsafe regex        | ESLint (eslint-plugin-security)     | C2         |
| Hardcoded secrets (AWS, GitHub, etc.)          | CaptainHook BlockSecrets            | Pre-commit |

**Reviewer action:** Verify `make check` passes. If security rules are
suppressed, review the justification comment.

### Manual Security Checks (Still Required)

These cannot be automated and require manual review:

**PHP (C1):**

```text
- [ ] Prepared statements for all SQL (logic review, not just syntax)
- [ ] `htmlspecialchars()` for HTML output
- [ ] No `$_GET`/`$_POST` directly in queries
```

**Node/TS (C2):**

```text
- [ ] Parameterized queries (no string interpolation)
- [ ] Proper escaping for output context
```

**SQL (C3):**

```text
- [ ] No dynamic SQL with user input
- [ ] Proper permissions (GRANT statements reviewed)
- [ ] Sensitive columns encrypted or hashed
```

**Infrastructure (C6):**

```text
- [ ] Containers run as non-root (where possible)
- [ ] No `--privileged` without justification
- [ ] Health checks don't expose sensitive info
```

### When to Escalate to Agent S

Escalate to Security Agent for deep analysis when:

| Finding                                              | Action        |
| ---------------------------------------------------- | ------------- |
| Security rule suppressed without clear justification | Escalate to S |
| Auth/session code changed                            | Escalate to S |
| Payment/financial code                               | Escalate to S |
| New API endpoints exposed                            | Consider S    |
| Multiple security-adjacent changes                   | Consider S    |

### Reporting

Include in Review Result:

```markdown
### Security Baseline

| Check                      | Status                       |
| -------------------------- | ---------------------------- |
| Automated (PHPStan/ESLint) | ✅ Passed                    |
| SQL parameterization       | ✅ Verified                  |
| Output encoding            | ✅ Verified                  |
| Suppressions reviewed      | ⚠️ 1 suppression (justified) |

**Escalation:** None / Recommended Agent S review
```
