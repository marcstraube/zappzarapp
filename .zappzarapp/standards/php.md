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

### Security Rules (ekino/phpstan-banned-code)

PHPStan blocks dangerous functions automatically:

| Banned Function                                    | Reason                       |
| -------------------------------------------------- | ---------------------------- |
| `eval()`                                           | Code execution vulnerability |
| `exec()`, `shell_exec()`, `system()`, `passthru()` | Command injection            |
| `proc_open()`, `popen()`, `pcntl_exec()`           | Command injection            |
| `var_dump()`, `print_r()`, `dd()`, `dump()`        | Debug output in production   |

**If you need to use a banned function:**

1. This is a red flag - reconsider your approach
2. If absolutely necessary, add `@phpstan-ignore bannedCode.function` with
   explanation
3. Security Agent will review all suppressions

### Exception Handling

**Coding Standard (always apply):**

Document exceptions with `@throws` tags on **private methods** that can throw:

```php
/**
 * @throws JsonException When JSON encoding/decoding fails
 */
private function parseResponse(string $response): array
{
    return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
}
```

**Important: Do NOT add `@throws` to public methods that catch all exceptions:**

```php
// CORRECT - no @throws because method catches everything and returns null
public function search(string $index, array $query): ?array
{
    try {
        return $this->request('GET', "/$index/_search", $query);
    } catch (Throwable) {
        return null;  // Exception handled, not propagated
    }
}
```

Adding `@throws` to such methods would mislead API consumers into thinking
exceptions can be thrown when they actually cannot.

**Required `@throws` documentation (private methods only):**

| Exception                  | When to document                                       |
| -------------------------- | ------------------------------------------------------ |
| `JsonException`            | Methods using `json_encode/decode` with THROW_ON_ERROR |
| `RuntimeException`         | Methods that throw on initialization failures          |
| `InvalidArgumentException` | Methods validating input parameters                    |

**PHPStan Enforcement (optional, for stricter projects):**

Enable in `phpstan.neon` (rules are pre-configured but commented out):

```yaml
exceptions:
  check:
    missingCheckedExceptionInThrows: true # Require @throws for checked exceptions
    tooWideThrowType: true # Warn if @throws is broader than actual
  checkedExceptionClasses:
    - JsonException # Add other exceptions as needed
  implicitThrows: false # Require explicit handling
```

**Benefits of enforcement:**

- IDE parity: PhpStorm shows missing `@throws` warnings, PHPStan will too
- Explicit contracts: API consumers know what exceptions to expect
- Safer refactoring: Changing exception types triggers compile-time errors

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
