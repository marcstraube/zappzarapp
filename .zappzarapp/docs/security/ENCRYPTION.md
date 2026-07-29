# Database Encryption Guide

> GDPR Art. 32 Compliance: Encryption of Personal Data

---

## ⚠️ IMPORTANT: When Do You Need This?

**Most applications DON'T need column-level encryption!**

The following security measures are **already implemented** and sufficient for
most use cases:

- ✅ **TLS/SSL** for data in transit (HTTPS, database connections)
- ✅ **OS-level disk encryption** (encrypt storage volumes)
- ✅ **Access control** and authentication
- ✅ **Audit logging** (see Phase 1.3)

**Only use column-level encryption for:**

- ❗ **GDPR Art. 9 Special Categories** of personal data:
  - Health data
  - Biometric data (fingerprints, facial recognition)
  - Genetic data
  - Financial account numbers (credit cards, bank accounts)
  - Social security numbers / national IDs
  - Government-issued ID numbers

**Do NOT use for:**

- ⚠️ Regular personal data (name, email, address, phone number)
- ⚠️ Data that needs to be searched or indexed frequently
- ⚠️ Low-sensitivity data

---

## Encryption Options

This boilerplate provides **3 encryption options**:

### 1. Database-Level Encryption (SQL Functions)

**Recommended for**: Encrypting specific columns in existing tables

- **PostgreSQL**: `encrypt_text()` / `decrypt_text()` (pgcrypto extension)
- **MariaDB**: `encrypt_text()` / `decrypt_text()` (AES functions)

**Pros:**

- ✅ Transparent - works with any language/framework
- ✅ Encryption happens in database (consistent)
- ✅ Key never leaves application server

**Cons:**

- ⚠️ Cannot index encrypted columns efficiently
- ⚠️ Searching requires full table scan

