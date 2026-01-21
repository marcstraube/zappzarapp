# Unified Security Scan Target

**Status:** Planned **Size:** Small **Scope:** chore **Created:** 2026-01-20
**Planning:** Not required

## Context

After ESLint security plugin, Semgrep, and Psalm taint analysis are complete.

## Goal

Create unified `make security-check` that runs all security tools.

## Implementation

```makefile
security-check: security-scan-node security-scan-php security-scan  ## Run all security scans
security-scan-node:    ## ESLint security plugin
security-scan-php:     ## Psalm taint analysis
security-scan:         ## Semgrep (all languages)
```

## Recommended Workflow

- `make security-check` — Full scan (CI, pre-release)
- Individual targets for focused scanning during development

## Files

- `Makefile`

## Dependencies

- eslint-security-plugin task complete
- semgrep-integration task complete
- psalm-taint-analysis task complete
