-- ============================================================================
-- MariaDB Encryption Helpers (AES_ENCRYPT / AES_DECRYPT)
-- ============================================================================
-- GDPR Art. 32: Column-level encryption for highly sensitive personal data
--
-- This migration provides optional encryption capabilities using MariaDB's
-- built-in AES functions.
--
-- IMPORTANT: This is OPTIONAL! Only enable if you need column-level encryption.
-- Most applications don't need this - TLS + disk encryption is sufficient.
--
-- Usage:
--   SELECT encrypt_text('sensitive data', 'encryption_key_from_env');
--   SELECT decrypt_text(encrypted_column, 'encryption_key_from_env');
-- ============================================================================

DELIMITER $$

-- ============================================================================
-- HELPER FUNCTIONS
-- ============================================================================

-- Encrypt text using AES-256-CBC
-- Returns VARBINARY (binary data) that must be stored in VARBINARY column
DROP FUNCTION IF EXISTS encrypt_text$$
CREATE FUNCTION encrypt_text(
    plaintext TEXT,
    encryption_key VARCHAR(255)
) RETURNS VARBINARY(16000)
DETERMINISTIC
BEGIN
    IF plaintext IS NULL THEN
        RETURN NULL;
    END IF;

    -- Use AES-256-CBC encryption
    -- AES_ENCRYPT uses 256-bit AES if key is >= 32 bytes
    RETURN AES_ENCRYPT(plaintext, encryption_key);
END$$

-- Decrypt VARBINARY back to text
-- Returns NULL if decryption fails (wrong key or corrupted data)
DROP FUNCTION IF EXISTS decrypt_text$$
CREATE FUNCTION decrypt_text(
    encrypted VARBINARY(16000),
    encryption_key VARCHAR(255)
) RETURNS TEXT
DETERMINISTIC
BEGIN
    DECLARE decrypted_value VARBINARY(16000);

    IF encrypted IS NULL THEN
        RETURN NULL;
    END IF;

    -- Decrypt using AES-256-CBC
    SET decrypted_value = AES_DECRYPT(encrypted, encryption_key);

    -- Return as text (CAST to CHAR to ensure proper character encoding)
    IF decrypted_value IS NULL THEN
        RETURN NULL;
    ELSE
        RETURN CAST(decrypted_value AS CHAR CHARACTER SET utf8mb4);
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- USAGE EXAMPLE (COMMENTED OUT - UNCOMMENT TO TEST)
-- ============================================================================

-- Example table with encrypted column
/*
CREATE TABLE users_with_encryption (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    -- Regular text column (NOT encrypted)
    full_name VARCHAR(255) NOT NULL,
    -- Encrypted column (stores VARBINARY)
    ssn VARBINARY(16000),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert encrypted data
INSERT INTO users_with_encryption (email, full_name, ssn)
VALUES (
    'user@example.com',
    'John Doe',
    encrypt_text('123-45-6789', 'your-encryption-key-from-env')
);

-- Query with decryption
SELECT
    id,
    email,
    full_name,
    decrypt_text(ssn, 'your-encryption-key-from-env') AS ssn_decrypted
FROM users_with_encryption;

-- Search encrypted data (NOTE: SLOW - requires full table scan)
-- Avoid searching encrypted columns if possible!
SELECT *
FROM users_with_encryption
WHERE decrypt_text(ssn, 'your-encryption-key-from-env') = '123-45-6789';
*/

-- ============================================================================
-- MARIADB ENCRYPTION AT REST (TABLE-LEVEL)
-- ============================================================================
--
-- MariaDB also supports automatic table-level encryption at rest.
-- This encrypts the entire table file on disk transparently.
--
-- Requirements:
-- 1. Enable file_key_management plugin (see docker/mariadb/my.cnf)
-- 2. Generate encryption keyfile (see docker/mariadb/keyfile.key)
-- 3. Create table with ENCRYPTED=YES option
--
-- Example:
--
-- CREATE TABLE sensitive_data (
--     id INT AUTO_INCREMENT PRIMARY KEY,
--     data TEXT
-- ) ENGINE=InnoDB ENCRYPTED=YES;
--
-- Advantages over column-level encryption:
-- ✅ Transparent - no code changes needed
-- ✅ Better performance - encryption happens at storage layer
-- ✅ Can still use indexes normally
-- ✅ Encrypts all data in the table automatically
--
-- Disadvantages:
-- ⚠️  All-or-nothing - can't encrypt individual columns
-- ⚠️  Requires server-side encryption key management
-- ⚠️  Key rotation is more complex
--
-- See docker/mariadb/my.cnf for configuration details.
-- ============================================================================

-- ============================================================================
-- PERFORMANCE CONSIDERATIONS
-- ============================================================================
--
-- Encrypted columns CANNOT be indexed efficiently!
-- Searching encrypted data requires full table scan (very slow).
--
-- Best practices:
-- 1. Store searchable fields UNENCRYPTED (e.g., email, username)
-- 2. Encrypt only highly sensitive fields that don't need searching
-- 3. Use hash-based lookups if you need to find encrypted data:
--    - Store: encrypted_ssn VARBINARY(16000), ssn_hash VARCHAR(64)
--    - Search by: WHERE ssn_hash = SHA2('search-value', 256)
--    - Then decrypt matched rows
-- 4. Consider table-level encryption (ENCRYPTED=YES) for better performance
-- ============================================================================

-- ============================================================================
-- GDPR COMPLIANCE NOTES
-- ============================================================================
--
-- GDPR Art. 32 requires "appropriate technical measures" for data protection.
-- Column-level encryption is NOT always necessary!
--
-- Sufficient for most applications:
-- ✅ TLS/SSL for data in transit (already implemented)
-- ✅ Encrypted storage volumes (operating system level)
-- ✅ Access control and authentication
-- ✅ Audit logging
--
-- When to use column-level encryption:
-- ❗ Special categories of personal data (GDPR Art. 9):
--    - Health data
--    - Biometric data
--    - Genetic data
--    - Financial account numbers (credit cards, bank accounts)
--    - Social security numbers
--    - Government ID numbers
--
-- When NOT to use column-level encryption:
-- ⚠️  Regular personal data (name, email, address, phone)
-- ⚠️  Data that needs to be searched/indexed frequently
-- ⚠️  Low-sensitivity data
-- ============================================================================
