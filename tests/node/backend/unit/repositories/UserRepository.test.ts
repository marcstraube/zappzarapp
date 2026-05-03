/**
 * Tests for UserRepository
 *
 * Tests user-specific repository operations including email lookup,
 * TOTP secret encryption/decryption, and duplicate email checking.
 */

import { describe, it, expect, vi, beforeEach, afterEach, type Mock } from 'vitest';
import { UserRepository, type User } from '@backend/Shared/Repository/UserRepository';
import type { Row } from '@backend/Shared/Repository/RepositoryInterface';
import { NullAuditLogger } from '@zappzarapp/audit-logger';

// Mock connection interface
interface MockConnection {
  query: Mock<(sql: string, params?: unknown[]) => Promise<Row[]>>;
  execute: Mock<
    (sql: string, params?: unknown[]) => Promise<{ affectedRows: number; insertId?: number }>
  >;
  beginTransaction: Mock<() => Promise<void>>;
  commit: Mock<() => Promise<void>>;
  rollback: Mock<() => Promise<void>>;
  release: Mock<() => void>;
  isPostgres: Mock<() => boolean>;
  quoteIdentifier: Mock<(name: string) => string>;
  param: Mock<(index: number) => string>;
}

// Mock factory interface
interface MockConnectionFactory {
  create: Mock<() => Promise<MockConnection>>;
  isPostgres: Mock<() => boolean>;
  close: Mock<() => Promise<void>>;
}

// Create mock connection
function createMockConnection(isPostgres = true): MockConnection {
  return {
    query: vi.fn().mockResolvedValue([]),
    execute: vi.fn().mockResolvedValue({ affectedRows: 0 }),
    beginTransaction: vi.fn().mockResolvedValue(undefined),
    commit: vi.fn().mockResolvedValue(undefined),
    rollback: vi.fn().mockResolvedValue(undefined),
    release: vi.fn(),
    isPostgres: vi.fn().mockReturnValue(isPostgres),
    quoteIdentifier: vi.fn((name: string) => (isPostgres ? `"${name}"` : `\`${name}\``)),
    param: vi.fn((index: number) => (isPostgres ? `$${index}` : '?')),
  };
}

// Create mock factory
function createMockFactory(isPostgres = true): MockConnectionFactory {
  const mockConnection = createMockConnection(isPostgres);
  return {
    create: vi.fn().mockResolvedValue(mockConnection),
    isPostgres: vi.fn().mockReturnValue(isPostgres),
    close: vi.fn().mockResolvedValue(undefined),
  };
}

// Sample user data
const sampleUser: User = {
  id: 1,
  email: 'user@example.com',
  password_hash: '$2b$10$hash...',
  name: 'Test User',
  totp_secret: null,
  totp_enabled: false,
  created_at: new Date('2026-01-01'),
  updated_at: new Date('2026-01-01'),
};

// Mock the database config module (must be before ConnectionFactory)
vi.mock('@backend/Shared/Database/DatabaseConfig', () => ({
  getDatabaseConfig: vi.fn(() => ({
    type: 'postgres',
    host: 'localhost',
    port: 5432,
    name: 'test',
    user: 'test',
    password: 'test',
  })),
  isPostgres: vi.fn(() => true),
}));

// Mock the ConnectionFactory module
vi.mock('@backend/Shared/Database/ConnectionFactory', async () => {
  const actual = await vi.importActual('@backend/Shared/Database/ConnectionFactory');
  return {
    ...actual,
    getConnectionFactory: vi.fn(),
  };
});

