# Database Backup & Restore

GDPR-compliant backup strategy with encryption for PostgreSQL and MariaDB.

## Quick Start

```bash
# Create encrypted backup
make backup

# List available backups
make backup-list

# Restore from backup (interactive)
make restore
```

## Prerequisites

The `BACKUP_ENCRYPTION_KEY` is automatically generated during `make setup`. If
you need to generate it manually:

```bash
# Generate a new encryption key
openssl rand -base64 32

# Add to .env
BACKUP_ENCRYPTION_KEY=your-generated-key-here
```

## Backup

### Create Backup

```bash
# Default: encrypted backup to ./backups/
make backup

# Direct script usage with options
./docker/scripts/backup-databases.sh --help
./docker/scripts/backup-databases.sh --output /custom/path --retention 7
./docker/scripts/backup-databases.sh --no-encrypt  # Not recommended for production!
```

### Backup Output

Backups are stored in `./backups/` with the naming convention:

- **Encrypted:** `{DB_TYPE}_{DB_NAME}_{TIMESTAMP}.sql.gz.enc`
- **Unencrypted:** `{DB_TYPE}_{DB_NAME}_{TIMESTAMP}.sql.gz`

Example: `postgres_app_20260112_143022.sql.gz.enc`

### Retention Policy

Backups older than the configured retention period are automatically deleted.

**Configuration (in `.env`):**

```bash
# Default: 30 days
BACKUP_RETENTION_DAYS=30

# Keep all backups (no auto-deletion)
BACKUP_RETENTION_DAYS=0
```

**Override via Make command:**

```bash
# One-time override: keep only 7 days
make backup RETENTION=7

# One-time override: keep all (ignore .env setting)
make backup RETENTION=0
```

**Direct script usage:**

```bash
./docker/scripts/backup-databases.sh --retention 7
```

## Restore

### Interactive Restore

```bash
make restore
# Lists available backups and prompts for selection
```

### Direct Restore

```bash
# From encrypted backup
./docker/scripts/restore-database.sh backups/postgres_app_20260112_143022.sql.gz.enc

# Skip confirmation (for scripts/automation)
./docker/scripts/restore-database.sh backups/postgres_app_20260112_143022.sql.gz.enc --yes
```

### Manual Decryption

If you need to inspect a backup without restoring:

```bash
# Decrypt and decompress to view SQL
openssl enc -aes-256-cbc -d -salt -pbkdf2 \
  -pass pass:"$BACKUP_ENCRYPTION_KEY" \
  -in backups/postgres_app_20260112_143022.sql.gz.enc \
  | gunzip > backup.sql

# View contents
less backup.sql
```

## Automation (Cron)

### Daily Backup

```bash
# Edit crontab
crontab -e

# Add daily backup at 2:00 AM
0 2 * * * cd /path/to/project && make backup >> /var/log/db-backup.log 2>&1
```

### Weekly Full Backup with 90-Day Retention

```bash
# Sunday at 3:00 AM, keep 90 days
0 3 * * 0 cd /path/to/project && ./docker/scripts/backup-databases.sh --retention 90 >> /var/log/db-backup.log 2>&1
```

## Offsite Backup (Optional)

After creating a backup, sync to offsite storage:

```bash
# AWS S3
aws s3 sync ./backups/ s3://your-bucket/backups/ --sse

# Rsync to remote server
rsync -avz ./backups/ user@backup-server:/backups/

# Rclone (supports many providers)
rclone sync ./backups/ remote:backups/
```

## Security Considerations

### Encryption

- Backups are encrypted using **AES-256-CBC** with PBKDF2 key derivation
- The `BACKUP_ENCRYPTION_KEY` should be:
  - At least 32 characters (256 bits)
  - Stored securely (not in version control)
  - Backed up separately from database backups
  - Rotated periodically in high-security environments

### Key Management

**CRITICAL:** If you lose `BACKUP_ENCRYPTION_KEY`, encrypted backups cannot be
restored!

Recommendations:

1. Store the key in a secure password manager
2. Keep a printed copy in a physical safe
3. Use a secrets management service (Vault, AWS Secrets Manager)
4. Document key rotation procedures

### GDPR Compliance

This backup strategy supports GDPR requirements:

| GDPR Article | Requirement                 | Implementation                              |
| ------------ | --------------------------- | ------------------------------------------- |
| Art. 5(1)(f) | Integrity & Confidentiality | AES-256 encryption                          |
| Art. 32      | Security of Processing      | Encrypted backups, access control           |
| Art. 33/34   | Breach Notification         | Backup logs for audit trail                 |
| Art. 17      | Right to Erasure            | Retention policy, can restore and anonymize |

## Troubleshooting

### "BACKUP_ENCRYPTION_KEY is not set"

```bash
# Check if key exists in .env
grep BACKUP_ENCRYPTION_KEY .env

# If empty, generate and add:
echo "BACKUP_ENCRYPTION_KEY=$(openssl rand -base64 32)" >> .env
```

### "Container is not running"

```bash
# Start the database container
make up

# Check container status
docker compose ps
```

### "Permission denied" on backup directory

```bash
# Create directory with correct permissions
mkdir -p backups
chmod 700 backups
```

### Restore fails with "bad decrypt"

- Wrong `BACKUP_ENCRYPTION_KEY`
- Backup file is corrupted
- Backup was created with different key

Try restoring from a different backup or check key history.

## Backup Logs

Backup and restore operations are logged to `storage/logs/backup.log`:

```json
{"timestamp":"2026-01-12T14:30:22+01:00","action":"backup_created","database":"postgres","db_name":"app","file":"postgres_app_20260112_143022.sql.gz.enc","size":"2.3M","encrypted":true}
{"timestamp":"2026-01-12T15:00:00+01:00","action":"backup_restored","database":"postgres","db_name":"app","file":"postgres_app_20260112_143022.sql.gz.enc","encrypted":true}
```

## Database Migrations

To set up GDPR-compliant database features (encryption helpers, audit logs):

```bash
# Run all migrations for configured DB_TYPE
make db-migrations
```

This applies:

- `000_encryption_helpers.sql` - pgcrypto/encrypt_text functions
- `001_audit_logs.sql` - Audit logging table with tamper protection

See `ENCRYPTION.md` and `AUDIT-LOGGING.md` for details.
