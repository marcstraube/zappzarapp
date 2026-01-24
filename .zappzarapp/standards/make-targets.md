# Make Targets Overview

## Prerequisites

Most targets require running containers. Start with `make up` if containers are
not running. After Docker config changes: `make build-*` +
`make down && make up`.

## By File Type

| File Type   | Check Targets                                  | Fix Targets                     | Test Targets |
| ----------- | ---------------------------------------------- | ------------------------------- | ------------ |
| PHP         | `analyse`, `phpmd`, `cs-check`, `rector-check` | `cs-fix`, `rector-fix`          | `test-php`   |
| Node/TS     | `lint-node`, `type-check`, `prettier-check`    | `lint-node-fix`, `prettier-fix` | `test-node`  |
| SQL         | `lint-sql`                                     | `lint-sql-fix`                  | —            |
| Shell       | `lint-shell`                                   | —                               | —            |
| Markdown    | `lint-md`                                      | `lint-md-fix`                   | —            |
| Docker      | `lint-docker`                                  | —                               | `goss-test`  |
| YAML/Config | `lint-config`                                  | —                               | —            |
| All         | `check`                                        | —                               | `test`       |

## Reviewer Workflow

```text
1. Auto-Fix    → make cs-fix prettier-fix lint-node-fix lint-sql-fix lint-md-fix
2. Lint/Check  → make check (or specific targets)
3. Tests       → make test (or specific targets)
```

## PHP Changes

```bash
# Fix
make cs-fix

# Check
make analyse phpmd cs-check rector-check

# Test
make test-php
```

## Node/TS Changes

```bash
# Fix
make prettier-fix lint-node-fix

# Check
make lint-node type-check prettier-check

# Test
make test-node
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
