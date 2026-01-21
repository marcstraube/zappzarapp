# Make Setup: Include API Documentation Generation

**Status:** Open **Size:** Small **Scope:** chore **Created:** 2026-01-21
**Planning:** Not required

## Context

Improve onboarding experience for new developers.

## Goal

Add `make docs` to `make setup` so API documentation is immediately available
after initial setup.

## Implementation

1. Add `make docs` call to `setup` target
2. Place after initial build steps complete
3. Handle gracefully if docs generation fails

## Files

- `Makefile` (`setup` target)

## Notes

New developers will have API docs from the start without extra steps.
