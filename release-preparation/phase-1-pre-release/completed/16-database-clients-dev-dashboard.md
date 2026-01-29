# 16: Database Clients for Dev Environment

## Goal

Install database clients in dev container and integrate them into the Dev
Dashboard for easy access.

## Tasks

1. **Install DB clients in dev container**
   - PostgreSQL client (`psql`)
   - MySQL/MariaDB client (`mysql`)
   - SQLite client (`sqlite3`)

2. **Dev Dashboard integration**
   - Add database connection UI to dashboard
   - Show connection status for configured databases
   - Quick-access buttons for common operations

3. **JetBrains IDE integration note**
   - Document that zappzarapp includes `.idea` database configuration
   - Reference `make db-*` commands for IDE data source setup
   - Add hint in Dev Dashboard pointing to IDE integration

## Files to Investigate

- `docker/dev/Dockerfile` - Add client packages
- `src/php/DevDashboard/` - Dashboard integration
- `.idea/dataSources.xml` - Existing IDE configuration

## Priority

Medium - Developer convenience feature.

## Notes

For JetBrains users, the integrated `.idea` configuration already provides
database tooling. The Dev Dashboard should mention this as an alternative.
