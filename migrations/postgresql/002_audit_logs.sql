-- ============================================================================
-- Audit Logs Table (PostgreSQL)
-- ============================================================================
-- GDPR Art. 30: Records of processing activities
-- GDPR Art. 32: Security measures (logging access to personal data)
--
-- This migration creates the audit_logs table for GDPR-compliant audit logging.
--
-- Required for:
-- - GDPR Art. 15: Right of access (who accessed my data?)
-- - GDPR Art. 17: Right to erasure (audit trail of deletion)
-- - GDPR Art. 33: Breach notification (what data was accessed?)
-- - Security audits and compliance reviews
--
-- Retention Policy:
-- - Audit logs should be retained for at least 90 days (recommended: 1-2 years)
-- - See Phase 2.2 for retention policy examples (delete_old_logs() function)
-- ============================================================================

-- Create audit_logs table
CREATE TABLE IF NOT EXISTS audit_logs (
    -- Primary key
    id BIGSERIAL PRIMARY KEY,

    -- Timestamp (indexed for efficient queries)
    timestamp TIMESTAMP NOT NULL DEFAULT NOW(),

    -- User who performed the action (NULL for system actions or failed login attempts)
    user_id INTEGER DEFAULT NULL,

    -- IP address of the client
    ip_address VARCHAR(45) NOT NULL,

    -- Action performed (e.g., 'user.view', 'user.update', 'user.delete', 'login.success')
    action VARCHAR(255) NOT NULL,

    -- Entity type (e.g., 'user', 'order', 'invoice')
    entity_type VARCHAR(100) NOT NULL,

    -- Entity ID (primary key of the entity)
    entity_id VARCHAR(255) NOT NULL,

    -- Additional data (encrypted with ENCRYPTION_KEY)
    -- Stores JSON data: { "user_agent": "...", "changed_fields": [...], ... }
    data BYTEA,

    -- Tamper-proof checksum (SHA-256)
    -- Calculated as: SHA256(timestamp + action + entity_type + entity_id + data)
    checksum VARCHAR(64) NOT NULL,

    -- Prevent updates and deletes (append-only table)
    CONSTRAINT audit_logs_immutable CHECK (FALSE)
);

-- Remove the immutable constraint (workaround - constraint above prevents all writes)
ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_immutable;

-- Add trigger to prevent updates and deletes (append-only table)
CREATE OR REPLACE FUNCTION prevent_audit_log_modification()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'Audit logs are immutable and cannot be modified or deleted';
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs;
CREATE TRIGGER audit_logs_no_update
    BEFORE UPDATE ON audit_logs
    FOR EACH ROW
    EXECUTE FUNCTION prevent_audit_log_modification();

DROP TRIGGER IF EXISTS audit_logs_no_delete ON audit_logs;
CREATE TRIGGER audit_logs_no_delete
    BEFORE DELETE ON audit_logs
    FOR EACH ROW
    EXECUTE FUNCTION prevent_audit_log_modification();

-- ============================================================================
-- INDEXES for performance
-- ============================================================================

-- Index for queries by timestamp (e.g., get recent logs)
CREATE INDEX idx_audit_logs_timestamp ON audit_logs (timestamp DESC);

-- Index for queries by user_id (e.g., get all actions by user)
CREATE INDEX idx_audit_logs_user_id ON audit_logs (user_id, timestamp DESC);

-- Index for queries by entity (e.g., get all actions on a specific user/order)
CREATE INDEX idx_audit_logs_entity ON audit_logs (entity_type, entity_id, timestamp DESC);

-- Index for queries by action (e.g., get all 'user.delete' actions)
CREATE INDEX idx_audit_logs_action ON audit_logs (action, timestamp DESC);

-- Partial index for failed login attempts (security monitoring)
CREATE INDEX idx_audit_logs_failed_login ON audit_logs (timestamp DESC)
WHERE action = 'login.failed';

-- ============================================================================
-- COMMENTS for documentation
-- ============================================================================

COMMENT ON TABLE audit_logs IS 'GDPR-compliant audit log table (append-only, encrypted data, tamper-proof checksums)';
COMMENT ON COLUMN audit_logs.id IS 'Primary key';
COMMENT ON COLUMN audit_logs.timestamp IS 'Timestamp when action was performed (indexed)';
COMMENT ON COLUMN audit_logs.user_id IS 'User who performed the action (NULL for system actions or failed logins)';
COMMENT ON COLUMN audit_logs.ip_address IS 'IP address of the client';
COMMENT ON COLUMN audit_logs.action IS 'Action performed (e.g., user.view, user.update, login.success)';
COMMENT ON COLUMN audit_logs.entity_type IS 'Entity type (e.g., user, order, invoice)';
COMMENT ON COLUMN audit_logs.entity_id IS 'Entity ID (primary key of the entity)';
COMMENT ON COLUMN audit_logs.data IS 'Additional data (encrypted BYTEA, decrypted with decrypt_text() function)';
COMMENT ON COLUMN audit_logs.checksum IS 'SHA-256 checksum for tamper detection';

-- ============================================================================
-- USAGE EXAMPLES (COMMENTED OUT - UNCOMMENT TO TEST)
-- ============================================================================

/*
-- Insert audit log entry
INSERT INTO audit_logs (timestamp, user_id, ip_address, action, entity_type, entity_id, data, checksum)
VALUES (
    NOW(),
    123,  -- user_id
    '192.168.1.1',
    'user.update',
    'user',
    '456',
    encrypt_text('{"changed_fields": ["email", "phone"], "user_agent": "Mozilla/5.0"}', 'your-encryption-key'),
    encode(digest(NOW()::text || 'user.update' || 'user' || '456' || '{"changed_fields": ["email", "phone"]}', 'sha256'), 'hex')
);

-- Query audit logs for a specific user (decrypted)
SELECT
    id,
    timestamp,
    user_id,
    ip_address,
    action,
    entity_type,
    entity_id,
    decrypt_text(data, 'your-encryption-key') AS data_decrypted
FROM audit_logs
WHERE user_id = 123
ORDER BY timestamp DESC
LIMIT 50;

-- Query audit logs for a specific entity
SELECT
    id,
    timestamp,
    user_id,
    ip_address,
    action,
    decrypt_text(data, 'your-encryption-key') AS data_decrypted
FROM audit_logs
WHERE entity_type = 'user' AND entity_id = '456'
ORDER BY timestamp DESC;

-- Failed login attempts (security monitoring)
SELECT
    timestamp,
    ip_address,
    decrypt_text(data, 'your-encryption-key') AS data_decrypted
FROM audit_logs
WHERE action = 'login.failed'
  AND timestamp > NOW() - INTERVAL '24 hours'
ORDER BY timestamp DESC;
*/

-- ============================================================================
-- RETENTION POLICY (See Phase 2.2)
-- ============================================================================
-- Audit logs should be retained for at least 90 days.
-- For automatic cleanup, see migrations/postgresql/002_retention_policies.sql
-- (will be created in Phase 2.2)
-- ============================================================================
