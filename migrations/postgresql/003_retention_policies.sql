-- ============================================================================
-- Retention Policy Functions (PostgreSQL)
-- ============================================================================
-- GDPR Art. 5(1)(e): Storage limitation principle
-- GDPR Art. 17: Right to erasure ("Right to be forgotten")
--
-- This migration provides example functions for:
-- 1. delete_old_logs() - Delete logs older than retention period
-- 2. anonymize_user() - Anonymize user data (GDPR Art. 17)
--
-- IMPORTANT: These are EXAMPLE functions. Customize for your application!
--
-- Scheduling (pg_cron, cron, systemd timer) is a Dev/Admin task, not boilerplate.
-- See documentation/gdpr/RETENTION-POLICY.md for scheduling options.
-- ============================================================================

-- ============================================================================
-- 1. DELETE OLD LOGS
-- ============================================================================
-- Deletes logs older than the specified retention period.
--
-- Usage:
--   SELECT delete_old_logs('audit_logs', 90);  -- Delete audit logs older than 90 days
--   SELECT delete_old_logs('access_logs', 30); -- Delete access logs older than 30 days
--   SELECT delete_old_logs('sessions', 7);     -- Delete sessions older than 7 days
--
-- Parameters:
--   p_table_name   - Table name (must exist and have a 'timestamp' column)
--   p_retention_days - Number of days to retain (default: 90)
--
-- Returns: Number of deleted rows
--
-- IMPORTANT: audit_logs has immutability triggers! See note below.
-- ============================================================================

CREATE OR REPLACE FUNCTION delete_old_logs(
    p_table_name TEXT,
    p_retention_days INTEGER DEFAULT 90
)
RETURNS INTEGER
LANGUAGE plpgsql
SECURITY DEFINER  -- Runs with owner privileges (required for audit_logs)
AS $$
DECLARE
    v_deleted_count INTEGER;
    v_cutoff_date TIMESTAMP;
    v_sql TEXT;
BEGIN
    -- Validate retention period
    IF p_retention_days < 1 THEN
        RAISE EXCEPTION 'Retention period must be at least 1 day';
    END IF;

    -- Calculate cutoff date
    v_cutoff_date := NOW() - (p_retention_days || ' days')::INTERVAL;

    -- Validate table exists and has timestamp column
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = p_table_name
        AND column_name = 'timestamp'
        AND table_schema = current_schema()
    ) THEN
        RAISE EXCEPTION 'Table "%" does not exist or has no "timestamp" column', p_table_name;
    END IF;

    -- For audit_logs: Temporarily disable immutability trigger
    -- This is safe because we're only deleting old records, not modifying existing ones
    IF p_table_name = 'audit_logs' THEN
        ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_no_delete;
    END IF;

    -- Build and execute dynamic SQL
    v_sql := format(
        'DELETE FROM %I WHERE timestamp < $1',
        p_table_name
    );

    EXECUTE v_sql USING v_cutoff_date;
    GET DIAGNOSTICS v_deleted_count = ROW_COUNT;

    -- Re-enable trigger for audit_logs
    IF p_table_name = 'audit_logs' THEN
        ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_no_delete;
    END IF;

    -- Log the cleanup action (to a separate cleanup log, not audit_logs)
    RAISE NOTICE 'Deleted % rows from % (older than %)', v_deleted_count, p_table_name, v_cutoff_date;

    RETURN v_deleted_count;

EXCEPTION
    WHEN OTHERS THEN
        -- Re-enable trigger if it was disabled
        IF p_table_name = 'audit_logs' THEN
            ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_no_delete;
        END IF;
        RAISE;
END;
$$;

COMMENT ON FUNCTION delete_old_logs(TEXT, INTEGER) IS
    'Deletes logs older than retention period. GDPR Art. 5(1)(e) - Storage limitation.';

-- ============================================================================
-- 2. ANONYMIZE USER (Right to be forgotten)
-- ============================================================================
-- Anonymizes all user data across tables. GDPR Art. 17 compliance.
--
-- This function:
-- 1. Replaces PII with anonymized values (keeps record structure intact)
-- 2. Logs the anonymization action in audit_logs (for compliance proof)
-- 3. Does NOT delete records (maintains referential integrity)
--
-- Usage:
--   SELECT anonymize_user(123);  -- Anonymize user with ID 123
--   SELECT anonymize_user(123, 'GDPR-REQUEST-2024-001');  -- With reference number
--
-- Parameters:
--   p_user_id      - User ID to anonymize
--   p_reference    - Optional reference number (e.g., GDPR request ID)
--
-- Returns: TRUE if successful, FALSE if user not found
--
-- IMPORTANT: Customize this function for your application's tables!
-- ============================================================================

CREATE OR REPLACE FUNCTION anonymize_user(
    p_user_id INTEGER,
    p_reference TEXT DEFAULT NULL
)
RETURNS BOOLEAN
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
DECLARE
    v_anonymized_email TEXT;
    v_anonymized_name TEXT;
    v_ip_address TEXT := '0.0.0.0';  -- Placeholder for system action
    v_checksum TEXT;
    v_data_json TEXT;
