# Project Context

Project-specific information for all agents.

## Architecture

- DevDashboard is a standalone module (own routes, controllers, services)
- Vite for frontend build (resources/ → public/build/)
- Multi-DB support: PostgreSQL (default), MariaDB (optional)

## Test Conventions

- PHP: tests/php/{Module}/Unit/ and tests/php/{Module}/Feature/
- Node: tests/node/backend/unit/ and tests/node/backend/integration/
- Test class suffix: `*_Test.php` / `*.test.ts`
- Coverage reports: build/coverage/
- New code always requires corresponding tests (Unit/Feature)
