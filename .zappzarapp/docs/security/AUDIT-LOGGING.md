# Audit Logging Guide

> GDPR Art. 30 & 32 Compliance: Records of Processing Activities

---

## Why Audit Logging?

**GDPR requires audit logging for:**

- **Art. 15**: Right of access - Users can request "who accessed my data?"
- **Art. 17**: Right to erasure - Audit trail of data deletion
- **Art. 30**: Records of processing activities
- **Art. 32**: Security measures - Logging access to personal data
- **Art. 33**: Breach notification - What data was accessed during breach?

**Security benefits:**

- Detect unauthorized access
- Investigate security incidents
- Monitor administrative actions
- Compliance audits

---

## When to Log

### ✅ ALWAYS Log These Actions

**Personal Data Access:**

- Viewing user profiles, orders, invoices
- Exporting personal data (GDPR data portability)
- Searching/filtering users

**Personal Data Modifications:**

- Creating new users
- Updating user information
- Deleting users (right to erasure)
- Anonymizing users

**Authentication:**

- Successful login
- Failed login attempts (security monitoring)
- Logout
- Password changes/resets
- Two-factor authentication events

**Administrative Actions:**

- Role assignments/changes
- Permission grants/revokes
- System configuration changes

---

### ⚠️ DON'T Log These

**Public Data:**

- Viewing public pages
- Accessing non-personal data

**High-Frequency Operations:**

- Page views (use analytics instead)
- API health checks
- Static asset requests

**System Operations:**

- Database migrations
- Cache clearing
- Background jobs (unless they process personal data)

---

## Quick Start

### 1. Run Migration

Apply all pending migrations (for the configured database — `002_audit_logs.sql`
among them):

```bash
make db-migrations
```

This creates the `audit_logs` table (append-only, encrypted, tamper-proof).

---

### 2. PHP Usage

#### Option A: Dependency Injection (Recommended)

```php
<?php
use Zappzarapp\AuditLogger\AuditLogger;
use Zappzarapp\AuditLogger\AuditLoggerInterface;

// In your dependency injection container
$auditLogger = new AuditLogger(
    pdo: $pdo,
    encryptionKey: $_ENV['ENCRYPTION_KEY'],
    logFilePath: __DIR__ . '/storage/logs/audit.log'
);

// Inject into services
$userService = new UserService($auditLogger);
```

#### Option B: Using Trait (Convenience)

```php
<?php
use App\Infrastructure\Audit\HasAuditLogging;
use Zappzarapp\AuditLogger\AuditLoggerInterface;

class UserService
{
    use HasAuditLogging;

    private AuditLoggerInterface $auditLogger;

    public function __construct(AuditLoggerInterface $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    public function updateUser(int $userId, array $data): void
    {
        // Update user in database...

        // Log the action
        $this->auditLog(
            action: 'user.update',
            entityType: 'user',
            entityId: $userId,
            data: ['changed_fields' => array_keys($data)]
        );
    }

    public function deleteUser(int $userId): void
    {
        // Delete user from database...

        // IMPORTANT: Log deletion for GDPR compliance
        $this->auditLog(
            action: 'user.delete',
            entityType: 'user',
            entityId: $userId,
            data: ['reason' => 'User requested account deletion (GDPR Art. 17)']
        );
    }
}
```

---

### 3. Node.js / TypeScript Usage

```typescript
import { Pool } from 'pg';
import { AuditLogger } from './services/AuditLogger';

const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
});

const auditLogger = new AuditLogger(
  pool,
  process.env.ENCRYPTION_KEY!,
  'storage/logs/audit.log'
);

// Log user data access
app.get('/api/users/:id', async (req, res) => {
  const user = await getUserById(req.params.id);

  // Log the access
  await auditLogger.log({
    action: 'user.view',
    entityType: 'user',
    entityId: req.params.id,
    userId: req.user?.id,
    ipAddress: req.ip,
    userAgent: req.headers['user-agent'],
  });

  res.json(user);
});

// Log user data modification
app.put('/api/users/:id', async (req, res) => {
  const updatedUser = await updateUser(req.params.id, req.body);

  // Log the modification
  await auditLogger.log({
    action: 'user.update',
    entityType: 'user',
    entityId: req.params.id,
    userId: req.user.id,
    data: { changed_fields: Object.keys(req.body) },
    ipAddress: req.ip,
    userAgent: req.headers['user-agent'],
  });

  res.json(updatedUser);
});

// Log authentication
app.post('/api/login', async (req, res) => {
  const user = await authenticate(req.body.email, req.body.password);

  if (user) {
    await auditLogger.logAuth(
      'login.success',
      user.id,
      {},
      req.ip,
      req.headers['user-agent']
    );
    res.json({ success: true, user });
  } else {
    await auditLogger.logAuth(
      'login.failed',
      null,
      { email: req.body.email },
      req.ip,
      req.headers['user-agent']
    );
    res.status(401).json({ error: 'Invalid credentials' });
  }
});
```

