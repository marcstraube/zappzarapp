<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

use RuntimeException;

/**
 * Convenience Trait for Audit Logging
 *
 * Provides easy access to audit logging functionality in any class.
 *
 * Usage:
 * <code>
 * class UserService
 * {
 *     use HasAuditLogging;
 *
 *     private AuditLoggerInterface $auditLogger;
 *
 *     public function __construct(AuditLoggerInterface $auditLogger)
 *     {
 *         $this->auditLogger = $auditLogger;
 *     }
 *
 *     public function updateUser(int $userId, array $data): void
 *     {
 *         // Update user...
 *
 *         // Log audit event
 *         $this->auditLog(
 *             action: 'user.update',
 *             entityType: 'user',
 *             entityId: $userId,
 *             data: ['changed_fields' => array_keys($data)]
 *         );
 *     }
 * }
 * </code>
 *
 * @package Infrastructure\Audit
 */
trait HasAuditLogging
{
    /**
     * Log an audit event
     *
     * @param string $action Action performed (e.g., 'user.view', 'user.update')
     * @param string $entityType Entity type (e.g., 'user', 'order')
     * @param string|int $entityId Entity ID
     * @param int|null $userId User who performed the action (defaults to current session user)
     * @param array<string, mixed> $data Additional data
     * @return void
     */
    protected function auditLog(
        string $action,
        string $entityType,
        string|int $entityId,
        ?int $userId = null,
        array $data = []
    ): void {
        if (!isset($this->auditLogger)) {
            throw new RuntimeException('AuditLogger not initialized. Did you forget to inject AuditLoggerInterface?');
        }

        // If userId not provided, try to get from session
        if ($userId === null && isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
        }

        $this->auditLogger->log($action, $entityType, $entityId, $userId, $data);
    }

    /**
     * Log authentication event
     *
     * @param string $action Action performed (e.g., 'login.success', 'login.failed')
     * @param int|null $userId User ID
     * @param array<string, mixed> $data Additional data
     * @return void
     */
    protected function auditLogAuth(
        string $action,
        ?int $userId = null,
        array $data = []
    ): void {
        if (!isset($this->auditLogger)) {
            throw new RuntimeException('AuditLogger not initialized. Did you forget to inject AuditLoggerInterface?');
        }

        $this->auditLogger->logAuth($action, $userId, $data);
    }

    /**
     * Log administrative action
     *
     * @param string $action Action performed (e.g., 'role.granted')
     * @param int $adminUserId Administrator who performed the action
     * @param string $entityType Entity type affected
     * @param string|int $entityId Entity ID affected
     * @param array<string, mixed> $data Additional data
     * @return void
     */
    protected function auditLogAdmin(
        string $action,
        int $adminUserId,
        string $entityType,
        string|int $entityId,
        array $data = []
    ): void {
        if (!isset($this->auditLogger)) {
            throw new RuntimeException('AuditLogger not initialized. Did you forget to inject AuditLoggerInterface?');
        }

        $this->auditLogger->logAdmin($action, $adminUserId, $entityType, $entityId, $data);
    }
}
