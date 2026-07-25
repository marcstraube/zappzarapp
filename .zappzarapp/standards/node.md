# TypeScript/Node Standards

## Critical (Agent MUST Check)

Before writing Node/TypeScript code, verify these rules:

1. **Suppressions**: Only use directives listed in "Allowed" table below
2. **Undocumented warnings**: Do NOT suppress — ask user with options
3. **No `any` type**: Use `unknown` + type guard instead
4. **No `@ts-ignore`**: Use `@ts-expect-error` with explanation

### Handling Undocumented Warnings

If you encounter a warning not in the Allowed table:

```text
Undocumented Warning: [RuleName]
File: path/to/file.ts:42
Message: [ESLint/TS message]

Options:
1. Add to standards (Recommended if legitimate pattern)
   → Directive: // eslint-disable-next-line [rule]
   → When allowed: [Your description]

2. Fix the code instead
   → [Suggested fix]

3. Skip for now (leaves warning)
```

**Never suppress undocumented warnings without user approval.**

---

## ESLint Rules

**Type-aware rules require `parserOptions.project`:**

Rules like `@typescript-eslint/no-unnecessary-condition` need
`project: './tsconfig.json'` in `parserOptions` — without type information they
cannot run. `no-unnecessary-condition` catches redundant typeof checks and
always-true/false conditions.

**Special configuration for tests:**

```typescript
// In eslint.config.js - Test-specific rules
{
  files: ['tests/**/*.ts'],
  rules: {
    '@typescript-eslint/unbound-method': 'off',  // vi.mocked() returns unbound
  }
}
```

## Suppressions - Allowed

| Directive                     | When Allowed                       |
| ----------------------------- | ---------------------------------- |
| `// eslint-disable-next-line` | With comment explaining the reason |
| `@ts-expect-error`            | For known TypeScript limitations   |

## Suppressions - Forbidden

| Directive                        | Reason                                           |
| -------------------------------- | ------------------------------------------------ |
| `// @ts-ignore`                  | Always use `@ts-expect-error` with explanation   |
| `any` Type                       | Strict mode — use `unknown` + type guard instead |
| `eslint-disable` for entire file | Too broad, use per-line                          |

## Security Rules (eslint-plugin-security)

ESLint blocks dangerous patterns automatically:

| Rule                             | What it Detects               | Severity |
| -------------------------------- | ----------------------------- | -------- |
| `detect-eval-with-expression`    | `eval()` with dynamic content | Error    |
| `detect-child-process`           | `child_process` usage         | Error    |
| `detect-non-literal-require`     | Dynamic `require()`           | Error    |
| `detect-unsafe-regex`            | ReDoS vulnerable regex        | Error    |
| `detect-non-literal-regexp`      | Dynamic RegExp constructor    | Warning  |
| `detect-possible-timing-attacks` | Timing-based comparisons      | Warning  |

**If you need to suppress a security rule:**

1. This is a red flag - reconsider your approach
2. If justified (e.g., internal value, not user input), add comment:

   ```typescript
   // eslint-disable-next-line security/detect-non-literal-regexp -- tag is internal, not user input
   ```

3. Security Agent will review all suppressions

## Imports

**Use `node:` prefix for Node.js built-in modules:**

```typescript
// ✅ Correct - with node: prefix
import { createHmac } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';

// ❌ Wrong - without node: prefix
import { createHmac } from 'crypto';
import { readFile } from 'fs/promises';
import { join } from 'path';
```

**Why:** Modern Node.js convention. The `node:` prefix explicitly marks built-in
modules, avoiding confusion with npm packages of the same name.

**Use path aliases instead of relative paths:**

```typescript
// ✅ Correct - using path alias
import { ElasticsearchService } from '@backend/services/ElasticsearchService';

// ❌ Wrong - long relative path
import { ElasticsearchService } from '../../../../../src/node/backend/services/ElasticsearchService';
```

**Available aliases** (configured in tsconfig.json):

| Alias        | Path                 |
| ------------ | -------------------- |
| `@backend/`  | `src/node/backend/`  |
| `@frontend/` | `src/node/frontend/` |

## Best Practices

- **no-unnecessary-condition**: May have false positives with closures — disable
  with comment if needed
- **Test data at method start**: Define variables at beginning for readability,
  even if only used in closures
- **Extract Helper Methods**: For code duplication (3+ occurrences), create
  private helpers. For service patterns with repetitive try/catch, consider a
  generic wrapper like `withClient<T>(operation, fallback)`.

## IDE vs. Linter Parity

PHPStorm/WebStorm show warnings that ESLint doesn't catch by default. Use
`eslint-plugin-sonarjs` for IDE parity:

```javascript
// eslint.config.js
import sonarjsPlugin from 'eslint-plugin-sonarjs';

// In plugins:
sonarjs: sonarjsPlugin,

// In rules:
'sonarjs/prefer-immediate-return': 'warn',
```

**Mapping:**

| IDE Warning                | ESLint Rule                          |
| -------------------------- | ------------------------------------ |
| "Redundant local variable" | `sonarjs/prefer-immediate-return`    |
| "Can be simplified"        | Various `@typescript-eslint/*` rules |

## Test Mocks

**IDE "Unused" Warnings for Mock Properties:**

Mock classes often have properties/methods that appear unused in the test file
but are called by the System Under Test (SUT). Use JetBrains-specific
suppression comments:

```typescript
class MockClient {
  // noinspection JSUnusedGlobalSymbols - Used by ServiceUnderTest.operation()
  tasks = { waitForTask: mockFn };

  // noinspection JSUnusedGlobalSymbols - Used by ServiceUnderTest.connect()
  health(): unknown {
    return mockHealthFn();
  }
}
```

**Note:** JSDoc `@internal` does NOT suppress these warnings in JetBrains IDEs.
The `// noinspection` comment must be on the line directly before the member.

## Test Coverage

Target coverage thresholds for quality assurance:

| Metric              | Target   |
| ------------------- | -------- |
| **Overall**         | **70%**  |
| Security-Critical   | **95%+** |
| Core Infrastructure | **85%+** |
| Optional Services   | **60%+** |
| Dev Tools           | **50%+** |

**Component Classification:**

- **Security-Critical**: Auth, encryption, input validation, session management
- **Core Infrastructure**: Database layer, HTTP clients, error handling, logging
- **Optional Services**: Search, cache, queues, email (graceful degradation)
- **Dev Tools**: DevDashboard, debug utilities, test helpers

**Enforcement:**

- Overall target checked by CI (warning if below 70%)
- Security-critical components reviewed manually
- New code should not decrease existing coverage

## Documentation

- **TSDoc**: For public API methods
- Code comments always in English
