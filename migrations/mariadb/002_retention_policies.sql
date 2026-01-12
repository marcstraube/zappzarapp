-- ============================================================================
-- Retention Policy Procedures (MariaDB)
-- ============================================================================
-- GDPR Art. 5(1)(e): Storage limitation principle
-- GDPR Art. 17: Right to erasure ("Right to be forgotten")
--
-- This migration provides example procedures for:
-- 1. delete_old_logs() - Delete logs older than retention period
-- 2. anonymize_user() - Anonymize user data (GDPR Art. 17)
--
-- IMPORTANT: These are EXAMPLE procedures. Customize for your application!
--
-- Scheduling (MariaDB EVENT, cron, systemd timer) is a Dev/Admin task, not boilerplate.
-- See documentation/gdpr/RETENTION-POLICY.md for scheduling options.
-- ============================================================================

DELIMITER $$

-- ============================================================================
-- 1. DELETE OLD LOGS
-- ============================================================================
-- Deletes logs older than the specified retention period.
--
-- Usage:
--   CALL delete_old_logs('audit_logs', 90, @deleted);  -- Delete audit logs older than 90 days
--   SELECT @deleted;  -- Number of deleted rows
--
-- Parameters:
--   p_table_name     - Table name (must exist and have a 'timestamp' column)
--   p_retention_days - Number of days to retain (default: 90)
--   p_deleted_count  - OUT parameter: Number of deleted rows
--
-- IMPORTANT: audit_logs has immutability triggers! See note below.
-- ============================================================================

DROP PROCEDURE IF EXISTS delete_old_logs$$

