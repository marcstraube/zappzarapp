-- ============================================================================
-- Audit Logs Table (MariaDB)
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
-- - See Phase 2.2 for retention policy examples (delete_old_logs() event)
-- ============================================================================

-- Create audit_logs table
CREATE TABLE IF NOT EXISTS audit_logs (
    -- Primary key
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Timestamp (indexed for efficient queries)
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- User who performed the action (NULL for system actions or failed login attempts)
    user_id INT UNSIGNED DEFAULT NULL,

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
    data VARBINARY(16000) DEFAULT NULL,

    -- Tamper-proof checksum (SHA-256)
    -- Calculated as: SHA256(timestamp + action + entity_type + entity_id + data)
    checksum VARCHAR(64) NOT NULL,

    -- Indexes
    INDEX idx_timestamp (timestamp DESC),
    INDEX idx_user_id (user_id, timestamp DESC),
    INDEX idx_entity (entity_type, entity_id, timestamp DESC),
    INDEX idx_action (action, timestamp DESC),
    INDEX idx_failed_login (timestamp DESC, action)  -- For failed login monitoring

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PREVENT UPDATES AND DELETES (Append-only table)
-- ============================================================================
-- Use triggers to prevent modifications (audit logs must be immutable)

DELIMITER $$

-- Prevent UPDATE
DROP TRIGGER IF EXISTS audit_logs_no_update$$
CREATE TRIGGER audit_logs_no_update
    BEFORE UPDATE ON audit_logs
    FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Audit logs are immutable and cannot be modified';
END$$

-- Prevent DELETE
DROP TRIGGER IF EXISTS audit_logs_no_delete$$
CREATE TRIGGER audit_logs_no_delete
    BEFORE DELETE ON audit_logs
    FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Audit logs are immutable and cannot be deleted';
END$$

DELIMITER ;

-- ============================================================================
-- COMMENTS for documentation (MariaDB 10.2.1+)
-- ============================================================================

ALTER TABLE audit_logs
    COMMENT = 'GDPR-compliant audit log table (append-only, encrypted data, tamper-proof checksums)';

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
    SHA2(CONCAT(NOW(), 'user.update', 'user', '456', '{"changed_fields": ["email", "phone"]}'), 256)
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
  AND timestamp > NOW() - INTERVAL 24 HOUR
ORDER BY timestamp DESC;
*/

-- ============================================================================
-- RETENTION POLICY (See Phase 2.2)
-- ============================================================================
-- Audit logs should be retained for at least 90 days.
-- For automatic cleanup, see migrations/mariadb/002_retention_policies.sql
-- (will be created in Phase 2.2)
-- ============================================================================

-- ============================================================================
-- OPTIONAL: Table-Level Encryption (MariaDB 10.1+)
-- ============================================================================
-- If you've enabled table-level encryption (see ENCRYPTION.md), you can
-- encrypt the entire audit_logs table on disk:
--
-- ALTER TABLE audit_logs ENCRYPTED=YES;
--
-- This provides an additional layer of security (data encrypted at rest).
-- Note: Column-level encryption (encrypt_text) is still used for the 'data'
-- column to ensure data is encrypted in backups and exports.
-- ============================================================================
