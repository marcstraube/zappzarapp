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
 * @package Infrastructure/Audit
 */

export interface AuditLogEntry {
  action: string;
  entityType: string;
  entityId: string | number;
  userId?: number | null;
  ipAddress?: string;
  userAgent?: string;
  data?: Record<string, unknown>;
}

export interface AuditLogResult {
  id: number;
  timestamp: Date;
  user_id: number | null;
  ip_address: string;
  action: string;
  entity_type: string;
  entity_id: string;
  data_decrypted: string | null;
}

/**
 * Audit Logger Interface
 *
 * Implementations:
 * - AuditLogger: Full GDPR-compliant logging to database and file
 * - NullAuditLogger: No-op for small/private projects
 */
export interface AuditLoggerInterface {
  /**
   * Log an audit event
   */
  log(entry: AuditLogEntry): Promise<void>;

  /**
   * Log authentication event
   */
  logAuth(
    action: string,
    userId: number | null,
    data?: Record<string, unknown>,
    ipAddress?: string,
    userAgent?: string
  ): Promise<void>;

  /**
   * Log administrative action
   */
  logAdmin(
    action: string,
    adminUserId: number,
    entityType: string,
    entityId: string | number,
    data?: Record<string, unknown>,
    ipAddress?: string,
    userAgent?: string
  ): Promise<void>;

  /**
   * Retrieve audit logs for a specific entity
   */
  getLogsForEntity(
    entityType: string,
    entityId: string | number,
    limit?: number
  ): Promise<AuditLogResult[]>;

  /**
   * Retrieve audit logs for a specific user
   */
  getLogsForUser(userId: number, limit?: number): Promise<AuditLogResult[]>;
}
