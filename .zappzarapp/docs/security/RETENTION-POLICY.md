# Data Retention Policy Guide

> GDPR Art. 5(1)(e) & Art. 17 Compliance: Storage Limitation & Right to Erasure

---

## Why Retention Policies?

**GDPR requires data retention policies for:**

- **Art. 5(1)(e)**: Storage limitation - Data should be kept only as long as
  necessary
- **Art. 17**: Right to erasure ("Right to be forgotten")
- **Art. 30**: Documentation of retention periods in Records of Processing
  Activities

**Security benefits:**

- Reduced attack surface (less data = less risk)
- Faster database queries (smaller tables)
- Lower storage costs
- Simplified compliance audits

---

## Recommended Retention Periods

| Data Type             | Retention Period        | Rationale                                |
| --------------------- | ----------------------- | ---------------------------------------- |
| Audit logs            | 2 years (730 days)      | Legal compliance, incident investigation |
| Session data          | 7-30 days               | Active sessions only                     |
| Access logs           | 90 days                 | Security monitoring                      |
| Failed login attempts | 90 days                 | Security analysis                        |
| Deleted user data     | Immediate anonymization | GDPR Art. 17                             |
| Backups               | 30-90 days              | Disaster recovery                        |
| Financial records     | 7-10 years              | Tax/legal requirements                   |

**Note:** Adjust based on your jurisdiction and legal requirements!

---

## Available Functions

### PostgreSQL

```sql
-- Delete old logs from any table with 'timestamp' column
SELECT delete_old_logs('audit_logs', 730);  -- Delete logs older than 2 years
SELECT delete_old_logs('sessions', 30);      -- Delete sessions older than 30 days

-- Anonymize user (GDPR Art. 17 - Right to be forgotten)
SELECT anonymize_user(123);                          -- Basic anonymization
SELECT anonymize_user(123, 'GDPR-REQ-2024-001');    -- With reference number

-- Check retention status
SELECT * FROM get_retention_status();
```

### MariaDB

```sql
-- Delete old logs
CALL delete_old_logs('audit_logs', 730, @deleted);
SELECT @deleted AS deleted_rows;

CALL delete_old_logs('sessions', 30, @deleted);
SELECT @deleted AS deleted_rows;

-- Anonymize user
CALL anonymize_user(123, 'GDPR-REQ-2024-001', @success);
SELECT @success;

-- Check retention status
CALL get_retention_status();
```

---

## Implementation Guide

### 1. Apply the Migration

```bash
# PostgreSQL
make db-cli DB=postgres
\i /docker-entrypoint-initdb.d/migrations/002_retention_policies.sql

# MariaDB
make db-cli DB=mariadb
SOURCE /docker-entrypoint-initdb.d/migrations/002_retention_policies.sql;
```

### 2. Customize `anonymize_user()`

The `anonymize_user()` function is a **template**. You must customize it for
your tables:

```sql
-- Example: Uncomment and modify in the migration file
UPDATE users SET
    email = v_anonymized_email,
    first_name = 'DELETED',
    last_name = 'USER',
    phone = NULL,
    address = NULL,
    date_of_birth = NULL
WHERE id = p_user_id;
```

### 3. Schedule Cleanup (Admin Task)

Scheduling is **not part of the boilerplate** - it's a Dev/Admin task. Choose
one:

#### Option A: System Cron with make db-cleanup (Recommended)

The simplest option - use the provided Makefile target with system cron:

```bash
# Manual execution (default: 730 days retention)
make db-cleanup

# Custom retention period
RETENTION_DAYS=365 make db-cleanup
```

**Schedule with cron:**

```bash
# /etc/cron.d/database-cleanup
# Run daily at 3 AM
0 3 * * * root cd /path/to/project && make db-cleanup >> /var/log/db-cleanup.log 2>&1

# Or with custom retention
0 3 * * * root cd /path/to/project && RETENTION_DAYS=365 make db-cleanup >> /var/log/db-cleanup.log 2>&1
```

**Or call Docker directly:**

```bash
# PostgreSQL
0 3 * * * root docker exec postgres psql -U app -d app -c "SELECT delete_old_logs('audit_logs', 730);"

# MariaDB
0 3 * * * root docker exec mariadb mariadb -u app -pSECRET app -e "CALL delete_old_logs('audit_logs', 730, @d); SELECT @d;"
```

#### Option B: Systemd Timer

```ini
# /etc/systemd/system/db-cleanup.timer
[Unit]
Description=Daily database cleanup

[Timer]
OnCalendar=*-*-* 03:00:00
Persistent=true

[Install]
WantedBy=timers.target
```

```ini
# /etc/systemd/system/db-cleanup.service
[Unit]
Description=Database cleanup service

[Service]
Type=oneshot
WorkingDirectory=/path/to/project
ExecStart=/usr/bin/make db-cleanup
User=root
```

```bash
# Enable timer
sudo systemctl enable --now db-cleanup.timer
```

#### Option C: pg_cron (PostgreSQL only)

pg_cron is **pre-installed** in the custom PostgreSQL image and
**pre-configured** in compose files. You only need to create the extension:

```sql
-- pg_cron is already loaded via shared_preload_libraries
-- Just create the extension in your database:
CREATE EXTENSION IF NOT EXISTS pg_cron;

-- Schedule daily cleanup at 3 AM
SELECT cron.schedule('cleanup-audit-logs', '0 3 * * *',
    $$SELECT delete_old_logs('audit_logs', 730)$$);
SELECT cron.schedule('cleanup-sessions', '0 3 * * *',
    $$SELECT delete_old_logs('sessions', 30)$$);

-- List scheduled jobs
SELECT * FROM cron.job;

-- Remove a job
SELECT cron.unschedule('cleanup-audit-logs');
```

