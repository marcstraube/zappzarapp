<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

use RuntimeException;
use Zappzarapp\AuditLogger\AuditLogEntry;

/**
 * Convenience Trait for Service-Layer Audit Logging
 *
 * Provides easy access to audit logging functionality in SERVICE classes.
 *
 * Note: Do NOT use this trait in Repositories!
 * Repositories use AuditLoggerInterface directly via AbstractPdoRepository,
 * which automatically logs all CRUD operations (insert, update, delete).
 *
 * This trait is specifically for:
 * - Authentication events (login, logout, password change)
 * - Administrative actions (role changes, permission grants)
 * - Business logic events that don't involve direct CRUD
 *
 * Usage:
 * <code>
 * class AuthService
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
 *     public function login(string $email, string $password): bool
 *     {
 *         // Authenticate user...
 *
 *         // Log authentication event
 *         $this->auditLogAuth(
 *             action: 'login.success',
 *             userId: $userId,
 *             data: ['ip' => $_SERVER['REMOTE_ADDR']]
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

        $this->auditLogger->log(new AuditLogEntry(
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            userId: $userId,
            ipAddress: $this->getClientIp(),
            userAgent: $this->getClientUserAgent(),
            data: $data,
        ));
    }

    /**
     * Log authentication event
     *
     * @param string $action Action performed (e.g., 'login.success', 'login.failed')
     * @param int|null $userId User ID
     * @param array<string, mixed> $data Additional data
     */
    protected function auditLogAuth(
        string $action,
        ?int $userId = null,
        array $data = []
    ): void {
        if (!isset($this->auditLogger)) {
            throw new RuntimeException('AuditLogger not initialized. Did you forget to inject AuditLoggerInterface?');
        }

        $this->auditLogger->logAuth($action, $userId, $data, $this->getClientIp(), $this->getClientUserAgent());
    }

    /**
     * Log administrative action
     *
     * @param string $action Action performed (e.g., 'role.granted')
     * @param int $adminUserId Administrator who performed the action
     * @param string $entityType Entity type affected
     * @param string|int $entityId Entity ID affected
     * @param array<string, mixed> $data Additional data
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

        $this->auditLogger->logAdmin($action, $adminUserId, $entityType, $entityId, $data, $this->getClientIp(), $this->getClientUserAgent());
    }

    /**
     * Get client IP address from request
     *
     * @SuppressWarnings("PHPMD.Superglobals") Required for request context
     */
    private function getClientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get client User-Agent from request
     *
     * @SuppressWarnings("PHPMD.Superglobals") Required for request context
     */
    private function getClientUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
}
