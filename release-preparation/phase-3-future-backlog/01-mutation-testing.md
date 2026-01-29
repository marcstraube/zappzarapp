# Task 01: Add Mutation Testing

## Priority

LOW - Future Backlog

## Estimated Effort

4-6 hours

## Context

Mutation testing validates test quality by introducing small changes (mutations)
to the code and checking if tests catch them. High mutation score indicates
robust tests that catch real bugs, not just achieve coverage.

## Current State

- PHPUnit and Vitest provide coverage metrics
- No mutation testing configured
- Test quality relies on coverage percentage alone

## Target State

1. Add Infection for PHP mutation testing
2. Add Stryker for Node.js mutation testing
3. Configure CI integration (optional, as mutation testing is slow)
4. Document acceptable mutation scores

## Implementation Outline

### PHP (Infection)

```bash
composer require --dev infection/infection
```

**Create `infection.json5`:**

```json5
{
    "$schema": "vendor/infection/infection/resources/schema.json",
    "source": { "directories": ["src/php"] },
    "logs": { "text": "build/infection.log" },
    "mutators": { "@default": true },
    "minMsi": 70,
    "minCoveredMsi": 80
}
```

**Makefile:**

```makefile
test-mutation-php: ## Run PHP mutation testing
	docker compose exec php vendor/bin/infection --threads=4
```

### Node.js (Stryker)

```bash
pnpm add -D @stryker-mutator/core @stryker-mutator/vitest-runner @stryker-mutator/typescript-checker
```

**Create `stryker.config.mjs`:**

```javascript
export default {
  packageManager: 'pnpm',
  testRunner: 'vitest',
  checkers: ['typescript'],
  reporters: ['html', 'clear-text', 'progress'],
  coverageAnalysis: 'perTest',
  mutate: ['src/node/**/*.ts', '!src/node/**/*.test.ts'],
};
```

## Notes

- Mutation testing is CPU-intensive; run locally or in scheduled CI
- Start with core business logic, not infrastructure code
- Aim for 70%+ mutation score on critical paths
- Can be run as part of pre-release verification, not every commit
