<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

/**
 * Null Object Audit Logger - No-op implementation
 *
 * Use this for small/private projects where audit logging is not required.
 * Implements AuditLoggerInterface but performs no operations.
 *
 * Benefits:
 * - No conditional null-checks in repository/service code
 * - Clean dependency injection (interface always required)
 * - Easy switch to real AuditLogger when needed
 *
 * Configuration (environment variable):
 * - AUDIT_LOGGING=false|0|disabled → NullAuditLogger (default)
 * - AUDIT_LOGGING=true|1|enabled → AuditLogger (real logging)
 *
 * @package Infrastructure\Audit
 */
final class NullAuditLogger implements AuditLoggerInterface
{
    /**
     * @inheritDoc
     */
    public function log(
        string $action,
        string $entityType,
        string|int $entityId,
        ?int $userId = null,
        array $data = []
    ): void {
        // No-op: Audit logging disabled
    }

    /**
     * @inheritDoc
     */
    public function logAuth(
        string $action,
        ?int $userId = null,
        array $data = []
    ): void {
        // No-op: Audit logging disabled
    }

    /**
     * @inheritDoc
     */
    public function logAdmin(
        string $action,
        int $adminUserId,
        string $entityType,
        string|int $entityId,
        array $data = []
    ): void {
        // No-op: Audit logging disabled
    }

    /**
     * @inheritDoc
     */
    public function getLogsForEntity(
        string $entityType,
        string|int $entityId,
        int $limit = 100
    ): array {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getLogsForUser(
        int $userId,
        int $limit = 100
    ): array {
        return [];
    }
}
