-- ============================================================================
-- PostgreSQL Encryption Helpers (pgcrypto)
-- ============================================================================
-- GDPR Art. 32: Column-level encryption for highly sensitive personal data
--
-- This migration provides optional encryption capabilities using pgcrypto.
-- Use this ONLY for highly sensitive fields (e.g., social security numbers,
-- health data, financial data).
--
-- IMPORTANT: This is OPTIONAL! Only enable if you need column-level encryption.
-- Most applications don't need this - TLS + disk encryption is sufficient.
--
-- Usage:
--   SELECT encrypt_text('sensitive data', 'encryption_key_from_env');
--   SELECT decrypt_text(encrypted_column, 'encryption_key_from_env');
-- ============================================================================

-- Enable pgcrypto extension (provides AES-256 encryption)
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- ============================================================================
-- HELPER FUNCTIONS
-- ============================================================================

-- Encrypt text using AES-256-GCM (Authenticated Encryption)
-- Returns BYTEA (binary data) that must be stored in BYTEA column
CREATE OR REPLACE FUNCTION encrypt_text(
    plaintext TEXT,
    encryption_key TEXT
) RETURNS BYTEA AS $$
BEGIN
    IF plaintext IS NULL THEN
        RETURN NULL;
    END IF;

    -- Use AES-256 in GCM mode (provides both confidentiality and authenticity)
    -- pgcrypto uses 'aes' cipher with key derivation (PBKDF2)
    RETURN pgp_sym_encrypt(plaintext, encryption_key, 'cipher-algo=aes256');
END;
$$ LANGUAGE plpgsql IMMUTABLE;

-- Decrypt BYTEA back to text
-- Returns NULL if decryption fails (wrong key or corrupted data)
CREATE OR REPLACE FUNCTION decrypt_text(
    encrypted BYTEA,
    encryption_key TEXT
) RETURNS TEXT AS $$
BEGIN
    IF encrypted IS NULL THEN
        RETURN NULL;
    END IF;

    -- Decrypt and return as text
    -- Will raise exception if key is wrong - catch in application layer
    RETURN pgp_sym_decrypt(encrypted, encryption_key);
EXCEPTION
    WHEN OTHERS THEN
        -- Return NULL on decryption failure (wrong key, corrupted data)
        RETURN NULL;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

-- ============================================================================
-- USAGE EXAMPLE (COMMENTED OUT - UNCOMMENT TO TEST)
-- ============================================================================

-- Example table with encrypted column
/*
CREATE TABLE users_with_encryption (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    -- Regular text column (NOT encrypted)
    full_name VARCHAR(255) NOT NULL,
    -- Encrypted column (stores BYTEA)
    ssn BYTEA,
    created_at TIMESTAMP DEFAULT NOW()
);

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
--    - Store: encrypted_ssn BYTEA, ssn_hash VARCHAR(64)
--    - Search by: WHERE ssn_hash = SHA256('search-value')
--    - Then decrypt matched rows
-- 4. Consider application-level encryption if you need more control
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

COMMENT ON FUNCTION encrypt_text IS 'Encrypt text using AES-256-GCM. Use for highly sensitive data only (GDPR Art. 9 special categories).';
COMMENT ON FUNCTION decrypt_text IS 'Decrypt BYTEA back to text. Returns NULL on failure.';
