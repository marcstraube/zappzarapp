# Make Targets Overview

## Prerequisites

Most targets require running containers. Start with `make up` if containers are
not running. After Docker config changes: `make build-*` +
`make down && make up`.

## By File Type

| File Type   | Check Targets                                      | Fix Targets                     | Test Targets |
| ----------- | -------------------------------------------------- | ------------------------------- | ------------ |
| PHP         | `analyse-php`, `phpmd`, `cs-check`, `rector-check` | `cs-fix`, `rector-fix`          | `test-php`   |
| Node/TS     | `lint-node`, `analyse-node`, `prettier-check`      | `lint-node-fix`, `prettier-fix` | `test-node`  |
| SQL         | `lint-sql`                                         | `lint-sql-fix`                  | —            |
| Shell       | `lint-shell`                                       | —                               | —            |
| Markdown    | `lint-md`                                          | `lint-md-fix`                   | —            |
| Docker      | `lint-docker`                                      | —                               | `goss-test`  |
| YAML/Config | `lint-config`                                      | —                               | —            |
| All         | `check`                                            | —                               | `test`       |

## Reviewer Workflow

```text
1. Auto-Fix    → make cs-fix prettier-fix lint-node-fix lint-sql-fix lint-md-fix
2. Lint/Check  → make check (or specific targets)
3. Tests       → make test (or specific targets)
```

## Targeted Testing & Linting

Many targets support `ARGS` parameter for faster, focused checks:

```bash
# PHP - Single file or directory
make cs-fix ARGS="src/php/App/Http/Controller.php"
make analyse-php ARGS="src/php/App"
make test-php ARGS="--filter testUserLogin"
make rector-check ARGS="src/php/DevToolbar"

# Node/TS - Single file or pattern
make prettier-fix ARGS="src/node/backend/index.ts"
make lint-node ARGS="src/node/**/*.test.ts"
make test-node ARGS="tests/unit/auth.test.ts"
make analyse-node ARGS="src/node/backend"

# Coverage - Specific test suites
make test-coverage-php ARGS="--testsuite Unit"
make test-coverage-node ARGS="tests/unit"
```

**Performance:**

- Whole project: 5-15s (all files)
- Single file: 0.3-1s (with ARGS) ⚡

## PHP Changes

```bash
# Fix (all files)
make cs-fix

# Fix (single file - faster)
make cs-fix ARGS="src/php/App/helpers.php"

# Check
make analyse-php phpmd cs-check rector-check

# Test (all tests)
make test-php

# Test (filtered)
make test-php ARGS="--filter Authentication"
```

## Node/TS Changes

```bash
# Fix (all files)
make prettier-fix lint-node-fix

# Fix (single file - faster)
make prettier-fix ARGS="vite.config.ts"

# Check
make lint-node analyse-node prettier-check

# Test (all tests)
make test-node

# Test (single file)
make test-node ARGS="tests/unit/api.test.ts"
```

## SQL Changes

```bash
# Fix
make lint-sql-fix

# Check
make lint-sql

# Apply migrations (against configured DB)
make db-migrations
```

## Shell Script Changes

```bash
# Check (no auto-fix — fix manually)
make lint-shell
```

See `.zappzarapp/standards/shell.md` for coding standards.

## Docker Changes

```bash
# Check (no auto-fix)
make lint-docker

# Test
make goss-test           # Runtime tests
make goss-test-build     # Build-time tests
```

## Mixed Changes

```bash
# All fixes
make cs-fix prettier-fix lint-node-fix lint-sql-fix lint-md-fix

# All checks
make check

# All tests
make test
```

## Coverage Reports

```bash
make test-coverage-php   # build/coverage/php/
make test-coverage-node  # build/coverage/node/
make test-coverage       # Both
```

## Dependency Changes

After adding/removing packages, always sync local dependencies for IDE support:

```bash
# PHP: After make composer CMD="require ..."
make composer-install-local

# Node: After make pnpm CMD="add ..."
make pnpm-install-local
```

**Note:** Both commands check if local tool exists. Safe to run always.

| Action              | Container Command                        | Local Sync                    |
| ------------------- | ---------------------------------------- | ----------------------------- |
| Add PHP package     | `make composer CMD="require vendor/pkg"` | `make composer-install-local` |
| Add Node package    | `make pnpm CMD="add pkg"`                | `make pnpm-install-local`     |
| Remove PHP package  | `make composer CMD="remove vendor/pkg"`  | `make composer-install-local` |
| Remove Node package | `make pnpm CMD="remove pkg"`             | `make pnpm-install-local`     |

## Release Workflow

Uses `standard-version` for automated versioning and CHANGELOG generation from
conventional commits.

```bash
# Preview next release
make release-dry

# Create patch release (0.0.X)
make release

# Create minor release (0.X.0)
make release-minor

# Create major release (X.0.0)
make release-major

# First release for new projects
make release-first
```

After release, push with tags:

```bash
git push --follow-tags
```

**Note:** Releases require a clean working directory and conventional commit
messages. See `.versionrc.json` for commit type → CHANGELOG section mapping.