BEGIN
    -- Generate anonymized values
    v_anonymized_email := 'deleted_' || p_user_id || '@anonymized.local';
    v_anonymized_name := 'Deleted User ' || p_user_id;

    -- ========================================================================
    -- ANONYMIZE USERS TABLE
    -- ========================================================================
    -- Anonymize the user record (GDPR Art. 17 - Right to erasure)
    -- This preserves the record structure while removing PII

    UPDATE users SET
        email = v_anonymized_email,
        password_hash = 'DELETED',  -- Prevents login
        name = v_anonymized_name,
        totp_secret = NULL,         -- Clear 2FA data
        totp_enabled = FALSE
    WHERE id = p_user_id;

    IF NOT FOUND THEN
        RETURN FALSE;
    END IF;

    -- ========================================================================
    -- CUSTOMIZE: Add more tables as needed for your application
    -- ========================================================================
    -- Example: Anonymize user's orders (keep order history, remove PII)
    -- UPDATE orders SET
    --     shipping_name = 'DELETED USER',
    --     shipping_address = 'ANONYMIZED',
    --     shipping_phone = NULL,
    --     billing_name = 'DELETED USER',
    --     billing_address = 'ANONYMIZED'
    -- WHERE user_id = p_user_id;
    --
    -- Example: Anonymize user's comments/posts
    -- UPDATE comments SET
    --     author_name = v_anonymized_name
    -- WHERE user_id = p_user_id;
    -- ========================================================================

    -- Log the anonymization action in audit_logs
    v_data_json := json_build_object(
        'action', 'user_anonymized',
        'reference', COALESCE(p_reference, 'N/A'),
        'anonymized_fields', ARRAY['email', 'name', 'password_hash', 'totp_secret'],
        'performed_by', 'system'
    )::TEXT;

    v_checksum := encode(
        digest(
            NOW()::TEXT || 'user.anonymize' || 'user' || p_user_id::TEXT || v_data_json,
            'sha256'
        ),
        'hex'
    );

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
        p_user_id::TEXT,
        v_data_json::BYTEA,  -- Unencrypted for this example; use encrypt_text() in production
        v_checksum
    );

    RAISE NOTICE 'User % anonymized successfully. Reference: %', p_user_id, COALESCE(p_reference, 'N/A');

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'Failed to anonymize user %: %', p_user_id, SQLERRM;
END;
$$;

COMMENT ON FUNCTION anonymize_user(INTEGER, TEXT) IS
    'Anonymizes user data for GDPR Art. 17 (Right to erasure). Customize for your tables!';

-- ============================================================================
-- 3. HELPER: Get retention policy status
-- ============================================================================
-- Returns statistics about tables that may need cleanup.
--
-- Usage:
--   SELECT * FROM get_retention_status();
-- ============================================================================

CREATE OR REPLACE FUNCTION get_retention_status()
RETURNS TABLE (
    table_name TEXT,
    total_rows BIGINT,
    oldest_record TIMESTAMP,
    newest_record TIMESTAMP,
    rows_older_than_90_days BIGINT,
    rows_older_than_365_days BIGINT
)
LANGUAGE plpgsql
AS $$
BEGIN
    -- Check audit_logs
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'audit_logs') THEN
        RETURN QUERY
        SELECT
            'audit_logs'::TEXT,
            COUNT(*)::BIGINT,
            MIN(timestamp),
            MAX(timestamp),
            COUNT(*) FILTER (WHERE timestamp < NOW() - INTERVAL '90 days')::BIGINT,
            COUNT(*) FILTER (WHERE timestamp < NOW() - INTERVAL '365 days')::BIGINT
        FROM audit_logs;
    END IF;

    -- Add more tables as needed:
    -- IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'sessions') THEN
    --     RETURN QUERY
    --     SELECT 'sessions'::TEXT, COUNT(*)::BIGINT, MIN(timestamp), MAX(timestamp),
    --            COUNT(*) FILTER (WHERE timestamp < NOW() - INTERVAL '90 days')::BIGINT,
    --            COUNT(*) FILTER (WHERE timestamp < NOW() - INTERVAL '365 days')::BIGINT
    --     FROM sessions;
    -- END IF;
END;
$$;

COMMENT ON FUNCTION get_retention_status() IS
    'Returns retention statistics for tables. Helps identify cleanup needs.';

-- ============================================================================
-- USAGE EXAMPLES (COMMENTED OUT)
-- ============================================================================

/*
-- Check retention status
SELECT * FROM get_retention_status();

-- Delete audit logs older than 2 years (730 days)
SELECT delete_old_logs('audit_logs', 730);

-- Delete sessions older than 30 days
SELECT delete_old_logs('sessions', 30);

-- Anonymize user (GDPR Art. 17 request)
SELECT anonymize_user(123, 'GDPR-REQ-2024-001');

-- Schedule with pg_cron (requires pg_cron extension - Admin task!)
-- CREATE EXTENSION IF NOT EXISTS pg_cron;
-- SELECT cron.schedule('cleanup-old-logs', '0 3 * * *', $$SELECT delete_old_logs('audit_logs', 730)$$);
-- SELECT cron.schedule('cleanup-sessions', '0 4 * * *', $$SELECT delete_old_logs('sessions', 30)$$);
*/

-- ============================================================================
-- PERMISSIONS
-- ============================================================================
-- Grant execute permissions to application role (customize for your setup)
-- GRANT EXECUTE ON FUNCTION delete_old_logs(TEXT, INTEGER) TO app_role;
-- GRANT EXECUTE ON FUNCTION anonymize_user(INTEGER, TEXT) TO app_role;
-- GRANT EXECUTE ON FUNCTION get_retention_status() TO app_role;
