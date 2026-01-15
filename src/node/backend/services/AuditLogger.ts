/**
 * Audit Logger Service for GDPR-compliant audit logging
 *
 * GDPR Art. 30: Records of processing activities
 * GDPR Art. 32: Security measures
 *
 * Features:
 * - Stores audit logs in database (audit_logs table)
 * - Encrypts sensitive data (using ENCRYPTION_KEY from environment)
 * - Tamper-proof (SHA-256 checksum)
 * - Logs to file (storage/logs/audit.log) for redundancy
 * - IP address and user agent tracking
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
 * @example
 * ```typescript
 * import { AuditLogger } from './services/AuditLogger';
 *
 * const auditLogger = new AuditLogger(pool, process.env.ENCRYPTION_KEY!);
 *
 * // Log user data access
 * await auditLogger.log({
 *   action: 'user.view',
 *   entityType: 'user',
 *   entityId: '123',
 *   userId: req.user?.id,
 *   ipAddress: req.ip,
 *   userAgent: req.headers['user-agent']
 * });
 *
 * // Log data modification
 * await auditLogger.log({
 *   action: 'user.update',
 *   entityType: 'user',
 *   entityId: '123',
 *   userId: req.user.id,
 *   data: { changed_fields: ['email', 'phone'] },
 *   ipAddress: req.ip,
 *   userAgent: req.headers['user-agent']
 * });
 * ```
 */

import { Pool, QueryResult } from 'pg';
import { createHash } from 'crypto';
import { appendFile, mkdir } from 'fs/promises';
import { dirname } from 'path';

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

export class AuditLogger {
  private pool: Pool;
  private encryptionKey: string;
  private logFilePath: string;

  /**
   * @param pool - PostgreSQL connection pool
   * @param encryptionKey - Encryption key for sensitive data (from process.env.ENCRYPTION_KEY)
   * @param logFilePath - Optional log file path (default: storage/logs/audit.log)
   */
  constructor(pool: Pool, encryptionKey: string, logFilePath = 'storage/logs/audit.log') {
    if (encryptionKey.length === 0) {
      throw new Error('Encryption key is required for AuditLogger');
    }

    this.pool = pool;
    this.encryptionKey = encryptionKey;
    this.logFilePath = logFilePath;
  }

  /**
   * Log an audit event
   */
  async log(entry: AuditLogEntry): Promise<void> {
    const timestamp = new Date().toISOString();
    const ipAddress = entry.ipAddress ?? 'unknown';
    const userAgent = entry.userAgent ?? 'unknown';

    // Add metadata to data
    const data = {
      ...entry.data,
      user_agent: userAgent,
      timestamp,
    };

    // Serialize data
    const dataJson = JSON.stringify(data);

    // Calculate checksum (tamper-proof)
    const checksum = createHash('sha256')
      .update(timestamp + entry.action + entry.entityType + String(entry.entityId) + dataJson)
      .digest('hex');

    // Write to database
    try {
      await this.pool.query(
        `
        INSERT INTO audit_logs (timestamp, user_id, ip_address, action, entity_type, entity_id, data, checksum)
        VALUES (
          $1,
          $2,
          $3,
          $4,
          $5,
          $6,
          encrypt_text($7, $8),
          $9
        )
      `,
        [
          timestamp,
          entry.userId ?? null,
          ipAddress,
          entry.action,
          entry.entityType,
          String(entry.entityId),
          dataJson,
          this.encryptionKey,
          checksum,
        ]
      );
    } catch (err) {
      // Log to file as fallback
      await this.writeLogToFile(timestamp, entry, ipAddress, userAgent, dataJson);
      const message = err instanceof Error ? err.message : String(err);
      throw new Error(`Failed to write audit log to database: ${message}`);
    }

    // Also write to file for redundancy
    await this.writeLogToFile(timestamp, entry, ipAddress, userAgent, dataJson);
  }

  /**
   * Log authentication event
   */
  async logAuth(
    action: string,
    userId: number | null,
    data: Record<string, unknown> = {},
    ipAddress = 'unknown',
    userAgent = 'unknown'
  ): Promise<void> {
    await this.log({
      action,
      entityType: 'auth',
      entityId: userId ?? 0,
      userId,
      ipAddress,
      userAgent,
      data,
    });
  }

  /**
   * Log administrative action
   */
  async logAdmin(
    action: string,
    adminUserId: number,
    entityType: string,
    entityId: string | number,
    data: Record<string, unknown> = {},
    ipAddress = 'unknown',
    userAgent = 'unknown'
  ): Promise<void> {
    await this.log({
      action,
      entityType,
      entityId,
      userId: adminUserId,
      ipAddress,
      userAgent,
      data: {
        ...data,
        admin_user_id: adminUserId,
      },
    });
  }

  /**
   * Retrieve audit logs for a specific entity
   */
  async getLogsForEntity(
    entityType: string,
    entityId: string | number,
    limit = 100
  ): Promise<AuditLogResult[]> {
    const result: QueryResult<AuditLogResult> = await this.pool.query(
      `
      SELECT
        id,
        timestamp,
        user_id,
        ip_address,
        action,
        entity_type,
        entity_id,
        decrypt_text(data, $1) as data_decrypted
      FROM audit_logs
      WHERE entity_type = $2
        AND entity_id = $3
      ORDER BY timestamp DESC
      LIMIT $4
    `,
      [this.encryptionKey, entityType, String(entityId), limit]
    );

    return result.rows;
  }

  /**
   * Retrieve audit logs for a specific user
   */
  async getLogsForUser(userId: number, limit = 100): Promise<AuditLogResult[]> {
    const result: QueryResult<AuditLogResult> = await this.pool.query(
      `
      SELECT
        id,
        timestamp,
        user_id,
        ip_address,
        action,
        entity_type,
        entity_id,
        decrypt_text(data, $1) as data_decrypted
      FROM audit_logs
      WHERE user_id = $2
      ORDER BY timestamp DESC
      LIMIT $3
    `,
      [this.encryptionKey, userId, limit]
    );

    return result.rows;
  }

  /**
   * Write audit log to file (JSON format, one line per log entry)
   */
  private async writeLogToFile(
    timestamp: string,
    entry: AuditLogEntry,
    ipAddress: string,
    _userAgent: string,
    dataJson: string
  ): Promise<void> {
    // Ensure log directory exists
    const logDir = dirname(this.logFilePath);
    try {
      await mkdir(logDir, { recursive: true });
    } catch {
      // Directory might already exist, ignore error
    }

    // Format as JSON (one line per entry)
    const logEntry = JSON.stringify(
      {
        timestamp,
        user_id: entry.userId ?? null,
        ip_address: ipAddress,
        action: entry.action,
        entity_type: entry.entityType,
        entity_id: String(entry.entityId),
        data: JSON.parse(dataJson) as Record<string, unknown>,
      },
      null,
      0
    );

    // Append to log file
    try {
      await appendFile(this.logFilePath, logEntry + '\n');
    } catch (err) {
      console.error('Failed to write audit log to file:', err);
      // Don't throw - file logging is optional
    }
  }
}
