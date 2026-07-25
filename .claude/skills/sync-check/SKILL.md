---
name: sync-check
description: Check synchronization between related configuration files
model: haiku
context: fork
allowed-tools:
  - Read
  - Grep
  - Glob
  - Bash(make:*)
  - Bash(git:*)
  - Bash(diff:*)
  - Bash(ls:*)
  - AskUserQuestion
argument-hint: '[--fix] [--category <name>]'
---

# Sync Check

Quick verification that related configuration files are synchronized.

## Purpose

Configuration files in this project often come in pairs or groups that must stay
in sync. This command identifies discrepancies before they cause issues.

## Arguments

Parse `$ARGUMENTS`:

- `--fix`: Attempt to fix simple sync issues (with confirmation)
- `--category <name>`: Only check specific category (docker, ide, env, all)

Examples:

```bash
/sync-check                    # Check all categories
/sync-check --category docker  # Only Docker configs
/sync-check --category ide     # Only IDE configs
/sync-check --fix              # Check and offer fixes
```

## Synchronization Categories

### Category: docker

Docker configuration files that must stay synchronized:

#### Compose Files

| Primary        | Must Sync With            |
| -------------- | ------------------------- |
| `compose.yaml` | `compose.override.yaml`   |
| `compose.yaml` | `compose.production.yaml` |

Check for:

- Services defined in one but missing in others
- Environment variables inconsistent
- Volume mounts differing unexpectedly
- Port mappings misaligned

#### Nginx Configs

| Primary                                             | Must Sync With                                     |
| --------------------------------------------------- | -------------------------------------------------- |
| `docker/nginx/conf.d/ssl-development.conf.template` | `docker/nginx/conf.d/ssl-production.conf.template` |

Check for:

- Location blocks present in one but not other
- SSL settings diverging inappropriately
- Proxy pass targets matching

#### PHP Configs

| Primary              | Must Sync With                      |
| -------------------- | ----------------------------------- |
| `docker/php/php.ini` | `docker/php/conf.d/development.ini` |

Check for:

- Settings that should differ (xdebug, display_errors)
- Settings that should match (timezone, memory_limit base)

#### Entrypoints

| Primary                               | Must Sync With                         |
| ------------------------------------- | -------------------------------------- |
| `docker/php/entrypoint.production.sh` | `docker/php/entrypoint.development.sh` |

Check for:

- PHP: Production minimal, development has full logic
- Node: Only `entrypoint.development.sh` exists (production uses direct CMD)
- Development-only additions (permission fixes, secrets copying)

#### Dockerfiles

For each Dockerfile with multiple targets:

- `docker/php/Dockerfile`: development vs production targets
- `docker/nginx/Dockerfile`: development vs production targets
- `docker/node/Dockerfile`: development vs production targets

Check for:

- Base image versions matching
- Shared layers identical
- Only final stage differs appropriately

### Category: env

Environment configuration synchronization:

#### .env Files

| Primary        | Must Sync With |
| -------------- | -------------- |
| `.env.example` | `.env`         |

Check for:

- Variables in `.env.example` missing from `.env`
- Variables in `.env` not documented in `.env.example`
- Default values that should match

### Category: ide

IDE configuration synchronization:

#### VS Code

| Primary              | Reference  |
| -------------------- | ---------- |
| `.vscode/tasks.json` | `Makefile` |

Check for:

- Makefile targets not in tasks.json
- Tasks referencing removed targets
- Task labels matching target descriptions

#### JetBrains (IntelliJ/PhpStorm)

| Primary                         | Reference  |
| ------------------------------- | ---------- |
| `.idea/runConfigurations/*.xml` | `Makefile` |

Check for:

- Makefile targets without run configurations
- Run configurations for removed targets
- Names matching target descriptions

Also check `.idea/workspace.xml`:

- RunManager list alphabetically sorted?
- All run configurations listed?

## Output Format

### Summary Table

```text
Sync Check Results
==================

| Category | Pairs Checked | In Sync | Issues |
| -------- | ------------- | ------- | ------ |
| docker   | 8             | 6       | 2      |
| env      | 1             | 0       | 1      |
| ide      | 2             | 2       | 0      |
| Total    | 11            | 8       | 3      |
```

### Issue Details

For each issue found:

```text
[WARN] docker/compose: Service 'mercure' in compose.yaml missing from compose.production.yaml
  -> Add mercure service to compose.production.yaml or mark as dev-only

[WARN] env: Variable 'NEW_FEATURE_FLAG' in .env missing from .env.example
  -> Add NEW_FEATURE_FLAG to .env.example with documentation

[INFO] ide/vscode: New Makefile target 'build-assets' not in tasks.json
  -> Run: Add task for 'build-assets' target
```

Severity levels:

- `[CRIT]`: Breaking inconsistency (production will fail)
- `[WARN]`: Should be fixed soon
- `[INFO]`: Nice to have, low priority

## Auto-Fix Capabilities (--fix)

When `--fix` is enabled, offer to fix these automatically:

### Safe to Auto-Fix

| Issue                       | Fix Action                  |
| --------------------------- | --------------------------- |
| Missing var in .env.example | Add with placeholder value  |
| Missing task in tasks.json  | Generate task from Makefile |
| Missing run configuration   | Generate XML from Makefile  |
| Unsorted workspace.xml      | Sort alphabetically         |

### Requires Confirmation

| Issue                           | Action                          |
| ------------------------------- | ------------------------------- |
| Missing service in compose file | Show diff, ask to copy          |
| Different config values         | Show both, ask which is correct |

### Never Auto-Fix

| Issue                    | Action                            |
| ------------------------ | --------------------------------- |
| Structural differences   | Report only, manual review needed |
| Security-related configs | Report only, manual review needed |

## Detailed Checks

### Compose File Comparison

```bash
# Extract service names from each file
grep -E '^\s{2}[a-z]' compose.yaml | awk '{print $1}' | tr -d ':'
grep -E '^\s{2}[a-z]' compose.override.yaml | awk '{print $1}' | tr -d ':'
grep -E '^\s{2}[a-z]' compose.production.yaml | awk '{print $1}' | tr -d ':'
```

Compare lists and identify:

- Services only in development
- Services only in production
- Services in both (should have same structure)

### Makefile Target Extraction

```bash
# Get all targets with descriptions
grep -E '^[a-zA-Z_-]+:.*##' Makefile | awk -F':.*##' '{print $1, $2}'
```

Compare against:

- `.vscode/tasks.json` task labels
- `.idea/runConfigurations/*.xml` filenames

### Environment Variable Extraction

```bash
# From .env.example (skip comments)
grep -E '^[A-Z_]+=|^#[A-Z_]+=' .env.example | grep -oE '^#?[A-Z_]+'

# From .env
grep -E '^[A-Z_]+=' .env | grep -oE '^[A-Z_]+'
```

## Notes

- Run this before commits that touch configuration files
- Part of the recommended pre-commit workflow
- Helps prevent "works on my machine" issues
- Quick enough to run frequently during development
