# Shell Script Linting (shellcheck)

**Status:** Planned **Size:** Small **Scope:** chore **Created:** 2026-01-20
**Planning:** Not required

## Context

20+ shell scripts in `docker/` directory are not validated.

## Goal

Add shellcheck validation for all shell scripts to catch common errors.

## What Shellcheck Finds

- Unquoted variables (`$var` vs `"$var"`)
- Deprecated syntax, bashisms in sh scripts
- Missing error handling (`set -e`, `set -u`)
- Subshell pitfalls, word splitting issues
- SC2086, SC2046, and other common issues

## Scripts to Lint

- `docker/*/entrypoint*.sh`
- `docker/*/healthcheck.sh`
- `docker/scripts/*.sh`
- `docker/certs/*.sh`
- `docker/hooks/*.sh`
- `tests/bats/helpers/*.bash` (BATS test helpers)

## Implementation

1. Add `make lint-shell` target (via Docker: `koalaman/shellcheck`)
2. Create `.shellcheckrc` for project-wide config
3. Integrate into `make check` pipeline
4. Document in MAKEFILE-REFERENCE.md

## Files

Create:

- `.shellcheckrc` (shellcheck configuration)

Modify:

- `Makefile` (new `lint-shell` target, add to `check`)
- `documentation/development/MAKEFILE-REFERENCE.md`