#### Option D: MariaDB Event Scheduler

```sql
-- Enable event scheduler (requires SUPER privilege)
SET GLOBAL event_scheduler = ON;

-- Schedule daily cleanup at 3 AM
CREATE EVENT IF NOT EXISTS cleanup_old_audit_logs
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_DATE + INTERVAL 3 HOUR
DO
  CALL delete_old_logs('audit_logs', 730, @deleted);

-- List events
SHOW EVENTS;

-- Remove an event
DROP EVENT cleanup_old_audit_logs;
```

#### Option E: Kubernetes CronJob

```yaml
# db-cleanup-cronjob.yaml
apiVersion: batch/v1
kind: CronJob
metadata:
  name: db-cleanup
spec:
  schedule: '0 3 * * *' # Daily at 3 AM
  jobTemplate:
    spec:
      template:
        spec:
          containers:
            - name: cleanup
              image: postgres:16-alpine
              command:
                - psql
                - -h
                - postgres-service
                - -U
                - app
                - -d
                - app
                - -c
                - "SELECT delete_old_logs('audit_logs', 730);"
              env:
                - name: PGPASSWORD
                  valueFrom:
                    secretKeyRef:
                      name: db-secrets
                      key: password
          restartPolicy: OnFailure
```

---

## GDPR Art. 17: Right to Erasure Workflow

When a user requests data deletion ("Right to be forgotten"):

### 1. Verify the Request

```php
// Verify user identity before processing
if (!$this->verifyUserIdentity($userId, $request)) {
    throw new UnauthorizedException('Identity verification failed');
}
```

### 2. Check for Legal Retention Requirements

```php
// Some data must be retained for legal reasons
$hasOpenOrders = $orderRepository->hasOpenOrders($userId);
$hasUnpaidInvoices = $invoiceRepository->hasUnpaidInvoices($userId);

if ($hasOpenOrders || $hasUnpaidInvoices) {
    // Cannot fully delete - inform user
    throw new RetentionException('Cannot delete: pending orders/invoices');
}
```

### 3. Anonymize User Data

```php
// PHP Example
$reference = 'GDPR-REQ-' . date('Y') . '-' . uniqid();

// PostgreSQL
$pdo->query("SELECT anonymize_user($userId, '$reference')");

// MariaDB
$stmt = $pdo->prepare("CALL anonymize_user(?, ?, @success)");
$stmt->execute([$userId, $reference]);
$result = $pdo->query("SELECT @success")->fetch();
```

### 4. Document the Request

```php
// Log for GDPR compliance proof
$this->auditLogger->log(
    userId: null,  // System action
    action: 'gdpr.erasure_request',
    entityType: 'user',
    entityId: (string) $userId,
    data: [
        'reference' => $reference,
        'requested_at' => date('c'),
        'completed_at' => date('c'),
        'requester_ip' => $request->getClientIp(),
    ]
);
```

### 5. Notify User

```php
// Send confirmation within 30 days (GDPR requirement)
$this->mailer->send(
    to: $userEmail,
    subject: 'Your data has been deleted',
    body: "Reference: $reference"
);
```

---

## Best Practices

### 1. Audit Log Retention

Audit logs are **append-only and immutable** (protected by triggers). The
`delete_old_logs()` function temporarily disables the trigger for cleanup.

**Recommendation:** Keep audit logs for at least 2 years:

- Legal hold requirements
- Incident investigation needs
- Compliance audits

### 2. Anonymization vs. Deletion

**Prefer anonymization over deletion:**

- Maintains referential integrity
- Preserves statistical data
- Provides audit trail
- Complies with Art. 17 while preserving business records

```sql
-- Anonymized user keeps ID but no PII
-- Before: { id: 123, email: "john@example.com", name: "John Doe" }
-- After:  { id: 123, email: "deleted_123@anonymized.local", name: "Deleted User 123" }
```

### 3. Backup Considerations

After anonymizing a user, old backups still contain their data:

- Document backup retention in privacy policy
- Consider backup encryption (already implemented, see BACKUP.md)
- Set realistic backup retention (30-90 days)

### 4. Third-Party Data Processors

When anonymizing users, also:

- Notify third-party processors (payment providers, analytics, etc.)
- Request deletion from external systems
- Document the chain of deletion

---

## Monitoring

### Check for Old Data

```sql
-- PostgreSQL
SELECT * FROM get_retention_status();

-- MariaDB
CALL get_retention_status();
```

Example output:

```text
 table_name  | total_rows | oldest_record       | rows_older_than_90_days | rows_older_than_365_days
-------------+------------+---------------------+-------------------------+--------------------------
 audit_logs  | 1234567    | 2023-01-15 10:23:45 | 456789                  | 123456
```

### Add Custom Tables

Extend `get_retention_status()` in the migration files to monitor additional
tables:

```sql
-- PostgreSQL: Add to get_retention_status() function
IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'sessions') THEN
    RETURN QUERY
    SELECT 'sessions'::TEXT, COUNT(*)::BIGINT, MIN(timestamp), MAX(timestamp),
           COUNT(*) FILTER (WHERE timestamp < NOW() - INTERVAL '90 days')::BIGINT,
           COUNT(*) FILTER (WHERE timestamp < NOW() - INTERVAL '365 days')::BIGINT
    FROM sessions;
END IF;
```

---

## Migration Files

- **PostgreSQL:** `migrations/postgresql/002_retention_policies.sql`
- **MariaDB:** `migrations/mariadb/002_retention_policies.sql`

---

## Related Documentation

- [Audit Logging Guide](./AUDIT-LOGGING.md) - When and how to log
- [Encryption Guide](./ENCRYPTION.md) - Column-level encryption
- [Backup Guide](./BACKUP.md) - Encrypted backups with retention
