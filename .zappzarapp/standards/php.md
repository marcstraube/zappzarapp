# PHP Standards

## Critical (Agent MUST Check)

Before writing PHP code, verify these rules:

1. **Suppressions**: Only use suppressions listed in "Allowed" table below
2. **Undocumented warnings**: Do NOT suppress — ask user with options
3. **Named arguments**: Always for boolean parameters (`useTls: true`)
4. **Readonly classes**: Use when all properties are readonly (PHP 8.2+)

### Handling Undocumented Warnings

If you encounter a warning/inspection not in the Allowed table:

```text
Undocumented Warning: [WarningName]
File: path/to/file.php:42
Message: [IDE/Tool message]

Options:
1. Add to standards (Recommended if legitimate pattern)
   → Suppression: @noinspection [Name]
   → When allowed: [Your description]

2. Fix the code instead
   → [Suggested fix]

3. Skip for now (leaves warning)
```

**Never suppress undocumented warnings without user approval.**

---

## Suppressions - Allowed

| Annotation                                                      | When Allowed                                                                                     |
| --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| `@SuppressWarnings("PHPMD.BooleanArgumentFlag")`                | When named arguments make intent clear                                                           |
| `@SuppressWarnings("PHPMD.ExcessiveClassComplexity")`           | Temporary, with backlog item for refactoring                                                     |
| `@SuppressWarnings("PHPMD.UnusedPrivateMethod")`                | Only when methods are called dynamically (`$this->$method()`)                                    |
| `@noinspection PhpUnused`                                       | For public API methods used externally                                                           |
| `@noinspection PhpPublicPropertyModifierCanBeOmittedInspection` | PHP 8.4 asymmetric visibility (`public private(set)`) — at class level                           |
| `@noinspection PhpMixedReturnTypeCanBeReducedInspection`        | When returning `resource\|false` (resource is not a native PHP type)                             |
| `@noinspection PhpSameParameterValueInspection`                 | When parameter intentionally always has same value (e.g., Config classes, interface consistency) |

## Suppressions - Forbidden

| Annotation                                 | Reason                                                                       |
| ------------------------------------------ | ---------------------------------------------------------------------------- |
| `@phpstan-ignore-line` without comment     | Always document the reason                                                   |
| Blanket `@SuppressWarnings` at class level | Too broad, use per-method (exception: documented reasons like dynamic calls) |

## Best Practices

- **Readonly Classes**: PHP 8.2+ — mark classes as `readonly` when all
  properties are readonly (removes redundant `readonly` keywords)
- **Asymmetric Visibility**: PHP 8.4 — `public private(set)` for properties that
  are publicly readable but only privately writable (see Suppressions for IDE)
- **Named Arguments**: Use for boolean parameters (`useTls: true` instead of
  `true`)
- **FilesystemIterator::SKIP_DOTS**: Use directly, not via child class
- **RandomException**: In PHP 8.2+ `random_bytes()` can throw — catch in tests
  or document with `@throws`
- **Extract Helper Methods**: For code duplication, create private helpers
  (reduces maintenance and improves readability)

## PHPStan

- Level: `max` (9)
- Baseline: `phpstan-baseline.neon` for legacy issues
- Never add new errors to baseline without justification

## PHPMD

- Config: `phpmd.xml.dist`
- CyclomaticComplexity: Max 10 (if exceeded: split method)
- NPathComplexity: Max 200

## IDE vs Linter Warnings

**IDE-specific inspections** (PhpStorm/IntelliJ) are separate from linter rules
(PHPStan/PHPMD). The `@noinspection` annotations above suppress IDE warnings
only.

- **Linter warnings** (PHPStan, PHPMD): Must be fixed or added to baseline with
  justification
- **IDE warnings** (`@noinspection`): Can be suppressed when the pattern is
  intentional (e.g., Config classes with fixed defaults)

When in doubt: If `make check` passes, the code is compliant. IDE warnings are
advisory.

## Documentation

- **PHPDoc**: Only when type hint is insufficient (`@param` for arrays with
  specific structure)
- Code comments always in English