---

## Action Naming Convention

Use a consistent naming scheme for actions:

### Format: `{entity}.{action}`

**User Actions:**

- `user.view` - Viewing user profile
- `user.create` - Creating new user
- `user.update` - Updating user information
- `user.delete` - Deleting user (GDPR right to erasure)
- `user.export` - Exporting user data (GDPR data portability)
- `user.anonymize` - Anonymizing user (GDPR compliance)

**Authentication:**

- `login.success` - Successful login
- `login.failed` - Failed login attempt
- `logout` - User logged out
- `password.change` - Password changed
- `password.reset` - Password reset
- `2fa.enabled` - Two-factor authentication enabled
- `2fa.disabled` - Two-factor authentication disabled

**Administrative:**

- `role.granted` - Role assigned to user
- `role.revoked` - Role removed from user
- `permission.granted` - Permission granted
- `permission.revoked` - Permission revoked

---

## Querying Audit Logs

### PHP

```php
<?php
// Get audit logs for a specific user
$logs = $auditLogger->getLogsForUser(userId: 123, limit: 50);

foreach ($logs as $log) {
    echo "{$log['timestamp']}: {$log['action']} by user {$log['user_id']}\n";
    echo "Data: {$log['data_decrypted']}\n\n";
}

// Get audit logs for a specific entity (e.g., user 456)
$logs = $auditLogger->getLogsForEntity(
    entityType: 'user',
    entityId: 456,
    limit: 100
);
```

---

### Node.js

```typescript
// Get audit logs for a specific user
const logs = await auditLogger.getLogsForUser(123, 50);

for (const log of logs) {
  console.log(`${log.timestamp}: ${log.action} by user ${log.user_id}`);
  console.log(`Data: ${log.data_decrypted}\n`);
}

// Get audit logs for a specific entity (e.g., user 456)
const logs = await auditLogger.getLogsForEntity('user', 456, 100);
```

---

### SQL (Direct Query)

**PostgreSQL:**

```sql
-- Get all actions on user 456
SELECT
    id,
    timestamp,
    user_id,
    ip_address,
    action,
    decrypt_text(data, 'YOUR_ENCRYPTION_KEY') AS data_decrypted
FROM audit_logs
WHERE entity_type = 'user' AND entity_id = '456'
ORDER BY timestamp DESC
LIMIT 50;

-- Failed login attempts in last 24 hours (security monitoring)
SELECT
    timestamp,
    ip_address,
    decrypt_text(data, 'YOUR_ENCRYPTION_KEY') AS data_decrypted
FROM audit_logs
WHERE action = 'login.failed'
  AND timestamp > NOW() - INTERVAL '24 hours'
ORDER BY timestamp DESC;

-- All admin actions
SELECT
    timestamp,
    user_id,
    action,
    entity_type,
    entity_id,
    decrypt_text(data, 'YOUR_ENCRYPTION_KEY') AS data_decrypted
FROM audit_logs
WHERE action LIKE 'role.%' OR action LIKE 'permission.%'
ORDER BY timestamp DESC;
```

**MariaDB:**

```sql
-- Same queries as PostgreSQL, but use:
-- - NOW() - INTERVAL 24 HOUR instead of NOW() - INTERVAL '24 hours'
```

---

## GDPR Subject Access Request (SAR)

When a user requests their data (GDPR Art. 15), include audit logs:

**PHP:**

```php
<?php
public function generateDataExport(int $userId): array
{
    // Get user data
    $user = $this->getUserById($userId);

    // Get audit logs (who accessed their data)
    $auditLogs = $this->auditLogger->getLogsForUser($userId);

    // Log the export request
    $this->auditLog(
        action: 'user.export',
        entityType: 'user',
        entityId: $userId,
        data: ['reason' => 'GDPR Subject Access Request (Art. 15)']
    );

    return [
        'user' => $user,
        'audit_logs' => $auditLogs,
    ];
}
```