describe('UserRepository', () => {
  let mockFactory: MockConnectionFactory;
  let mockConnection: MockConnection;

  beforeEach(async () => {
    vi.clearAllMocks();
    mockFactory = createMockFactory(true);
    mockConnection = await mockFactory.create();

    // Setup the mock to return our factory
    const { getConnectionFactory } = await import('@backend/Shared/Database/ConnectionFactory');
    vi.mocked(getConnectionFactory).mockReturnValue(mockFactory as never);
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  // =========================================================================
  // findByEmail() Tests
  // =========================================================================

  describe('findByEmail', () => {
    it('should return user when found', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });

      mockConnection.query.mockResolvedValueOnce([sampleUser]);

      const result = await repo.findByEmail('user@example.com');

      expect(result).toEqual(sampleUser);
    });

    it('should return null when not found', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });

      mockConnection.query.mockResolvedValueOnce([]);

      const result = await repo.findByEmail('nonexistent@example.com');

      expect(result).toBeNull();
    });
  });

  // =========================================================================
  // emailExists() Tests
  // =========================================================================

  describe('emailExists', () => {
    it('should return true when email found', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });

      mockConnection.query.mockResolvedValueOnce([{ '?column?': 1 }]);

      const result = await repo.emailExists('user@example.com');

      expect(result).toBe(true);
      expect(mockConnection.query).toHaveBeenCalledWith(
        'SELECT 1 FROM "users" WHERE "email" = $1 LIMIT 1',
        ['user@example.com']
      );
    });

    it('should return false when email not found', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });

      mockConnection.query.mockResolvedValueOnce([]);

      const result = await repo.emailExists('nonexistent@example.com');

      expect(result).toBe(false);
    });

    it('should exclude user id when provided', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });

      mockConnection.query.mockResolvedValueOnce([]);

      const result = await repo.emailExists('user@example.com', 5);

      expect(result).toBe(false);
      expect(mockConnection.query).toHaveBeenCalledWith(
        'SELECT 1 FROM "users" WHERE "email" = $1 AND "id" != $2 LIMIT 1',
        ['user@example.com', 5]
      );
    });

    it('should return false on error', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });

      mockConnection.query.mockRejectedValueOnce(new Error('Query failed'));

      const result = await repo.emailExists('user@example.com');

      expect(result).toBe(false);
    });

    it('should use MySQL syntax when MariaDB', async () => {
      const mariaDbFactory = createMockFactory(false);
      const mariaDbConnection = await mariaDbFactory.create();

      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mariaDbFactory as never,
      });

      mariaDbConnection.query.mockResolvedValueOnce([{ '?column?': 1 }]);

      const result = await repo.emailExists('user@example.com');

      expect(result).toBe(true);
      expect(mariaDbConnection.query).toHaveBeenCalledWith(
        'SELECT 1 FROM `users` WHERE `email` = ? LIMIT 1',
        ['user@example.com']
      );
    });
  });

  // =========================================================================
  // enableTotp() Tests
  // =========================================================================

  describe('enableTotp', () => {
    it('should return true on success', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
        encryptionKey: 'test-key',
      });

      // First call: encrypt query
      mockConnection.query.mockResolvedValueOnce([{ encrypted: 'encrypted_secret' }]);
      // Second call: execute for update
      mockConnection.execute.mockResolvedValueOnce({ affectedRows: 1 });

      const result = await repo.enableTotp(1, 'JBSWY3DPEHPK3PXP');

      expect(result).toBe(true);
      // Verify encrypt was called
      expect(mockConnection.query).toHaveBeenCalledWith(
        'SELECT encrypt_text($1, $2) as encrypted',
        ['JBSWY3DPEHPK3PXP', 'test-key']
      );
    });

    it('should return false when encryption fails', async () => {
      // Clear environment
      const originalEnv = process.env.ENCRYPTION_KEY;
      delete process.env.ENCRYPTION_KEY;

      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });
      const result = await repo.enableTotp(1, 'JBSWY3DPEHPK3PXP');

      expect(result).toBe(false);

      process.env.ENCRYPTION_KEY = originalEnv;
    });

    it('should return false when user not found', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
        encryptionKey: 'test-key',
      });

      // First call: encrypt query
      mockConnection.query.mockResolvedValueOnce([{ encrypted: 'encrypted_secret' }]);
      // Second call: execute for update (no rows affected)
      mockConnection.execute.mockResolvedValueOnce({ affectedRows: 0 });

      const result = await repo.enableTotp(999, 'JBSWY3DPEHPK3PXP');

      expect(result).toBe(false);
    });
  });

  // =========================================================================
  // disableTotp() Tests
  // =========================================================================

  describe('disableTotp', () => {
    it('should return true on success', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });

      mockConnection.execute.mockResolvedValueOnce({ affectedRows: 1 });

      const result = await repo.disableTotp(1);

      expect(result).toBe(true);
      expect(mockConnection.execute).toHaveBeenCalledWith(
        'UPDATE "users" SET "totp_secret" = NULL, "totp_enabled" = $1 WHERE "id" = $2',
        [false, 1]
      );
    });

    it('should return false when user not found', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });

      mockConnection.execute.mockResolvedValueOnce({ affectedRows: 0 });

      const result = await repo.disableTotp(999);

      expect(result).toBe(false);
    });

    it('should return false on error', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });

      mockConnection.execute.mockRejectedValueOnce(new Error('Update failed'));

      const result = await repo.disableTotp(1);

      expect(result).toBe(false);
    });

    it('should use MySQL syntax with numeric boolean', async () => {
      const mariaDbFactory = createMockFactory(false);
      const mariaDbConnection = await mariaDbFactory.create();

      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mariaDbFactory as never,
      });

      mariaDbConnection.execute.mockResolvedValueOnce({ affectedRows: 1 });

      const result = await repo.disableTotp(1);

      expect(result).toBe(true);
      expect(mariaDbConnection.execute).toHaveBeenCalledWith(
        'UPDATE `users` SET `totp_secret` = NULL, `totp_enabled` = ? WHERE `id` = ?',
        [0, 1]
      );
    });
  });

  // =========================================================================
  // getTotpSecret() Tests
  // =========================================================================

  describe('getTotpSecret', () => {
    it('should return decrypted secret', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
        encryptionKey: 'test-key',
      });

      // First call: select query
      mockConnection.query.mockResolvedValueOnce([{ totp_secret: 'encrypted_secret' }]);
      // Second call: decrypt query
      mockConnection.query.mockResolvedValueOnce([{ decrypted: 'JBSWY3DPEHPK3PXP' }]);

      const result = await repo.getTotpSecret(1);

      expect(result).toBe('JBSWY3DPEHPK3PXP');
      // Verify decrypt was called
      expect(mockConnection.query).toHaveBeenCalledWith(
        'SELECT decrypt_text($1, $2) as decrypted',
        ['encrypted_secret', 'test-key']
      );
    });

    it('should return null when totp not enabled', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });

      mockConnection.query.mockResolvedValueOnce([]);

      const result = await repo.getTotpSecret(1);

      expect(result).toBeNull();
    });

    it('should return null when secret is null', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });

      mockConnection.query.mockResolvedValueOnce([{ totp_secret: null }]);

      const result = await repo.getTotpSecret(1);

      expect(result).toBeNull();
    });

    it('should return null on error', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });

      mockConnection.query.mockRejectedValueOnce(new Error('Query failed'));

      const result = await repo.getTotpSecret(1);

      expect(result).toBeNull();
    });
  });

  // =========================================================================
  // Table/Fields Configuration Tests
  // =========================================================================

  describe('configuration', () => {
    it('should use users table', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });

      mockConnection.query.mockResolvedValueOnce([sampleUser]);

      await repo.find(1);

      expect(mockConnection.query).toHaveBeenCalledWith(expect.stringContaining('"users"'), [1]);
    });

    it('should encrypt totp_secret field', async () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
        encryptionKey: 'test-key',
      });

      // First call: encrypt query
      mockConnection.query.mockResolvedValueOnce([{ encrypted: 'encrypted_value' }]);
      // Second call: insert query (PostgreSQL uses RETURNING)
      mockConnection.query.mockResolvedValueOnce([{ id: 1 }]);

      await repo.insert({
        email: 'test@example.com',
        password_hash: 'hash',
        totp_secret: 'my-secret',
      });

      // Verify encrypt was called for totp_secret
      expect(mockConnection.query).toHaveBeenCalledWith(
        'SELECT encrypt_text($1, $2) as encrypted',
        ['my-secret', 'test-key']
      );
    });
  });

  // =========================================================================
  // Interface Compliance Tests
  // =========================================================================

  describe('interface compliance', () => {
    it('should implement UserRepositoryInterface', () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });

      // Type check - these should all exist
      expect(typeof repo.findByEmail).toBe('function');
      expect(typeof repo.emailExists).toBe('function');
      expect(typeof repo.enableTotp).toBe('function');
      expect(typeof repo.disableTotp).toBe('function');
      expect(typeof repo.getTotpSecret).toBe('function');
    });

    it('should inherit from AbstractRepository', () => {
      const repo = new UserRepository({
        auditLogger: new NullAuditLogger(),
        connectionFactory: mockFactory as never,
      });

      // Inherited methods should exist
      expect(typeof repo.find).toBe('function');
      expect(typeof repo.findAll).toBe('function');
      expect(typeof repo.findBy).toBe('function');
      expect(typeof repo.insert).toBe('function');
      expect(typeof repo.update).toBe('function');
      expect(typeof repo.delete).toBe('function');
      expect(typeof repo.exists).toBe('function');
      expect(typeof repo.count).toBe('function');
    });
  });
});
