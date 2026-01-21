-- ============================================================================
-- Users Table (PostgreSQL)
-- ============================================================================
-- Example users table demonstrating:
-- - Basic user fields (email, password_hash, name)
-- - Encrypted field (totp_secret) for 2FA using pgcrypto
-- - Timestamps with auto-update trigger
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
    id SERIAL PRIMARY KEY,

    -- Authentication
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,

    -- Profile
    name VARCHAR(255),

    -- 2FA (TOTP) - encrypted with pgcrypto
    -- Use encrypt_text() from 000_encryption_helpers.sql
    totp_secret BYTEA DEFAULT NULL,
    totp_enabled BOOLEAN NOT NULL DEFAULT FALSE,

    -- Timestamps
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_users_email ON users (email);
CREATE INDEX IF NOT EXISTS idx_users_created_at ON users (created_at DESC);

-- Auto-update updated_at timestamp
CREATE OR REPLACE FUNCTION update_users_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS users_update_timestamp ON users;
CREATE TRIGGER users_update_timestamp
    BEFORE UPDATE ON users
    FOR EACH ROW
    EXECUTE FUNCTION update_users_timestamp();

-- Table comments
COMMENT ON TABLE users IS 'Application users with optional 2FA support';
COMMENT ON COLUMN users.email IS 'Unique email address (login identifier)';
COMMENT ON COLUMN users.password_hash IS 'Bcrypt hashed password (one-way hash)';
COMMENT ON COLUMN users.totp_secret IS 'Encrypted 2FA TOTP secret (use encrypt_text/decrypt_text)';
COMMENT ON COLUMN users.totp_enabled IS 'Whether 2FA is enabled for this user';

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
    TRUE
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
    totp_enabled = TRUE
WHERE id = 1;

-- Disable 2FA
UPDATE users SET
    totp_secret = NULL,
    totp_enabled = FALSE
WHERE id = 1;
*/

-- ============================================================================
-- PERMISSIONS
-- ============================================================================
-- Grant permissions to application role (customize for your setup)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON users TO app_role;
-- GRANT USAGE, SELECT ON SEQUENCE users_id_seq TO app_role;
