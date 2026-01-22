# BATS Setup/Reset Directory Tests

**Status:** In Progress **Size:** Small **Scope:** chore **Created:** 2026-01-22
**Planning:** Not required **Commits:** `47e0544`

## Context

`make setup` and `make reset` handle directory creation/deletion including
ownership fixes for root-owned files (from container operations). These need
BATS tests to prevent regressions.

## Goal

Add BATS tests for setup/reset directory handling, including root-owned file
scenarios using Docker-in-Docker.

## Implementation

### Test 1: `make setup` creates `dist/` directory

```bash
@test "make setup creates dist directory" {
  rm -rf dist
  run make setup
  [ "$status" -eq 0 ]
  [ -d "dist" ]
}
```

### Test 2: `make reset` removes `dist/` directory

```bash
@test "make reset removes dist directory" {
  mkdir -p dist
  touch dist/test.txt
  run make reset <<< "RESET"
  [ "$status" -eq 0 ]
  [ ! -d "dist" ]
}
```

### Test 3: Ownership fix for root-owned files (Docker-in-Docker)

```bash
@test "make setup fixes root-owned backups directory" {
  # Create root-owned directory via Docker
  docker run --rm -v "$(pwd)/backups:/backups" alpine:3.21 \
    sh -c "mkdir -p /backups/test && chown root:root /backups/test"

  # Verify root ownership
  run find backups -user root
  [ -n "$output" ]

  # Run setup - should fix ownership
  run make setup
  [ "$status" -eq 0 ]

  # Verify user ownership
  run find backups -user root
  [ -z "$output" ]
}
```

### Test 4: Lockfile directory bug (Docker-in-Docker)

```bash
@test "composer-install handles lockfile as directory" {
  # Create lockfile as directory (simulates Docker bind mount bug)
  docker run --rm -v "$(pwd):/app" -w /app alpine:3.21 \
    sh -c "rm -f composer.lock && mkdir composer.lock"

  # Verify it's a directory
  [ -d "composer.lock" ]

  # Run composer-install - should fix and succeed
  run make composer-install
  [ "$status" -eq 0 ]

  # Verify lockfile is now a file with content
  [ -f "composer.lock" ]
  [ -s "composer.lock" ]
}

@test "pnpm-install handles lockfile as directory" {
  # Create lockfile as directory (simulates Docker bind mount bug)
  docker run --rm -v "$(pwd):/app" -w /app alpine:3.21 \
    sh -c "rm -f pnpm-lock.yaml && mkdir pnpm-lock.yaml"

  # Verify it's a directory
  [ -d "pnpm-lock.yaml" ]

  # Run pnpm-install - should fix and succeed
  run make pnpm-install
  [ "$status" -eq 0 ]

  # Verify lockfile is now a file with content
  [ -f "pnpm-lock.yaml" ]
  [ -s "pnpm-lock.yaml" ]
}
```

### Test 5: Fresh setup from clean state

```bash
@test "make setup succeeds from completely fresh state" {
  # This is a destructive test - run in isolated environment
  run make reset <<< "RESET"
  [ "$status" -eq 0 ]

  run make init
  [ "$status" -eq 0 ]

  run make setup
  [ "$status" -eq 0 ]

  # Verify key artifacts exist
  [ -d "dist" ]
  [ -d "build" ]
  [ -f "composer.lock" ]
  [ -s "composer.lock" ]
  [ -f "pnpm-lock.yaml" ]
  [ -s "pnpm-lock.yaml" ]
}
```

## Files

- `tests/bats/make-setup-reset.bats` (new)

## Notes

- Tests 3-5 require BATS to run with Docker socket access (Docker-in-Docker or
  host Docker socket mounted)
- Existing BATS infrastructure supports this via `tests/bats/helpers/`
- Test 5 is destructive and should only run in CI or isolated environments

## Learnings from Implementation

### Shebang Clarification

**BATS tests and Node container are separate!**

- BATS tests run in `zappzarapp-bats` container (has bash)
- Node entrypoint runs in `zappzarapp-node` container (Alpine, no bash)
- Node entrypoint should use `#!/bin/sh` (Alpine's ash)
- This does NOT break BATS tests

### pnpm EBUSY Error

Docker bind mounts don't support atomic rename. pnpm's lockfile write fails with
EBUSY. Solution: Generate lockfile in temp directory, copy back with
`cat > file`.

### Empty Lockfile JSON Error

Composer fails if lockfile exists but is empty (not valid JSON). Solution:
Delete empty lockfile or write `{}` before running composer.

### BATS/CI Permission Error

When BATS runs as root (UID 0), files created on host have root ownership.
PHP/Node containers run as user 1000 and can't write to these files.

Solution: Use `USER_ID/GROUP_ID` from `.env` (not `$(shell id -u)`) when setting
ownership in Makefile targets.