---

## Security Features

### 1. Append-Only (Immutable)

Audit logs **cannot be modified or deleted** after creation.

Triggers prevent `UPDATE` and `DELETE` operations:

```sql
-- This will FAIL
UPDATE audit_logs SET action = 'something.else' WHERE id = 123;
-- Error: Audit logs are immutable

-- This will also FAIL
DELETE FROM audit_logs WHERE id = 123;
-- Error: Audit logs are immutable
```

Only way to remove logs: Manual database operation by DBA (for compliance with
retention policies).

---

### 2. Encrypted Data

The `data` column is encrypted using `encrypt_text()` function.

**Contains:**

- User agent
- Changed fields
- Additional context
- Sensitive information

**Decrypted only when querying:**

```sql
SELECT decrypt_text(data, 'ENCRYPTION_KEY') AS data_decrypted
FROM audit_logs;
```

---

### 3. Tamper-Proof Checksum

Each log entry has a SHA-256 checksum:

```text
checksum = SHA256(timestamp + action + entity_type + entity_id + data)
```

**Verify integrity:**

```sql
-- PostgreSQL
SELECT
    id,
    action,
    checksum,
    encode(digest(timestamp::text || action || entity_type || entity_id || decrypt_text(data, 'KEY'), 'sha256'), 'hex') AS checksum_recalculated
FROM audit_logs
WHERE checksum != encode(digest(timestamp::text || action || entity_type || entity_id || decrypt_text(data, 'KEY'), 'sha256'), 'hex');
-- If any rows returned, logs have been tampered with!
```

---

### 4. IP Address Tracking

Logs include client IP address (handles proxies):

- `X-Forwarded-For` header
- `X-Real-IP` header
- `REMOTE_ADDR` fallback

Useful for:

- Security monitoring (detect unauthorized access)
- Breach notification (which IPs accessed data?)
- Geolocation analysis

---

### 5. Dual Logging (Database + File)

Logs are written to:

1. **Database** (`audit_logs` table) - Queryable, indexed
2. **File** (`storage/logs/audit.log`) - Redundancy, backup

**File format:** JSON lines (one log entry per line)

```json
{
  "timestamp": "2025-01-09T21:00:00.000Z",
  "user_id": 123,
  "ip_address": "192.168.1.1",
  "action": "user.update",
  "entity_type": "user",
  "entity_id": "456",
  "data": { "changed_fields": ["email"], "user_agent": "Mozilla/5.0" }
}
```

---

## Log Retention

**Recommended retention:** 1-2 years

**Legal requirements vary by jurisdiction:**

- GDPR: No specific retention period, but "no longer than necessary"
- Some industries: 5-7 years (e.g., financial services)

**See Phase 2.2** for automatic retention policy implementation:

- `delete_old_logs()` function (PostgreSQL)
- Scheduled cleanup (pg_cron, cron, etc.)

**Manual cleanup (for testing):**

```sql
-- Delete logs older than 90 days (PostgreSQL)
-- NOTE: This bypasses the immutability trigger!
DELETE FROM audit_logs WHERE timestamp < NOW() - INTERVAL '90 days';

-- MariaDB
DELETE FROM audit_logs WHERE timestamp < NOW() - INTERVAL 90 DAY;
```

**Production cleanup:** Should be done by DBA with proper authorization.

---

## Performance Considerations

### Indexes

The following indexes are created automatically:

- `idx_audit_logs_timestamp` - Recent logs
- `idx_audit_logs_user_id` - Logs by user
- `idx_audit_logs_entity` - Logs by entity
- `idx_audit_logs_action` - Logs by action type
- `idx_audit_logs_failed_login` - Security monitoring

### Partitioning (Large Scale)

For high-volume applications (millions of logs), consider **table
partitioning**:

**PostgreSQL:**

```sql
-- Partition by month
CREATE TABLE audit_logs_2025_01 PARTITION OF audit_logs
    FOR VALUES FROM ('2025-01-01') TO ('2025-02-01');
```

**MariaDB:**

