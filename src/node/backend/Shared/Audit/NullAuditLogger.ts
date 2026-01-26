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
 * - AUDIT_LOGGING=false|0|disabled -> NullAuditLogger (default)
 * - AUDIT_LOGGING=true|1|enabled -> AuditLogger (real logging)
 *
 * @package Infrastructure/Audit
 */

import type { AuditLoggerInterface, AuditLogEntry, AuditLogResult } from './AuditLoggerInterface';

export class NullAuditLogger implements AuditLoggerInterface {
  /**
   * No-op: Audit logging disabled
   */
  async log(_entry: AuditLogEntry): Promise<void> {
    // No-op
  }

  /**
   * No-op: Audit logging disabled
   */
  async logAuth(
    _action: string,
    _userId: number | null,
    _data?: Record<string, unknown>,
    _ipAddress?: string,
    _userAgent?: string
  ): Promise<void> {
    // No-op
  }

  /**
   * No-op: Audit logging disabled
   */
  async logAdmin(
    _action: string,
    _adminUserId: number,
    _entityType: string,
    _entityId: string | number,
    _data?: Record<string, unknown>,
    _ipAddress?: string,
    _userAgent?: string
  ): Promise<void> {
    // No-op
  }

  /**
   * Returns empty array (no logs stored)
   */
  getLogsForEntity(
    _entityType: string,
    _entityId: string | number,
    _limit?: number
  ): Promise<AuditLogResult[]> {
    return Promise.resolve([]);
  }

  /**
   * Returns empty array (no logs stored)
   */
  getLogsForUser(_userId: number, _limit?: number): Promise<AuditLogResult[]> {
    return Promise.resolve([]);
  }
}
