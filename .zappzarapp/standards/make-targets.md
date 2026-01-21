# Make Targets Overview

## By File Type

| File Type   | Check Targets                                  | Fix Targets                     | Test Targets |
| ----------- | ---------------------------------------------- | ------------------------------- | ------------ |
| PHP         | `analyse`, `phpmd`, `cs-check`, `rector-check` | `cs-fix`, `rector-fix`          | `test-php`   |
| Node/TS     | `lint-node`, `type-check`, `prettier-check`    | `lint-node-fix`, `prettier-fix` | `test-node`  |
| SQL         | `lint-sql`                                     | `lint-sql-fix`                  | `test-sql`   |
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

# Test (against real DB)
make test-sql
```

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
