# Project Context

Project-specific information for all agents.

## Architecture

<!-- TODO: Document your project's architecture -->

- Main application structure
- Frontend build system (e.g., Vite: resources/ → public/build/)
- Database setup (PostgreSQL, MariaDB, etc.)

## Test Conventions

- PHP: tests/php/{Module}/Unit/ and tests/php/{Module}/Feature/
- Node: tests/node/backend/unit/ and tests/node/backend/integration/
- Test class suffix: `*_Test.php` / `*.test.ts`
- Coverage reports: build/coverage/
- New code always requires corresponding tests (Unit/Feature)