**Setup:** See
[Database-Level Encryption](#database-level-encryption-sql-functions)

---

### 2. Application-Level Encryption (PHP/Node.js)

**Recommended for**: Full control over encryption, application-specific needs

- **PHP**: `EncryptionService::encrypt()` / `::decrypt()`
- **Node.js**: `EncryptionService.encrypt()` / `.decrypt()`

**Pros:**

- ✅ Full control over encryption logic
- ✅ Can use different keys per tenant/user
- ✅ Works with any database (even NoSQL)

**Cons:**

- ⚠️ More code to maintain
- ⚠️ Must ensure consistency across services

**Setup:** See
[Application-Level Encryption](#application-level-encryption-phpnodejs)

---

### 3. Table-Level Encryption at Rest (MariaDB only)

**Recommended for**: Encrypting entire tables transparently

- **MariaDB**: `CREATE TABLE ... ENCRYPTED=YES`
- **Automatic**: All data in table is encrypted on disk

**Pros:**

- ✅ **Transparent** - no code changes needed
- ✅ **Fast** - encryption at storage layer
- ✅ **Can still use indexes** normally
- ✅ Protects against physical disk theft

**Cons:**

- ⚠️ MariaDB only (not available in PostgreSQL without extensions)
- ⚠️ Encrypts entire table (all-or-nothing)
- ⚠️ Requires keyfile management

**Setup:** See
[Table-Level Encryption at Rest](#table-level-encryption-at-rest-mariadb-only)

---

## Database-Level Encryption (SQL Functions)

### PostgreSQL (pgcrypto)

#### 1. Enable pgcrypto Extension

Apply all pending migrations (`000_encryption_helpers.sql` runs first):

```bash
make db-migrations
```

The migration creates two functions:

- `encrypt_text(plaintext TEXT, key TEXT) RETURNS BYTEA`
- `decrypt_text(encrypted BYTEA, key TEXT) RETURNS TEXT`

#### 2. Create Table with Encrypted Column

```sql
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    full_name VARCHAR(255) NOT NULL,
    -- Encrypted column (BYTEA type)
    ssn BYTEA,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### 3. Insert Encrypted Data

**From SQL:**

```sql
INSERT INTO users (email, full_name, ssn)
VALUES (
    'user@example.com',
    'John Doe',
    encrypt_text('123-45-6789', current_setting('app.encryption_key'))
);
```

**From PHP:**

```php
$encryptionKey = $_ENV['ENCRYPTION_KEY'] ?? throw new RuntimeException('ENCRYPTION_KEY not set');

$stmt = $pdo->prepare('
    INSERT INTO users (email, full_name, ssn)
    VALUES (:email, :name, encrypt_text(:ssn, :key))
');
$stmt->execute([
    'email' => 'user@example.com',
    'name' => 'John Doe',
    'ssn' => '123-45-6789',
    'key' => $encryptionKey,
]);
```

#### 4. Query with Decryption

```sql
SELECT
    id,
    email,
    full_name,
    decrypt_text(ssn, current_setting('app.encryption_key')) AS ssn_decrypted
FROM users;
```

---

### MariaDB (AES Functions)

#### 1. Enable Encryption Functions

Apply all pending migrations (`000_encryption_helpers.sql` runs first):

```bash
make db-migrations
```

The migration creates two functions:

- `encrypt_text(plaintext TEXT, key VARCHAR(255)) RETURNS VARBINARY(16000)`
- `decrypt_text(encrypted VARBINARY(16000), key VARCHAR(255)) RETURNS TEXT`

#### 2. Create Table with Encrypted Column

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    full_name VARCHAR(255) NOT NULL,
    -- Encrypted column (VARBINARY type)
    ssn VARBINARY(16000),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### 3. Insert / Query

Same as PostgreSQL examples above, but use `VARBINARY` instead of `BYTEA`.

---

## Application-Level Encryption (PHP/Node.js)

### PHP

```php
<?php
use App\Infrastructure\Encryption\EncryptionService;

$encryptionKey = $_ENV['ENCRYPTION_KEY'] ?? throw new RuntimeException('ENCRYPTION_KEY not set');

// Encrypt
$plaintext = '123-45-6789';
$encrypted = EncryptionService::encrypt($plaintext, $encryptionKey);
// Store $encrypted in VARCHAR/TEXT column

// Decrypt
$decrypted = EncryptionService::decrypt($encrypted, $encryptionKey);
// $decrypted === '123-45-6789'
```

**File:** `src/php/Infrastructure/Encryption/EncryptionService.php`

**Algorithm:** AES-256-GCM (authenticated encryption)

---

### Node.js / TypeScript

```typescript
import { EncryptionService } from './App/services/EncryptionService';

const encryptionKey = process.env.ENCRYPTION_KEY;
if (!encryptionKey) {
  throw new Error('ENCRYPTION_KEY not set');
}

// Encrypt
const plaintext = '123-45-6789';
const encrypted = EncryptionService.encrypt(plaintext, encryptionKey);
// Store encrypted in VARCHAR/TEXT column

// Decrypt
const decrypted = EncryptionService.decrypt(encrypted, encryptionKey);
// decrypted === '123-45-6789'
```

**File:** `src/node/backend/services/EncryptionService.ts`

**Algorithm:** AES-256-GCM (authenticated encryption)

---

## Table-Level Encryption at Rest (MariaDB only)

### 1. Generate Encryption Keyfile

```bash
bash docker/mariadb/generate-keyfile.sh
```

This creates `docker/mariadb/keyfile.key` (gitignored).

**⚠️ IMPORTANT:** Backup this keyfile securely! If lost, encrypted data
**CANNOT** be recovered.

---

### 2. Enable Encryption in MariaDB Config

```bash
cp docker/mariadb/my.cnf.example docker/mariadb/my.cnf
```

Edit `docker/mariadb/my.cnf` and uncomment encryption settings:

```ini
plugin_load_add = file_key_management
file_key_management_filename = /etc/mysql/keyfile.key
innodb_encrypt_tables = ON
innodb_encrypt_log = ON
innodb_encryption_threads = 4
```

---

### 3. Mount Keyfile and Config in Docker Compose

Edit `compose.yaml` or `compose.production.yaml`:

```yaml
mariadb:
  volumes:
    # Mount encryption keyfile (read-only)
    - ./docker/mariadb/keyfile.key:/etc/mysql/keyfile.key:ro
    # Mount custom config
    - ./docker/mariadb/my.cnf:/etc/mysql/conf.d/encryption.cnf:ro
```

---

### 4. Restart MariaDB

```bash
make restart
```

---

### 5. Create Encrypted Tables

```sql
CREATE TABLE sensitive_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ssn VARCHAR(255),
    health_data TEXT
) ENGINE=InnoDB ENCRYPTED=YES;
```

**All data in this table is now automatically encrypted on disk!**

You can query and index normally - encryption is transparent.

---

### 6. Encrypt Existing Tables

```sql
ALTER TABLE existing_table ENCRYPTED=YES;
```

---

### 7. Check Encryption Status

```sql
SELECT
    TABLE_SCHEMA,
    TABLE_NAME,
    CREATE_OPTIONS
FROM INFORMATION_SCHEMA.TABLES
WHERE CREATE_OPTIONS LIKE '%ENCRYPTED%';
```

---

## Performance Considerations

### ⚠️ Encrypted Columns Cannot Be Indexed

Searching encrypted data requires **full table scan** (very slow).

**Bad:**

```sql
-- SLOW - full table scan!
SELECT * FROM users
WHERE decrypt_text(ssn, 'key') = '123-45-6789';
```

#### Better: Use Hash-Based Lookups

Store a hash of the searchable value:

```sql
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    -- Encrypted data
    ssn BYTEA,
    -- Hash for searching (indexed)
    ssn_hash VARCHAR(64),
    INDEX (ssn_hash)
);

-- Insert with hash
INSERT INTO users (email, ssn, ssn_hash)
VALUES (
    'user@example.com',
    encrypt_text('123-45-6789', 'key'),
    SHA256('123-45-6789')  -- PostgreSQL: encode(digest('123-45-6789', 'sha256'), 'hex')
);

-- Search by hash (fast - uses index)
SELECT * FROM users
WHERE ssn_hash = SHA256('123-45-6789');
```

---

## Key Management Best Practices

### 1. Environment Variables

**Store encryption keys in `.env`:**

```bash
ENCRYPTION_KEY=your-base64-encoded-32-byte-key
BACKUP_ENCRYPTION_KEY=your-backup-key
```

**Generated automatically by `make setup`** (uses `openssl rand -base64 32`)

---

### 2. Key Rotation

**When to rotate:**

- Regularly (e.g., every 12 months)
- After security incident
- After employee departure

**How to rotate:**

1. Generate new key: `openssl rand -base64 32`
2. Add to `.env`: `ENCRYPTION_KEY_NEW=...`
3. Re-encrypt all data:

   ```sql
   UPDATE users
   SET ssn = encrypt_text(decrypt_text(ssn, 'OLD_KEY'), 'NEW_KEY');
   ```

4. Replace `ENCRYPTION_KEY` with `ENCRYPTION_KEY_NEW`
5. Remove old key

---

### 3. Backup Encryption Key

**Critical:** If you lose the encryption key, data **CANNOT** be recovered!

- ✅ Store backup key in secure location (password manager, vault)
- ✅ Use `BACKUP_ENCRYPTION_KEY` for redundancy
- ✅ Document key recovery process
- ⚠️ Never commit keys to version control (already in `.gitignore`)

---

## GDPR Compliance Checklist

- ✅ **Art. 32**: Encryption of personal data (implemented)
- ✅ **Appropriate level**: Use encryption only for sensitive data (Art. 9)
- ✅ **Key management**: Secure storage of encryption keys
- ✅ **Right to access**: Can decrypt data for subject access requests
- ✅ **Right to erasure**: Can delete encrypted data
- ✅ **Data breach**: Encrypted data is useless without key (reduces breach
  impact)

---

## Troubleshooting

### Decryption Returns NULL

**Causes:**

- Wrong encryption key
- Corrupted encrypted data
- Wrong algorithm/mode

**Solution:**

- Verify `ENCRYPTION_KEY` is correct
- Check that encrypted data is not truncated (BYTEA/VARBINARY large enough)

---

### Performance Issues

**Problem:** Queries are slow when decrypting many rows

**Solutions:**

1. Decrypt only when necessary (not in WHERE clause)
2. Use hash-based lookups (see above)
3. Consider table-level encryption (MariaDB ENCRYPTED=YES) for better
   performance

---

### MariaDB Keyfile Not Found

**Error:** `file_key_management plugin: Can't open keyfile`

**Solutions:**

1. Generate keyfile: `bash docker/mariadb/generate-keyfile.sh`
2. Check volume mount in `compose.yaml`
3. Verify file permissions: `chmod 600 docker/mariadb/keyfile.key`

---

## Migration Path

**Already have unencrypted data?**

### PostgreSQL

```sql
-- 1. Add encrypted column
ALTER TABLE users ADD COLUMN ssn_encrypted BYTEA;

-- 2. Encrypt existing data
UPDATE users
SET ssn_encrypted = encrypt_text(ssn, 'ENCRYPTION_KEY')
WHERE ssn IS NOT NULL;

-- 3. Drop old column (after verification!)
ALTER TABLE users DROP COLUMN ssn;

-- 4. Rename column
ALTER TABLE users RENAME COLUMN ssn_encrypted TO ssn;
```

### MariaDB Table-Level

```sql
-- Simply enable encryption on existing table
ALTER TABLE users ENCRYPTED=YES;
```

Background encryption will happen automatically (controlled by
`innodb_encryption_threads`).

---

## Further Reading

- [PostgreSQL pgcrypto Documentation](https://www.postgresql.org/docs/current/pgcrypto.html)
- [MariaDB Encryption at Rest](https://mariadb.com/kb/en/data-at-rest-encryption/)
- [GDPR Art. 32 - Security of Processing](https://gdpr-info.eu/art-32-gdpr/)
- [OWASP Cryptographic Storage Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cryptographic_Storage_Cheat_Sheet.html)
