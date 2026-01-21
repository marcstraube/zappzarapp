# SQL Standards

## Dialects

- PostgreSQL: Primary (default)
- MariaDB: Secondary (optional)

## Linting

- Tool: sqlfluff
- Config: `.sqlfluff`
- Both dialects are checked

## Best Practices

- Use prepared statements (never string concatenation)
- Explicit column names in SELECT (avoid `SELECT *`)
- Index frequently queried columns
- Use transactions for multi-statement operations