CREATE PROCEDURE delete_old_logs(
    IN p_table_name VARCHAR(255),
    IN p_retention_days INT,
    OUT p_deleted_count INT
)
SQL SECURITY DEFINER
BEGIN
    DECLARE v_cutoff_date TIMESTAMP;
    DECLARE v_has_trigger INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        -- Re-enable trigger if it was disabled
        IF p_table_name = 'audit_logs' AND v_has_trigger = 1 THEN
            SET @sql = 'CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW BEGIN SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''Audit logs are immutable and cannot be deleted''; END';
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
        END IF;
        RESIGNAL;
    END;

    -- Validate retention period
    IF p_retention_days < 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Retention period must be at least 1 day';
    END IF;

    -- Calculate cutoff date
    SET v_cutoff_date = DATE_SUB(NOW(), INTERVAL p_retention_days DAY);

    -- For audit_logs: Temporarily disable immutability trigger
    IF p_table_name = 'audit_logs' THEN
        -- Check if trigger exists
        SELECT COUNT(*) INTO v_has_trigger
        FROM information_schema.TRIGGERS
        WHERE TRIGGER_SCHEMA = DATABASE()
        AND TRIGGER_NAME = 'audit_logs_no_delete';

        IF v_has_trigger = 1 THEN
            DROP TRIGGER IF EXISTS audit_logs_no_delete;
        END IF;
    END IF;

    -- Build and execute dynamic SQL
    SET @sql = CONCAT('DELETE FROM `', p_table_name, '` WHERE timestamp < ''', v_cutoff_date, '''');
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    SET p_deleted_count = ROW_COUNT();
    DEALLOCATE PREPARE stmt;

    -- Re-create trigger for audit_logs
    IF p_table_name = 'audit_logs' AND v_has_trigger = 1 THEN
        SET @sql = 'CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs FOR EACH ROW BEGIN SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''Audit logs are immutable and cannot be deleted''; END';
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;

END$$

-- ============================================================================
-- 2. ANONYMIZE USER (Right to be forgotten)
-- ============================================================================
-- Anonymizes all user data across tables. GDPR Art. 17 compliance.
--
-- This procedure:
-- 1. Replaces PII with anonymized values (keeps record structure intact)
-- 2. Logs the anonymization action in audit_logs (for compliance proof)
-- 3. Does NOT delete records (maintains referential integrity)
--
-- Usage:
--   CALL anonymize_user(123, NULL, @success);         -- Anonymize user with ID 123
--   CALL anonymize_user(123, 'GDPR-REQ-2024-001', @success);  -- With reference
--   SELECT @success;  -- TRUE (1) if successful
--
-- Parameters:
--   p_user_id   - User ID to anonymize
--   p_reference - Optional reference number (e.g., GDPR request ID)
--   p_success   - OUT parameter: 1 if successful, 0 if user not found
--
-- IMPORTANT: Customize this procedure for your application's tables!
-- ============================================================================

DROP PROCEDURE IF EXISTS anonymize_user$$

CREATE PROCEDURE anonymize_user(
    IN p_user_id INT,
    IN p_reference VARCHAR(255),
    OUT p_success TINYINT
)
SQL SECURITY DEFINER
BEGIN
    DECLARE v_anonymized_email VARCHAR(255);
    DECLARE v_anonymized_name VARCHAR(255);
    DECLARE v_ip_address VARCHAR(45) DEFAULT '0.0.0.0';
    DECLARE v_checksum VARCHAR(64);
    DECLARE v_data_json TEXT;
    DECLARE v_reference VARCHAR(255);

    SET p_success = 0;
    SET v_reference = COALESCE(p_reference, 'N/A');

    -- Generate anonymized values
    SET v_anonymized_email = CONCAT('deleted_', p_user_id, '@anonymized.local');
    SET v_anonymized_name = CONCAT('Deleted User ', p_user_id);

    -- ========================================================================
    -- CUSTOMIZE THIS SECTION FOR YOUR APPLICATION
    -- ========================================================================
    -- Example: Anonymize a "users" table
    -- Uncomment and modify for your actual table structure:
    --
    -- UPDATE users SET
    --     email = v_anonymized_email,
    --     first_name = 'DELETED',
    --     last_name = 'USER',
    --     phone = NULL,
    --     address = NULL,
    --     date_of_birth = NULL,
    --     profile_image = NULL,
    --     updated_at = NOW(),
    --     deleted_at = NOW()
    -- WHERE id = p_user_id;
    --
    -- IF ROW_COUNT() = 0 THEN
    --     SET p_success = 0;
    --     -- Optionally: SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User not found';
    -- END IF;
    --
    -- Example: Anonymize user's orders
    -- UPDATE orders SET
    --     shipping_name = 'DELETED USER',
    --     shipping_address = 'ANONYMIZED',
    --     shipping_phone = NULL,
    --     billing_name = 'DELETED USER',
    --     billing_address = 'ANONYMIZED'
    -- WHERE user_id = p_user_id;
    --
    -- Example: Anonymize user's comments
    -- UPDATE comments SET
    --     author_name = v_anonymized_name
    -- WHERE user_id = p_user_id;
    -- ========================================================================

    -- Build data JSON
    SET v_data_json = JSON_OBJECT(
        'action', 'user_anonymized',
        'reference', v_reference,
        'anonymized_fields', JSON_ARRAY('email', 'name', 'phone', 'address'),
        'performed_by', 'system'
    );

    -- Calculate checksum
    SET v_checksum = SHA2(
        CONCAT(NOW(), 'user.anonymize', 'user', p_user_id, v_data_json),
        256
    );

    -- Log the anonymization action in audit_logs
    INSERT INTO audit_logs (
        timestamp,
        user_id,
        ip_address,
        action,
        entity_type,
        entity_id,
        data,
        checksum
    ) VALUES (
        NOW(),
        NULL,  -- System action, no user
        v_ip_address,
        'user.anonymize',
        'user',
        CAST(p_user_id AS CHAR),
        v_data_json,  -- Unencrypted for this example; use encrypt_text() in production
        v_checksum
    );

    SET p_success = 1;

END$$

-- ============================================================================
-- 3. HELPER: Get retention policy status
-- ============================================================================
-- Returns statistics about tables that may need cleanup.
--
-- Usage:
--   CALL get_retention_status();
-- ============================================================================

DROP PROCEDURE IF EXISTS get_retention_status$$

CREATE PROCEDURE get_retention_status()
BEGIN
    -- Check audit_logs
    SELECT
        'audit_logs' AS table_name,
        COUNT(*) AS total_rows,
        MIN(timestamp) AS oldest_record,
        MAX(timestamp) AS newest_record,
        SUM(CASE WHEN timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) AS rows_older_than_90_days,
        SUM(CASE WHEN timestamp < DATE_SUB(NOW(), INTERVAL 365 DAY) THEN 1 ELSE 0 END) AS rows_older_than_365_days
    FROM audit_logs;

    -- Add more tables as needed:
    -- SELECT
    --     'sessions' AS table_name,
    --     COUNT(*) AS total_rows,
    --     MIN(timestamp) AS oldest_record,
    --     MAX(timestamp) AS newest_record,
    --     SUM(CASE WHEN timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) AS rows_older_than_90_days,
    --     SUM(CASE WHEN timestamp < DATE_SUB(NOW(), INTERVAL 365 DAY) THEN 1 ELSE 0 END) AS rows_older_than_365_days
    -- FROM sessions;
END$$

DELIMITER ;

-- ============================================================================
-- USAGE EXAMPLES (COMMENTED OUT)
-- ============================================================================

/*
-- Check retention status
CALL get_retention_status();

-- Delete audit logs older than 2 years (730 days)
CALL delete_old_logs('audit_logs', 730, @deleted);
SELECT @deleted AS deleted_rows;

-- Delete sessions older than 30 days
CALL delete_old_logs('sessions', 30, @deleted);
SELECT @deleted AS deleted_rows;

-- Anonymize user (GDPR Art. 17 request)
CALL anonymize_user(123, 'GDPR-REQ-2024-001', @success);
SELECT @success;

-- Schedule with MariaDB Event Scheduler (requires SUPER privilege - Admin task!)
-- SET GLOBAL event_scheduler = ON;
--
-- CREATE EVENT IF NOT EXISTS cleanup_old_audit_logs
-- ON SCHEDULE EVERY 1 DAY
-- STARTS CURRENT_DATE + INTERVAL 3 HOUR  -- Run at 3 AM
-- DO
--   CALL delete_old_logs('audit_logs', 730, @deleted);
--
-- CREATE EVENT IF NOT EXISTS cleanup_old_sessions
-- ON SCHEDULE EVERY 1 DAY
-- STARTS CURRENT_DATE + INTERVAL 4 HOUR  -- Run at 4 AM
-- DO
--   CALL delete_old_logs('sessions', 30, @deleted);
*/

-- ============================================================================
-- PERMISSIONS
-- ============================================================================
-- Grant execute permissions to application role (customize for your setup)
-- GRANT EXECUTE ON PROCEDURE delete_old_logs TO 'app_user'@'%';
-- GRANT EXECUTE ON PROCEDURE anonymize_user TO 'app_user'@'%';
-- GRANT EXECUTE ON PROCEDURE get_retention_status TO 'app_user'@'%';
