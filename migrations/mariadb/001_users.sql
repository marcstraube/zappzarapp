-- ============================================================================
-- Users Table (MariaDB)
-- ============================================================================
-- Example users table demonstrating:
-- - Basic user fields (email, password_hash, name)
-- - Encrypted field (totp_secret) for 2FA using AES encryption
-- - Timestamps with auto-update
--
-- The totp_secret column demonstrates column-level encryption for sensitive
-- data that must be decrypted (not hashed) - 2FA secrets need to be read
-- by the server to verify TOTP codes.
--
-- Password storage uses bcrypt hashing (one-way) - see application layer.
-- ============================================================================

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    -- Primary key
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Authentication
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,

    -- Profile
    name VARCHAR(255) DEFAULT NULL,

    -- 2FA (TOTP) - encrypted with AES
    -- Use encrypt_text() from 000_encryption_helpers.sql
    totp_secret VARBINARY(512) DEFAULT NULL,
    totp_enabled TINYINT(1) NOT NULL DEFAULT 0,

    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_email (email),
    INDEX idx_created_at (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table comments
ALTER TABLE users
    COMMENT = 'Application users with optional 2FA support';

-- ============================================================================
-- USAGE EXAMPLES
-- ============================================================================

/*
-- Insert user without 2FA
INSERT INTO users (email, password_hash, name)
VALUES ('user@example.com', '$2y$10$...bcrypt_hash...', 'John Doe');

-- Insert user with 2FA (encrypt the TOTP secret)
INSERT INTO users (email, password_hash, name, totp_secret, totp_enabled)
VALUES (
    'secure@example.com',
    '$2y$10$...bcrypt_hash...',
    'Jane Doe',
    encrypt_text('JBSWY3DPEHPK3PXP', 'your-encryption-key'),
    1
);

-- Query user and decrypt TOTP secret
SELECT
    id,
    email,
    name,
    totp_enabled,
    decrypt_text(totp_secret, 'your-encryption-key') AS totp_secret_decrypted
FROM users
WHERE email = 'secure@example.com';

-- Enable 2FA for existing user
UPDATE users SET
    totp_secret = encrypt_text('JBSWY3DPEHPK3PXP', 'your-encryption-key'),
    totp_enabled = 1
WHERE id = 1;

-- Disable 2FA
UPDATE users SET
    totp_secret = NULL,
    totp_enabled = 0
WHERE id = 1;
*/

-- ============================================================================
-- PERMISSIONS
-- ============================================================================
-- Grant permissions to application user (customize for your setup)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON users TO 'app_user'@'%';
