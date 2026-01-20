# Backup & Restore

GDPR-compliant backup strategy with encryption for all data services.

## Overview

| Service                       | Command                | Directory                | Format        |
| ----------------------------- | ---------------------- | ------------------------ | ------------- |
| Database (PostgreSQL/MariaDB) | `backup-db`            | `backups/db/`            | `.sql.gz.enc` |
| SeaweedFS (Object Storage)    | `backup-seaweedfs`     | `backups/seaweedfs/`     | `.tar.gz.enc` |
| RabbitMQ (Message Broker)     | `backup-rabbitmq`      | `backups/rabbitmq/`      | `.json.enc`   |
| Elasticsearch (Search)        | `backup-elasticsearch` | `backups/elasticsearch/` | `.tar.gz.enc` |

## Quick Start

```bash
# Backup all enabled services
make backup-all

# Individual service backups
make backup-db              # Database
make backup-seaweedfs       # SeaweedFS
make backup-rabbitmq        # RabbitMQ
make backup-elasticsearch   # Elasticsearch

# List backups
make backup-db-list
make backup-seaweedfs-list
make backup-rabbitmq-list
make backup-elasticsearch-list

# Restore (interactive)
make backup-db-restore
make backup-seaweedfs-restore
make backup-rabbitmq-restore
make backup-elasticsearch-restore
```

## Prerequisites

The `BACKUP_ENCRYPTION_KEY` is automatically generated during `make setup`. If
you need to generate it manually:

```bash
# Generate a new encryption key
openssl rand -base64 32

# Add to secrets/backup_encryption_key.txt
echo "your-generated-key-here" > secrets/backup_encryption_key.txt
```

---

## Database Backup (PostgreSQL/MariaDB)

### Create Backup

```bash
# Default: encrypted backup to ./backups/db/
make backup-db

# Direct script usage with options
./docker/scripts/backup-databases.sh --help
./docker/scripts/backup-databases.sh --output /custom/path --retention 7
./docker/scripts/backup-databases.sh --no-encrypt  # Not recommended!
```

### Backup Output

Backups are stored in `./backups/db/` with the naming convention:

- **Encrypted:** `{DB_TYPE}_{DB_NAME}_{TIMESTAMP}.sql.gz.enc`
- **Unencrypted:** `{DB_TYPE}_{DB_NAME}_{TIMESTAMP}.sql.gz`

Example: `postgres_app_20260114_143022.sql.gz.enc`

### Restore

```bash
# Interactive restore
make backup-db-restore

# Direct restore
./docker/scripts/restore-database.sh backups/db/postgres_app_20260114_143022.sql.gz.enc

# Skip confirmation (for automation)
./docker/scripts/restore-database.sh backups/db/postgres_app_20260114_143022.sql.gz.enc --yes
```

### Manual Decryption

```bash
# Decrypt and decompress to view SQL
openssl enc -aes-256-cbc -d -salt -pbkdf2 \
  -pass file:secrets/backup_encryption_key.txt \
  -in backups/db/postgres_app_20260114_143022.sql.gz.enc \
  | gunzip > backup.sql
```

---

## SeaweedFS Backup (Object Storage)

### Create Backup

```bash
# Default: encrypted backup to ./backups/seaweedfs/
make backup-seaweedfs

# Direct script usage
./docker/scripts/backup-seaweedfs.sh --help
./docker/scripts/backup-seaweedfs.sh --no-encrypt
```

### Backup Contents

SeaweedFS backups use `weed shell` to export all buckets and objects:

- **Format:** `seaweedfs_{TIMESTAMP}.tar.gz.enc`
- **Contents:** All buckets, objects, and metadata

Example: `seaweedfs_20260114_143022.tar.gz.enc`

### Restore

```bash
# Interactive restore
make backup-seaweedfs-restore

# Direct restore
./docker/scripts/restore-seaweedfs.sh backups/seaweedfs/seaweedfs_20260114_143022.tar.gz.enc
```

**Note:** Restore recreates buckets and objects. Existing objects with same keys
will be overwritten.

---

## RabbitMQ Backup (Message Broker)

### Create Backup

```bash
# Default: encrypted backup to ./backups/rabbitmq/
make backup-rabbitmq

# Direct script usage
./docker/scripts/backup-rabbitmq.sh --help
./docker/scripts/backup-rabbitmq.sh --no-encrypt
```

### Backup Contents

RabbitMQ backups export definitions via the Management API:

- **Format:** `rabbitmq_{TIMESTAMP}.json.enc`
- **Contents:** Users, vhosts, permissions, exchanges, queues, bindings,
  policies

Example: `rabbitmq_20260114_143022.json.enc`

**Important:** RabbitMQ backups do NOT include message data. For persistent
messages, drain queues before backup or use a separate archival strategy.

### Restore

```bash
# Interactive restore
make backup-rabbitmq-restore

# Direct restore
./docker/scripts/restore-rabbitmq.sh backups/rabbitmq/rabbitmq_20260114_143022.json.enc
```

**Note:** Restore imports definitions. Existing definitions are merged, not
replaced.

