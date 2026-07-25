# 0005: Audit Logger Package Extraction

**Date:** 2026-02-13

**Status:** Accepted

**Context:** The boilerplate had tightly coupled audit logger implementations in
both PHP (`App\Infrastructure\Audit`) and Node (`Shared/Audit/`). Following the
successful extraction of `zappzarapp/security` (PHP) and
`@zappzarapp/browser-utils` (Node), audit logging was the next extraction
candidate.

**Decision:** Extract into two standalone packages with identical APIs:

- **PHP:** `zappzarapp/audit-logger` — Packagist package
- **Node:** `@zappzarapp/audit-logger` — npm package

Key design decisions:

1. **Injectable encryption strategy:** `EncryptionInterface` with
   `AppEncryption` (AES-256-GCM) default and `DatabaseEncryption` for existing
   DB-function users
2. **No superglobals:** All context (userId, IP, userAgent) passed as
   parameters; `HasAuditLogging` trait stays in boilerplate as convenience
   wrapper with `$_SESSION` access
3. **AuditLogEntry DTO:** Immutable DTO replaces individual parameters in
   `log()` method
4. **Node QueryExecutor interface:** Zero DB driver dependencies in package;
   boilerplate wraps its `ConnectionFactory`/`DatabaseConnection`
5. **Database dialect support:** Both PostgreSQL and MariaDB in both packages
6. **Configurable table name:** Constructor parameter (default `'audit_logs'`)
7. **File logging optional:** Constructor flag, DB error fallback always active
8. **Purge not implemented:** Documented for GDPR data minimization

**Consequences:**

- (+) Reusable across projects without boilerplate dependency
- (+) Full test coverage in isolated packages (100% line coverage)
- (+) Clean separation of concerns (no superglobals, DI-friendly)
- (+) Both DB dialects supported from day one
- (-) Boilerplate needs adapter code (HasAuditLogging trait, QueryExecutor
  wrapper)
- (-) Package linking infrastructure needed during development
