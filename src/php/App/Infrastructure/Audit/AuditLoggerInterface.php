<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

/**
 * Audit Logger Interface for GDPR-compliant audit logging
 *
 * GDPR Art. 30: Records of processing activities
 * GDPR Art. 32: Security measures (logging access to personal data)
 *
 * Required for:
 * - GDPR Art. 15: Right of access (who accessed my data?)
 * - GDPR Art. 17: Right to erasure (audit trail of deletion)
 * - GDPR Art. 33: Breach notification (what data was accessed?)
 * - Security audits and compliance reviews
 *
 * When to log:
 * ✓ Access to personal data (read, view, export)
 * ✓ Modification of personal data (create, update, delete)
 * ✓ Authentication events (login, logout, password change)
 * ✓ Administrative actions (role changes, permissions)
 * ✓ Failed access attempts (security monitoring)
 *
 * When NOT to log:
 * ✗ Public data access (no personal information)
 * ✗ System-level operations (not related to user data)
 * ✗ High-frequency operations (e.g., page views - use analytics instead)
 *
 * @package Infrastructure\Audit
 */
interface AuditLoggerInterface
{
    /**
     * Log an audit event
     *
     * @param string $action Action performed (e.g., 'user.view', 'user.update', 'user.delete')
     * @param string $entityType Entity type (e.g., 'user', 'order', 'invoice')
     * @param string|int $entityId Entity ID (primary key)
     * @param int|null $userId User who performed the action (null for system actions)
     * @param array<string, mixed> $data Additional data (optional, will be encrypted)
     * @return void
     */
    public function log(
        string $action,
        string $entityType,
        string|int $entityId,
        ?int $userId = null,
        array $data = []
    ): void;

    /**
     * Log authentication event
     *
     * @param string $action Action performed (e.g., 'login.success', 'login.failed', 'logout')
     * @param int|null $userId User ID (null for failed login attempts)
     * @param array<string, mixed> $data Additional data (e.g., IP address, user agent)
     * @return void
     */
    public function logAuth(
        string $action,
        ?int $userId = null,
        array $data = []
    ): void;

    /**
     * Log administrative action
     *
     * @param string $action Action performed (e.g., 'role.granted', 'permission.revoked')
     * @param int $adminUserId Administrator who performed the action
     * @param string $entityType Entity type affected
     * @param string|int $entityId Entity ID affected
     * @param array<string, mixed> $data Additional data
     * @return void
     */
    public function logAdmin(
        string $action,
        int $adminUserId,
        string $entityType,
        string|int $entityId,
        array $data = []
    ): void;

    /**
     * Retrieve audit logs for a specific entity
     *
     * @param string $entityType Entity type
     * @param string|int $entityId Entity ID
     * @param int $limit Maximum number of logs to retrieve
     * @return array<int, array<string, mixed>> Array of audit log entries
     */
    public function getLogsForEntity(
        string $entityType,
        string|int $entityId,
        int $limit = 100
    ): array;

    /**
     * Retrieve audit logs for a specific user
     *
     * @param int $userId User ID
     * @param int $limit Maximum number of logs to retrieve
     * @return array<int, array<string, mixed>> Array of audit log entries
     */
    public function getLogsForUser(
        int $userId,
        int $limit = 100
    ): array;
}
