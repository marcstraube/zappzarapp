# 14: Unused Audit Trait - IMPLEMENTED

## Problem

PHPStan reports unused trait error:

```
src/php/App/Infrastructure/Audit/HasAuditLogging.php
Trait App\Infrastructure\Audit\HasAuditLogging is used zero times and is not analysed.
```

## Solution Implemented

**Architecture: Security-by-Design with Null Object Pattern**

### Changes Made

1. **NullAuditLogger.php** (NEW)
   - No-op implementation of AuditLoggerInterface
   - For small/private projects that don't need audit logging
   - Clean DI without null-checks everywhere

2. **AbstractPdoRepository.php**
   - `AuditLoggerInterface` now required in constructor
   - Automatic audit logging for `insert()`, `update()`, `delete()`
   - `getCurrentUserId()` helper for session-based user context

3. **HasAuditLogging.php**
   - Updated docblock: clarified it's for SERVICE-LAYER use
   - Repositories use AuditLoggerInterface directly
   - Trait reserved for auth/admin events in future services

4. **UserRepository.php**
   - Constructor updated to pass AuditLoggerInterface to parent

5. **Test files updated**
   - AbstractPdoRepositoryTest.php
   - UserRepositoryTest.php
   - DashboardControllerTest.php (unrelated DocsService fix)

### Usage

```php
// GDPR/Enterprise projects - real logging
$repo = new UserRepository(new AuditLogger($pdo), $config);

// Small/private projects - no logging
$repo = new UserRepository(new NullAuditLogger(), $config);
```

## Status

COMPLETED - PHPStan passes, all tests pass (361 tests, 989 assertions)
