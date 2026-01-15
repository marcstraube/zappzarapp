/**
 * Tests for AuditLogger
 */

import { describe, it, expect, vi, beforeEach, afterEach, type Mock } from 'vitest';
import { AuditLogger } from '@backend/services/AuditLogger';
import type { Pool, QueryResult } from 'pg';
import * as fs from 'fs/promises';

// Mock pool type with properly typed query method
interface MockPool {
  query: Mock<(...args: unknown[]) => Promise<QueryResult>>;
}

describe('AuditLogger', () => {
  const TEST_KEY = 'test-encryption-key';
  const TEST_LOG_FILE = '/tmp/audit-test.log';
  let mockPool: MockPool;
  let auditLogger: AuditLogger;

  beforeEach(() => {
    // Create mock pool
    mockPool = {
      query: vi.fn(),
    };

    auditLogger = new AuditLogger(mockPool as unknown as Pool, TEST_KEY, TEST_LOG_FILE);
  });

  afterEach(async () => {
    // Clean up test log file
    try {
      await fs.unlink(TEST_LOG_FILE);
    } catch {
      // File might not exist, ignore
    }
  });

  describe('log', () => {
    it('should write log entry to database', async () => {
      const mockQueryResult: QueryResult = {
        rows: [],
        command: 'INSERT',
        rowCount: 1,
        oid: 0,
        fields: [],
      };

      mockPool.query.mockResolvedValueOnce(mockQueryResult);

      await auditLogger.log({
        action: 'user.view',
        entityType: 'user',
        entityId: 123,
        userId: 456,
        ipAddress: '192.168.1.1',
        userAgent: 'test-agent',
      });

      expect(mockPool.query).toHaveBeenCalledWith(
        expect.stringContaining('INSERT INTO audit_logs'),
        expect.arrayContaining([
          expect.any(String), // timestamp
          456, // userId
          '192.168.1.1', // ipAddress
          'user.view', // action
          'user', // entityType
          '123', // entityId
          expect.any(String), // dataJson
          TEST_KEY, // encryption key
          expect.any(String), // checksum
        ])
      );
    });

    it('should write log entry to file', async () => {
      const mockQueryResult: QueryResult = {
        rows: [],
        command: 'INSERT',
        rowCount: 1,
        oid: 0,
        fields: [],
      };

      mockPool.query.mockResolvedValueOnce(mockQueryResult);

      await auditLogger.log({
        action: 'user.delete',
        entityType: 'user',
        entityId: 789,
        userId: 1,
        ipAddress: '10.0.0.1',
        userAgent: 'Mozilla/5.0',
      });

      // Verify file was created
      const fileExists = await fs
        .access(TEST_LOG_FILE)
        .then(() => true)
        .catch(() => false);
      expect(fileExists).toBe(true);

      // Verify file contains JSON
      const contents = await fs.readFile(TEST_LOG_FILE, 'utf-8');
      expect(contents).toBeTruthy();

      const logEntry = JSON.parse(contents.trim());
      expect(logEntry.action).toBe('user.delete');
      expect(logEntry.entity_type).toBe('user');
      expect(logEntry.entity_id).toBe('789');
    });

    it('should handle database errors and write to file as fallback', async () => {
      mockPool.query.mockRejectedValueOnce(new Error('Database connection failed'));

      await expect(
        auditLogger.log({
          action: 'user.view',
          entityType: 'user',
          entityId: 123,
          userId: 456,
        })
      ).rejects.toThrow('Failed to write audit log to database');

      // File should still be written
      const fileExists = await fs
        .access(TEST_LOG_FILE)
        .then(() => true)
        .catch(() => false);
      expect(fileExists).toBe(true);
    });

    it('should include additional data in log entry', async () => {
      const mockQueryResult: QueryResult = {
        rows: [],
        command: 'INSERT',
        rowCount: 1,
        oid: 0,
        fields: [],
      };

      mockPool.query.mockResolvedValueOnce(mockQueryResult);

      await auditLogger.log({
        action: 'user.update',
        entityType: 'user',
        entityId: 123,
        userId: 456,
        data: {
          changed_fields: ['email', 'phone'],
          reason: 'User requested update',
        },
      });

      expect(mockPool.query).toHaveBeenCalled();

      // Verify file contains the additional data
      const contents = await fs.readFile(TEST_LOG_FILE, 'utf-8');
      const logEntry = JSON.parse(contents.trim());
      expect(logEntry.data.changed_fields).toEqual(['email', 'phone']);
      expect(logEntry.data.reason).toBe('User requested update');
    });

    it('should handle null userId', async () => {
      const mockQueryResult: QueryResult = {
        rows: [],
        command: 'INSERT',
        rowCount: 1,
        oid: 0,
        fields: [],
      };

      mockPool.query.mockResolvedValueOnce(mockQueryResult);

      await auditLogger.log({
        action: 'login.failed',
        entityType: 'auth',
        entityId: 0,
        userId: null,
      });

      expect(mockPool.query).toHaveBeenCalledWith(
        expect.any(String),
        expect.arrayContaining([
          expect.any(String), // timestamp
          null, // userId should be null
          expect.any(String), // ipAddress
          'login.failed',
          'auth',
          expect.any(String),
          expect.any(String),
          expect.any(String),
          expect.any(String),
        ])
      );
    });
  });

  describe('logAuth', () => {
    it('should log authentication event', async () => {
      const mockQueryResult: QueryResult = {
        rows: [],
        command: 'INSERT',
        rowCount: 1,
        oid: 0,
        fields: [],
      };

      mockPool.query.mockResolvedValueOnce(mockQueryResult);

      await auditLogger.logAuth('login.success', 123, {}, '192.168.1.1', 'test-agent');

      expect(mockPool.query).toHaveBeenCalledWith(
        expect.any(String),
        expect.arrayContaining([
          expect.any(String),
          123,
          '192.168.1.1',
          'login.success',
          'auth',
          expect.any(String), // entityId (should be 123 as string or 0)
          expect.any(String),
          expect.any(String),
          expect.any(String),
        ])
      );
    });

    it('should handle failed login with null userId', async () => {
      const mockQueryResult: QueryResult = {
        rows: [],
        command: 'INSERT',
        rowCount: 1,
        oid: 0,
        fields: [],
      };

      mockPool.query.mockResolvedValueOnce(mockQueryResult);

      await auditLogger.logAuth('login.failed', null, { email: 'test@example.com' });

      expect(mockPool.query).toHaveBeenCalled();
    });
  });

  describe('logAdmin', () => {
    it('should log administrative action', async () => {
      const mockQueryResult: QueryResult = {
        rows: [],
        command: 'INSERT',
        rowCount: 1,
        oid: 0,
        fields: [],
      };

      mockPool.query.mockResolvedValueOnce(mockQueryResult);

      await auditLogger.logAdmin(
        'role.granted',
        1,
        'user',
        123,
        { role: 'moderator' },
        '10.0.0.1',
        'admin-agent'
      );

      expect(mockPool.query).toHaveBeenCalled();

      // Verify admin_user_id is included in data
      const contents = await fs.readFile(TEST_LOG_FILE, 'utf-8');
      const logEntry = JSON.parse(contents.trim());
      expect(logEntry.data.admin_user_id).toBe(1);
      expect(logEntry.data.role).toBe('moderator');
    });
  });

  describe('getLogsForEntity', () => {
    it('should retrieve logs for specific entity', async () => {
      const mockLogs = [
        {
          id: 1,
          timestamp: new Date('2025-01-09T12:00:00Z'),
          user_id: 456,
          ip_address: '192.168.1.1',
          action: 'user.view',
          entity_type: 'user',
          entity_id: '123',
          data_decrypted: '{"changed_fields":["email"]}',
        },
      ];

      const mockQueryResult: QueryResult = {
        rows: mockLogs,
        command: 'SELECT',
        rowCount: 1,
        oid: 0,
        fields: [],
      };

      mockPool.query.mockResolvedValueOnce(mockQueryResult);

      const logs = await auditLogger.getLogsForEntity('user', 123, 50);

      expect(logs).toHaveLength(1);
      expect(logs[0]!.action).toBe('user.view');
      expect(logs[0]!.entity_id).toBe('123');

      expect(mockPool.query).toHaveBeenCalledWith(
        expect.stringContaining('SELECT'),
        expect.arrayContaining([TEST_KEY, 'user', '123', 50])
      );
    });

    it('should return empty array when no logs found', async () => {
      const mockQueryResult: QueryResult = {
        rows: [],
        command: 'SELECT',
        rowCount: 0,
        oid: 0,
        fields: [],
      };

      mockPool.query.mockResolvedValueOnce(mockQueryResult);

      const logs = await auditLogger.getLogsForEntity('user', 999, 50);

      expect(logs).toHaveLength(0);
    });
  });

  describe('getLogsForUser', () => {
    it('should retrieve logs for specific user', async () => {
      const mockLogs = [
        {
          id: 1,
          timestamp: new Date('2025-01-09T12:00:00Z'),
          user_id: 456,
          ip_address: '192.168.1.1',
          action: 'user.update',
          entity_type: 'user',
          entity_id: '123',
          data_decrypted: '{"changed_fields":["phone"]}',
        },
      ];

      const mockQueryResult: QueryResult = {
        rows: mockLogs,
        command: 'SELECT',
        rowCount: 1,
        oid: 0,
        fields: [],
      };

      mockPool.query.mockResolvedValueOnce(mockQueryResult);

      const logs = await auditLogger.getLogsForUser(456, 50);

      expect(logs).toHaveLength(1);
      expect(logs[0]!.user_id).toBe(456);

      expect(mockPool.query).toHaveBeenCalledWith(
        expect.stringContaining('SELECT'),
        expect.arrayContaining([TEST_KEY, 456, 50])
      );
    });
  });

  describe('edge cases', () => {
    it('should handle very long entity IDs', async () => {
      const mockQueryResult: QueryResult = {
        rows: [],
        command: 'INSERT',
        rowCount: 1,
        oid: 0,
        fields: [],
      };

      mockPool.query.mockResolvedValueOnce(mockQueryResult);

      const longId = '1'.repeat(255);

      await auditLogger.log({
        action: 'user.view',
        entityType: 'user',
        entityId: longId,
        userId: 1,
      });

      expect(mockPool.query).toHaveBeenCalled();
    });

    it('should handle unicode in action names', async () => {
      const mockQueryResult: QueryResult = {
        rows: [],
        command: 'INSERT',
        rowCount: 1,
        oid: 0,
        fields: [],
      };

      mockPool.query.mockResolvedValueOnce(mockQueryResult);

      await auditLogger.log({
        action: 'user.Ändерung', // Unicode characters
        entityType: 'user',
        entityId: 123,
        userId: 1,
      });

      expect(mockPool.query).toHaveBeenCalled();
    });

    it('should handle large data objects', async () => {
      const mockQueryResult: QueryResult = {
        rows: [],
        command: 'INSERT',
        rowCount: 1,
        oid: 0,
        fields: [],
      };

      mockPool.query.mockResolvedValueOnce(mockQueryResult);

      const largeData = {
        array: Array.from({ length: 1000 }, (_, i) => `item-${i}`),
        nested: { deeply: { nested: { object: { value: 'test' } } } },
      };

      await auditLogger.log({
        action: 'user.update',
        entityType: 'user',
        entityId: 123,
        userId: 1,
        data: largeData,
      });

      expect(mockPool.query).toHaveBeenCalled();
    });
  });
});
