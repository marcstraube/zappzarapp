# SQL Standards

## Dialects

- PostgreSQL: Primary (default)
- MariaDB: Secondary (optional)

## Linting

- Tool: sqlfluff
- Config: `.sqlfluff`
- Both dialects are checked

### `latest` Image Drift Between CI and Local

`make lint-sql` runs `sqlfluff/sqlfluff:latest`. CI pulls a fresh image on every
run; the local Docker cache keeps whatever was pulled last. New sqlfluff
releases can add rules, so results diverge without any repo change — the same
drift pattern as untracked lockfiles, where CI resolves fresh dependencies
before local environments see them.

Reproduce CI results locally:

```bash
docker pull sqlfluff/sqlfluff:latest
make lint-sql
```

### Rule Exclusions

Prefer config-level `exclude_rules` in `.sqlfluff` with a reasoned comment over
per-line `-- noqa` annotations.

- `PG01` ("CREATE INDEX should use CONCURRENTLY") is excluded: inappropriate for
  bootstrap migrations — tables are empty at bootstrap (no lock contention), and
  `CREATE INDEX CONCURRENTLY` cannot run inside a transaction, so following the
  rule would break transactional migration runners.

## Best Practices

- Use prepared statements (never string concatenation)
- Explicit column names in SELECT (avoid `SELECT *`)
- Index frequently queried columns
- Use transactions for multi-statement operations
