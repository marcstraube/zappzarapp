# Node.js Tests

This directory contains tests for the Node.js backend using Vitest (similar to PHPUnit for PHP).

## Directory Structure

```
tests/node/              # Node.js tests (Vitest)
├── unit/                # Unit tests for individual functions/utilities
│   └── math.test.ts
├── integration/         # Integration tests for API endpoints
│   └── api.test.ts
└── README.md

Note: PHP tests are in tests/php/ (PHPUnit), mirroring the src/php/ and src/node/ structure.
```

## Running Tests

```bash
# Run all Node.js tests
make test-node

# Run tests in watch mode (re-runs on file changes)
make test-node-watch

# Generate coverage report
make test-coverage-node
```

Alternatively, you can use pnpm commands directly in the container:

```bash
# Run tests via pnpm
docker compose exec node pnpm test

# Watch mode
docker compose exec node pnpm test:watch

# With UI
docker compose exec node pnpm test:ui

# With coverage
docker compose exec node pnpm test:coverage
```

## Coverage Reports

Coverage reports are generated in `build/coverage/node/`:
- `build/coverage/node/index.html` - HTML coverage report (similar to PHPUnit)
- `build/coverage/node/lcov.info` - LCOV format for CI/CD integration

## Writing Tests

### Unit Tests

Place unit tests in `tests/node/unit/` directory. Example:

```typescript
import { describe, it, expect } from 'vitest';
import { myFunction } from '@node/utils/myFunction';

describe('MyFunction', () => {
  it('should return expected result', () => {
    expect(myFunction(1, 2)).toBe(3);
  });
});
```

### Integration Tests

Place integration tests in `tests/node/integration/` directory. Example:

```typescript
import { describe, it, expect, beforeAll } from 'vitest';
import request from 'supertest';
import { createApp } from '@node/app';
import type { Express } from 'express';

describe('API Tests', () => {
  let app: Express;

  beforeAll(() => {
    app = createApp();
  });

  it('should return 200', async () => {
    await request(app).get('/api/hello').expect(200);
  });
});
```

## Path Aliases

The following aliases are available in tests:

- `@` -> `./src`
- `@node` -> `./src/node`
- `@tests` -> `./tests/node`

## Quality Thresholds

Minimum coverage requirements (configured in `vitest.config.ts`):
- Lines: 80%
- Functions: 80%
- Branches: 80%
- Statements: 80%

Similar to PHPUnit's coverage requirements in the PHP stack.

## Additional Tools

### ESLint (Static Analysis)

Run linting (similar to PHPStan for PHP):
```bash
# Check for linting issues
docker compose exec node pnpm lint

# Auto-fix linting issues
docker compose exec node pnpm lint:fix
```

### Prettier (Code Formatting)

Check and fix code formatting (similar to PHP-CS-Fixer):
```bash
# Check formatting
docker compose exec node pnpm format:check

# Fix formatting
docker compose exec node pnpm format
```

### Complete Quality Check

Run all quality checks at once:
```bash
# Runs format-check, lint, type-check, and test
docker compose exec node pnpm quality
```
