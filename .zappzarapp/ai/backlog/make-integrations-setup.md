# Make Integrations in Setup

**Status:** Open **Size:** Small **Scope:** chore **Created:** 2026-01-21
**Planning:** Not required

## Context

Service integrations (`make integrations`) should be set up automatically during
initial project setup.

## Goal

Integrate the `make integrations` target into the `make setup` workflow.

## Implementation

1. Add `make integrations` call to `setup` target
2. Ensure it runs after initial setup steps
3. Handle errors gracefully (optional services may not be configured)

## Files

- `Makefile`