---

## Elasticsearch Backup (Search)

### Create Backup

```bash
# Default: encrypted backup to ./backups/elasticsearch/
make backup-elasticsearch

# Direct script usage
./docker/scripts/backup-elasticsearch.sh --help
./docker/scripts/backup-elasticsearch.sh --no-encrypt
```

### Backup Contents

Elasticsearch backups use the native Snapshot API:

- **Format:** `elasticsearch_{TIMESTAMP}.tar.gz.enc`
- **Contents:** All indices, mappings, and documents

Example: `elasticsearch_20260114_143022.tar.gz.enc`

### Restore

```bash
# Interactive restore
make backup-elasticsearch-restore

# Direct restore
./docker/scripts/restore-elasticsearch.sh backups/elasticsearch/elasticsearch_20260114_143022.tar.gz.enc
```

**Note:** Restore closes affected indices during the operation. Plan for brief
downtime.

---

## Retention Policy

Backups older than the configured retention period are automatically deleted.

**Configuration (in `.env`):**

```bash
# Default: 30 days
BACKUP_RETENTION_DAYS=30

# Keep all backups (no auto-deletion)
BACKUP_RETENTION_DAYS=0
```

**Override via script:**

```bash
./docker/scripts/backup-databases.sh --retention 7
./docker/scripts/backup-seaweedfs.sh --retention 7
```

---

## Automation (Cron)

### Daily Backup of All Services

```bash
# Edit crontab
crontab -e

# Add daily backup at 2:00 AM
0 2 * * * cd /path/to/project && make backup-all >> /var/log/backup.log 2>&1
```

### Individual Service Schedules

```bash
# Database: Daily at 2:00 AM
0 2 * * * cd /path/to/project && make backup-db >> /var/log/backup.log 2>&1

# SeaweedFS: Weekly on Sunday at 3:00 AM
0 3 * * 0 cd /path/to/project && make backup-seaweedfs >> /var/log/backup.log 2>&1

# RabbitMQ: Daily at 2:30 AM
30 2 * * * cd /path/to/project && make backup-rabbitmq >> /var/log/backup.log 2>&1

# Elasticsearch: Daily at 3:00 AM
0 3 * * * cd /path/to/project && make backup-elasticsearch >> /var/log/backup.log 2>&1
```

---

## Offsite Backup

After creating backups, sync to offsite storage:

```bash
# AWS S3
aws s3 sync ./backups/ s3://your-bucket/backups/ --sse

# Rsync to remote server
rsync -avz ./backups/ user@backup-server:/backups/

# Rclone (supports many providers)
rclone sync ./backups/ remote:backups/
```

---

## Security Considerations

### Encryption

- All backups are encrypted using **AES-256-CBC** with PBKDF2 key derivation
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

---

## Troubleshooting

### "BACKUP_ENCRYPTION_KEY is not set"

```bash
# Check if key exists
cat secrets/backup_encryption_key.txt

# If empty or missing, generate:
openssl rand -base64 32 > secrets/backup_encryption_key.txt
chmod 600 secrets/backup_encryption_key.txt
```

### "Container is not running"

```bash
# Start the required containers
make up

# Check container status
docker compose ps
```

### "Permission denied" on backup directory

```bash
# Recreate directories with correct permissions
make setup
```

### Restore fails with "bad decrypt"

- Wrong `BACKUP_ENCRYPTION_KEY`
- Backup file is corrupted
- Backup was created with different key

Try restoring from a different backup or check key history.

### SeaweedFS: "weed: command not found"

The SeaweedFS client (`weed`) runs inside the SeaweedFS container. Ensure the
container is running:

```bash
docker compose ps seaweedfs
make up  # If not running
```

### RabbitMQ: "Management API unavailable"

Ensure RabbitMQ Management plugin is enabled and the container is healthy:

```bash
docker compose ps rabbitmq
curl -u guest:guest http://localhost:15672/api/overview
```

### Elasticsearch: "Snapshot repository not found"

The backup script automatically creates the repository. If it fails:

```bash
# Check Elasticsearch health
curl http://localhost:9200/_cluster/health

# Manually create repository
curl -X PUT "localhost:9200/_snapshot/backup_repo" -H 'Content-Type: application/json' -d'
{
  "type": "fs",
  "settings": {
    "location": "/usr/share/elasticsearch/backup"
  }
}'
```

---

## Backup Logs

Backup and restore operations are logged to `storage/logs/backup.log`:

```json
{"timestamp":"2026-01-14T14:30:22+01:00","action":"backup_created","service":"postgres","file":"postgres_app_20260114_143022.sql.gz.enc","size":"2.3M","encrypted":true}
{"timestamp":"2026-01-14T14:31:00+01:00","action":"backup_created","service":"seaweedfs","file":"seaweedfs_20260114_143100.tar.gz.enc","size":"156M","encrypted":true}
{"timestamp":"2026-01-14T14:32:00+01:00","action":"backup_created","service":"rabbitmq","file":"rabbitmq_20260114_143200.json.enc","size":"4.2K","encrypted":true}
```
