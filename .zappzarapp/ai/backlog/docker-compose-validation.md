# Docker Compose Validation

**Status:** Planned **Size:** Small **Scope:** chore **Created:** 2026-01-20
**Planning:** Not required

## Context

Complex compose.yaml files should be validated before `make up`.

## Goal

Add `make compose-validate` to catch syntax errors early.

## What It Validates

- YAML syntax errors
- Invalid service configurations
- Missing required fields
- Environment variable interpolation
- Profile configuration

## Implementation

```makefile
compose-validate: ## Validate Docker Compose configuration
    @docker compose config --quiet && echo "✓ compose.yaml valid"
    @docker compose -f compose.production.yaml config --quiet && echo "✓ compose.production.yaml valid"
```

## Integration

- Add to `make check` pipeline
- Run before `make up` (optional, adds latency)
- Document validation in troubleshooting guide

## Files

- `Makefile`
- `documentation/development/MAKEFILE-REFERENCE.md`
