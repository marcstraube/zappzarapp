---
description: Smart test runner based on changed files
context: fork
allowed-tools: Read, Grep, Glob, Bash(make:*), Bash(git:*), Bash(docker:*),
  Bash(docker compose:*), Bash(goss:*), Bash(dgoss:*), Bash(ls:*)
argument-hint: [--full | --php | --node | --goss | --quick]
---

# Smart Test Runner

Runs appropriate tests based on changed files or explicit flags.

## Arguments

Parse `$ARGUMENTS`:

- (default): Detect changed files and run relevant tests
- `--full`: Run all tests (PHP + Node + Goss)
- `--php`: Only PHP tests
- `--node`: Only Node tests
- `--goss`: Only Goss container tests
- `--quick`: Fast subset (no coverage, skip slow tests)

Examples:

```bash
/test                  # Auto-detect and test changed areas
/test --full           # Run everything
/test --php            # Only PHP tests
/test --goss           # Only container tests
/test --quick          # Fast pre-commit check
```

## Detection Logic

### Step 1: Identify Changed Files

```bash
# Get changed files (staged + unstaged)
git diff --name-only HEAD
git diff --name-only --cached
```

### Step 2: Categorize Changes

| Pattern | Category | Tests to Run |
|---------|----------|--------------|
| `src/php/**`, `tests/php/**` | PHP | `make test-php` |
| `src/node/**`, `tests/node/**` | Node | `make test-node` |
| `docker/**`, `compose*.yaml` | Infrastructure | `make goss-test` |
| `Makefile` | Build | `make goss-test` (verify targets work) |
| `.env.example` | Config | `make goss-test` |
| `resources/**` | Frontend | `make test-node` (if Vite tests exist) |

### Step 3: Run Tests

For each detected category, run the appropriate make target.

## Test Commands

### PHP Tests

```bash
# Full test suite with coverage
make test-php

# Quick test (no coverage)
make test-php-quick
```

Check output for:
- Test failures
- Coverage percentage
- PHPStan errors (if integrated)

### Node Tests

```bash
# Full test suite
make test-node

# Quick test
make test-node-quick
```

Check output for:
- Test failures
- TypeScript errors
- ESLint issues

### Goss Container Tests

```bash
# Test running containers
make goss-test

# Full matrix (all presets)
make goss-test-matrix
```

Prerequisites:
- Containers must be running (`make up`)
- Goss must be installed (`make goss-check`)

## Quick Mode (`--quick`)

For fast feedback during development:

```bash
# PHP: No coverage, fail fast
vendor/bin/phpunit --no-coverage --stop-on-failure

# Node: No coverage, fail fast
pnpm test -- --bail

# Goss: Only core services (nginx, php, database)
make goss-test-nginx
make goss-test-php
make goss-test-postgres
```

## Full Mode (`--full`)

Complete test suite for CI/pre-release:

```bash
make test              # All unit/integration tests
make goss-test-matrix  # All container configurations
make check             # All quality checks
```

## Output Format

```text
╔══════════════════════════════════════════════════════════════╗
║ Smart Test Runner                                            ║
╠══════════════════════════════════════════════════════════════╣
║ Mode: auto-detect                                            ║
║ Changed: 5 files (3 PHP, 2 Docker)                           ║
╠══════════════════════════════════════════════════════════════╣
║ Running: PHP Tests                                           ║
║ ✓ 42 tests passed                                            ║
║ Coverage: 87%                                                ║
╠══════════════════════════════════════════════════════════════╣
║ Running: Goss Container Tests                                ║
║ ✓ nginx: 8/8 passed                                          ║
║ ✓ php: 6/6 passed                                            ║
║ ✓ postgres: 5/5 passed                                       ║
╠══════════════════════════════════════════════════════════════╣
║ Result: ALL PASSED                                           ║
╚══════════════════════════════════════════════════════════════╝
```

## Error Handling

### Container Not Running

If Goss tests needed but containers not running:

```text
[WARN] Goss tests skipped: No containers running
       Run 'make up' first, then re-run /test --goss
```

### Goss Not Installed

```text
[WARN] Goss not installed
       Install: curl -fsSL https://goss.rocks/install | sh
```

### Test Failures

On any test failure:
1. Show failure details
2. Show relevant log excerpts
3. Suggest next steps

```text
[FAIL] PHP Tests: 2 failures

  1) UserServiceTest::testCreateUser
     Expected status 201, got 500

  2) AuthControllerTest::testLogin
     Missing required field 'email'

Suggestion: Check src/php/App/Service/UserService.php:42
```

## Integration with /commit

The `/commit` command calls `make check` which includes tests.
Use `/test --quick` for faster iteration during development.

## Notes

- Auto-detection requires git to be initialized
- Coverage reports saved to `build/coverage/`
- Goss tests require running containers
- Use `--full` before creating PRs