```sql
-- Partition by range (month)
ALTER TABLE audit_logs
PARTITION BY RANGE (TO_DAYS(timestamp)) (
    PARTITION p202501 VALUES LESS THAN (TO_DAYS('2025-02-01')),
    PARTITION p202502 VALUES LESS THAN (TO_DAYS('2025-03-01'))
);
```

---

## Monitoring & Alerts

### Failed Login Attempts

Monitor for brute-force attacks:

```sql
-- Count failed login attempts by IP (last hour)
SELECT
    ip_address,
    COUNT(*) as failed_attempts
FROM audit_logs
WHERE action = 'login.failed'
  AND timestamp > NOW() - INTERVAL '1 hour'
GROUP BY ip_address
HAVING COUNT(*) > 5
ORDER BY failed_attempts DESC;
```

**Action:** Block IP or enable CAPTCHA after N failed attempts.

---

### Unusual Access Patterns

Detect suspicious activity:

```sql
-- Users accessing many records in short time (data scraping?)
SELECT
    user_id,
    COUNT(*) as access_count,
    COUNT(DISTINCT entity_id) as unique_entities
FROM audit_logs
WHERE action = 'user.view'
  AND timestamp > NOW() - INTERVAL '10 minutes'
GROUP BY user_id
HAVING COUNT(*) > 100
ORDER BY access_count DESC;
```

---

## Troubleshooting

### Logs Not Written to Database

**Symptom:** `writeLog()` throws exception

**Possible causes:**

1. `audit_logs` table doesn't exist → Run migration
2. `encrypt_text()` function missing → Run `000_encryption_helpers.sql`
3. Wrong `ENCRYPTION_KEY` → Check `.env`
4. Database connection failed → Check database status

**Fallback:** Logs are written to file (`storage/logs/audit.log`) even if
database write fails.

---

### Cannot Query Encrypted Data

**Symptom:** `decrypt_text()` returns `NULL`

**Causes:**

- Wrong encryption key
- Encrypted data is corrupted

**Solution:**

```sql
-- Check if encryption key is correct
SELECT decrypt_text(data, 'CORRECT_KEY') FROM audit_logs LIMIT 1;
-- Should return JSON string, not NULL
```

---

### File Logging Not Working

**Symptom:** `storage/logs/audit.log` not created

**Causes:**

- Directory doesn't exist
- Permission denied

**Solution:**

```bash
mkdir -p storage/logs
chmod 755 storage/logs
```

---

## Best Practices

### 1. Always Log User ID

```php
// ✅ GOOD - Always include user ID
$this->auditLog(
    action: 'user.view',
    entityType: 'user',
    entityId: 123,
    userId: $_SESSION['user_id']  // ← Always include
);

// ⚠️ BAD - Missing user ID
$this->auditLog(
    action: 'user.view',
    entityType: 'user',
    entityId: 123
    // Missing userId!
);
```

---

### 2. Log Context, Not Sensitive Data

```php
// ✅ GOOD - Log what changed, not the values
$this->auditLog(
    action: 'user.update',
    entityType: 'user',
    entityId: 123,
    data: ['changed_fields' => ['email', 'phone']]  // ← Context only
);

// ⚠️ BAD - Logging sensitive data
$this->auditLog(
    action: 'user.update',
    entityType: 'user',
    entityId: 123,
    data: ['email' => 'user@example.com']  // ← Don't log actual values
);
```

**Exception:** For GDPR data deletion, log reason:

```php
$this->auditLog(
    action: 'user.delete',
    entityType: 'user',
    entityId: 123,
    data: ['reason' => 'User requested account deletion (GDPR Art. 17)']
);
```

---

### 3. Use Consistent Action Names

Follow the `{entity}.{action}` convention:

- `user.view`, `user.update`, `user.delete`
- `login.success`, `login.failed`
- `role.granted`, `role.revoked`

---

### 4. Log Administrative Actions

```php
// Admin grants role to user
$this->auditLogAdmin(
    action: 'role.granted',
    adminUserId: $_SESSION['admin_id'],
    entityType: 'user',
    entityId: 456,
    data: ['role' => 'moderator']
);
```

---

## Further Reading

- [GDPR Art. 30 - Records of Processing](https://gdpr-info.eu/art-30-gdpr/)
- [GDPR Art. 32 - Security Measures](https://gdpr-info.eu/art-32-gdpr/)
- [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html)
