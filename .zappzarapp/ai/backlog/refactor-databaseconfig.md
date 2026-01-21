# Refactor DatabaseConfig to Reduce Complexity

**Status:** Planned **Size:** Small **Scope:** refactor **Created:** 2026-01-21
**Planning:** Not required

## Context

PHPMD reports ExcessiveClassComplexity for DatabaseConfig.

## Goal

Refactor `DatabaseConfig` to reduce class complexity. Currently suppressed with
`@SuppressWarnings("PHPMD.ExcessiveClassComplexity")`.

## Potential Approaches

- Extract SSL configuration logic into separate `SslConfig` class
- Extract URL parsing into separate `DatabaseUrlParser` class
- Split environment variable handling into trait or helper

## Files

- `src/php/App/Infrastructure/DatabaseConfig.php`
