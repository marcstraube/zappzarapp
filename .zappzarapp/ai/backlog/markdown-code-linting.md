# Markdown Code Linting (Documentation Quality)

**Status:** Planned **Size:** Medium **Scope:** chore **Created:** 2026-01-20
**Planning:** Not required

## Context

Code examples in Markdown files are not validated by existing linters.

## Goal

Lint code blocks in Markdown documentation using existing linter configurations
to ensure examples are syntactically correct and follow project standards.

## Approach

Extract code blocks by language tag → write to temp files → run existing linters
with existing configs.

## Languages to Support

| Language              | Linter             | Config             |
| --------------------- | ------------------ | ------------------ |
| TypeScript/JavaScript | ESLint             | `eslint.config.js` |
| PHP                   | `php -l` + PHPStan | `phpstan.neon`     |
| SQL                   | sqlfluff           | `.sqlfluff` (new)  |
| Bash/Shell            | shellcheck         | (default rules)    |

## Implementation

1. Create `scripts/lint-markdown-code.sh` extraction script
2. Add sqlfluff configuration (`.sqlfluff`) for PostgreSQL + MariaDB dialects
3. Add shellcheck for bash code blocks
4. Create `make lint-docs-code` target
5. Integrate into `make check` pipeline
6. Document in MAKEFILE-REFERENCE.md

## Integration Points

- `Makefile`: new `lint-docs-code` target
- `make check`: add `lint-docs-code` to pipeline
- `.sqlfluff`: new config file (PostgreSQL default, MariaDB support)
- `package.json`: shellcheck if not available system-wide
- `documentation/development/MAKEFILE-REFERENCE.md`: document new target

## Files

Create:

- `scripts/lint-markdown-code.sh`
- `.sqlfluff`

Modify:

- `Makefile`
- `documentation/development/MAKEFILE-REFERENCE.md`

## Dependencies

- sqlfluff (Python, install via pip or Docker)
- shellcheck (system package or Docker)
