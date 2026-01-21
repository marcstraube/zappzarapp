# Makefile Target Testing with BATS + Goss Integration

**Status:** Planned **Size:** Large **Scope:** chore **Created:** 2026-01-19
**Planning:** Required

## Context

Feature request for environment-specific testing of Make targets.

## Goal

Add automated tests for Makefile targets using BATS for command execution and
Goss for state validation.

## Before Starting

Use Plan Mode to analyze:

- Current Makefile structure and target dependencies
- Which targets are environment-sensitive (DB_TYPE, NODE_MODE, etc.)
- Existing Goss test structure and how to integrate
- CI/CD pipeline integration

## Tools

| Tool | Purpose                                          |
| ---- | ------------------------------------------------ |
| BATS | Command execution, exit codes, output validation |
| Goss | Container/system state validation after commands |

## Implementation

### Combined Approach

```bash
@test "make up creates healthy containers" {
  run make up
  [ "$status" -eq 0 ]

  # Goss validates resulting state
  run make goss-test
  [ "$status" -eq 0 ]
}
```

### Test Scenarios

1. **BATS-only tests:**
   - Target exit codes and basic functionality
   - Error handling (missing dependencies, containers not running)
   - Output validation for help/info targets

2. **BATS + Goss combined tests:**
   - `make up` → Goss validates container state
   - `make build-*` → Goss validates image contents
   - Environment matrix (DB_TYPE, NODE_MODE) → Goss validates config

### Subtask: Documentation Workflow Tests

Parse Markdown docs → extract shell commands → execute → verify success.

- GETTING-STARTED.md: Full setup workflow
- README.md: Quick start commands
- MAKEFILE-REFERENCE.md: All documented targets
- OPTIONAL-SERVICES.md: Service activation workflows

## Files

Create:

- `tests/bats/` directory structure
- `tests/bats/make-targets.bats`
- `tests/bats/make-environment.bats`
- `tests/bats/helpers/` (shared setup, Goss integration)
- `tests/bats/helpers/doc-parser.bash`

Modify:

- `Makefile` (new `test-bats` and `test-full` targets)

## Dependencies

- BATS installation (via package manager or git submodule)
- `bats-support` and `bats-assert` helper libraries
- Existing Goss setup (already in project)
