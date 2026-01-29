# Database Schema Diff / Advanced Migrations

**Status:** Planned **Size:** Large **Scope:** feature **Created:** 2026-01-19
**Planning:** Required

## Context

Feature request for automated schema diff generation.

## Goal

Extend `make db-migrations` with schema-diff capabilities:

1. `make db-migrate` - Apply migrations (current functionality)
2. `make db-diff` - Generate migration SQL from schema changes

## Before Starting

Use Plan Mode to evaluate:

- Best tooling for this project (Atlas, Flyway, Skeema, migra, etc.)
- Multi-DB support requirements (PostgreSQL + MariaDB)
- "Source of Truth" approach (code-first vs. DB-first)
- Docker integration and CI/CD considerations
- Complexity vs. benefit trade-off

## Candidate Tools

| Tool   | PostgreSQL | MariaDB | Notes                   |
| ------ | ---------- | ------- | ----------------------- |
| Atlas  | ✅         | ✅      | Go, declarative, modern |
| Flyway | ✅         | ✅      | Java, widely adopted    |
| Skeema | ❌         | ✅      | MySQL/MariaDB only      |
| migra  | ✅         | ❌      | Python, PostgreSQL only |

## Notes

- Current simple SQL-file approach may be sufficient for most users
- This is an enhancement for larger projects
- Keep backwards compatibility with existing `migrations/` structure
