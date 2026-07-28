/**
 * Tests for AbstractRepository
 *
 * Tests the base repository functionality including CRUD operations,
 * transactions, encryption, and cross-database support.
 */

import { describe, it, expect, vi, beforeEach, afterEach, type Mock } from 'vitest';
import { AbstractRepository } from '@backend/Shared/Repository/AbstractRepository';
import type { Row } from '@backend/Shared/Repository/RepositoryInterface';
import { NullAuditLogger } from '@zappzarapp/audit-logger';

// Mock connection interface - uses simple Mock type to avoid generic issues with vi.fn()
interface MockConnection {
  query: Mock;
  execute: Mock;
  beginTransaction: Mock;
  commit: Mock;
  rollback: Mock;
  release: Mock;
  isPostgres: Mock;
  quoteIdentifier: Mock;
  param: Mock;
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

// Concrete implementation for testing
interface TestEntity extends Row {
  id: number;
  name: string;
  email: string;
  secret?: string;
}

class TestRepository extends AbstractRepository<TestEntity> {
  protected getTable(): string {
    return 'test_table';
  }
}

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

describe('AbstractRepository', () => {
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

  describe('constructor', () => {
    it('should use default primary key', () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      // Access protected method via type assertion
      expect((repo as unknown as { getPrimaryKey: () => string }).getPrimaryKey()).toBe('id');
    });

    it('should accept custom primary key', () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
        primaryKey: 'custom_id',
      });

      expect((repo as unknown as { getPrimaryKey: () => string }).getPrimaryKey()).toBe(
        'custom_id'
      );
    });

    it('should accept encryption key', () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
        encryptionKey: 'my-secret-key',
      });

      expect(repo.getEncryptionKeyForTesting()).toBe('my-secret-key');
    });
  });

  describe('find', () => {
    it('should return entity when found', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });
      const testEntity: TestEntity = { id: 1, name: 'Test', email: 'test@example.com' };

      mockConnection.query.mockResolvedValueOnce([testEntity]);

      const result = await repo.find(1);

      expect(result).toEqual(testEntity);
      expect(mockConnection.query).toHaveBeenCalledWith(
        'SELECT * FROM "test_table" WHERE "id" = $1 LIMIT 1',
        [1]
      );
    });

    it('should return null when not found', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockConnection.query.mockResolvedValueOnce([]);

      const result = await repo.find(999);

      expect(result).toBeNull();
    });
  });

  describe('findAll', () => {
    it('should return all entities', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });
      const testEntities: TestEntity[] = [
        { id: 1, name: 'Test1', email: 'test1@example.com' },
        { id: 2, name: 'Test2', email: 'test2@example.com' },
      ];

      mockConnection.query.mockResolvedValueOnce(testEntities);

      const result = await repo.findAll();

      expect(result).toEqual(testEntities);
      expect(mockConnection.query).toHaveBeenCalledWith('SELECT * FROM "test_table"', []);
    });

    it('should support limit and offset', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockConnection.query.mockResolvedValueOnce([]);

      await repo.findAll(10, 5);

      expect(mockConnection.query).toHaveBeenCalledWith(
        'SELECT * FROM "test_table" LIMIT $1 OFFSET $2',
        [10, 5]
      );
    });
  });

  describe('findBy', () => {
    it('should return entities matching criteria', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });
      const testEntity: TestEntity = { id: 1, name: 'Test', email: 'test@example.com' };

      mockConnection.query.mockResolvedValueOnce([testEntity]);

      const result = await repo.findBy({ email: 'test@example.com' });

      expect(result).toEqual([testEntity]);
      expect(mockConnection.query).toHaveBeenCalledWith(
        'SELECT * FROM "test_table" WHERE "email" = $1',
        ['test@example.com']
      );
    });
  });

  describe('insert', () => {
    it('should insert entity and return id (PostgreSQL)', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockConnection.query.mockResolvedValueOnce([{ id: 42 }]);

      const result = await repo.insert({ name: 'New', email: 'new@example.com' });

      expect(result).toBe(42);
      expect(mockConnection.query).toHaveBeenCalledWith(
        'INSERT INTO "test_table" ("name", "email") VALUES ($1, $2) RETURNING "id"',
        ['New', 'new@example.com']
      );
    });

    it('should return null for empty data', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      const result = await repo.insert({});

      expect(result).toBeNull();
    });
  });

  describe('update', () => {
    it('should update entity and return true on success', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockConnection.execute.mockResolvedValueOnce({ affectedRows: 1 });

      const result = await repo.update(1, { name: 'Updated' });

      expect(result).toBe(true);
      expect(mockConnection.execute).toHaveBeenCalledWith(
        'UPDATE "test_table" SET "name" = $1 WHERE "id" = $2',
        ['Updated', 1]
      );
    });

    it('should return false when no rows affected', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockConnection.execute.mockResolvedValueOnce({ affectedRows: 0 });

      const result = await repo.update(999, { name: 'Updated' });

      expect(result).toBe(false);
    });

    it('should return false for empty data', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      const result = await repo.update(1, {});

      expect(result).toBe(false);
    });
  });

  describe('delete', () => {
    it('should delete entity and return true on success', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockConnection.execute.mockResolvedValueOnce({ affectedRows: 1 });

      const result = await repo.delete(1);

      expect(result).toBe(true);
      expect(mockConnection.execute).toHaveBeenCalledWith(
        'DELETE FROM "test_table" WHERE "id" = $1',
        [1]
      );
    });

    it('should return false when no rows affected', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockConnection.execute.mockResolvedValueOnce({ affectedRows: 0 });

      const result = await repo.delete(999);

      expect(result).toBe(false);
    });
  });

  describe('exists', () => {
    it('should return true when entity exists', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockConnection.query.mockResolvedValueOnce([{ '1': 1 }]);

      const result = await repo.exists(1);

      expect(result).toBe(true);
    });

    it('should return false when entity does not exist', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockConnection.query.mockResolvedValueOnce([]);

      const result = await repo.exists(999);

      expect(result).toBe(false);
    });
  });

  describe('count', () => {
    it('should return count without criteria', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockConnection.query.mockResolvedValueOnce([{ count: 42 }]);

      const result = await repo.count();

      expect(result).toBe(42);
      expect(mockConnection.query).toHaveBeenCalledWith(
        'SELECT COUNT(*) as count FROM "test_table"',
        []
      );
    });

    it('should return count with criteria', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockConnection.query.mockResolvedValueOnce([{ count: 5 }]);

      const result = await repo.count({ name: 'Test' });

      expect(result).toBe(5);
      expect(mockConnection.query).toHaveBeenCalledWith(
        'SELECT COUNT(*) as count FROM "test_table" WHERE "name" = $1',
        ['Test']
      );
    });
  });

  describe('transactions', () => {
    it('should begin transaction', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      const result = await repo.beginTransaction();

      expect(result).toBe(true);
      expect(mockConnection.beginTransaction).toHaveBeenCalled();
      expect(repo.isInTransaction()).toBe(true);
    });

    it('should commit transaction', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      await repo.beginTransaction();
      const result = await repo.commit();

      expect(result).toBe(true);
      expect(mockConnection.commit).toHaveBeenCalled();
      expect(repo.isInTransaction()).toBe(false);
    });

    it('should rollback transaction', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      await repo.beginTransaction();
      const result = await repo.rollback();

      expect(result).toBe(true);
      expect(mockConnection.rollback).toHaveBeenCalled();
      expect(repo.isInTransaction()).toBe(false);
    });
  });

  describe('isAvailable', () => {
    it('should return true when connection is available', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockConnection.query.mockResolvedValueOnce([{ '1': 1 }]);

      const result = await repo.isAvailable();

      expect(result).toBe(true);
    });

    it('should return false when connection fails', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockConnection.query.mockRejectedValueOnce(new Error('Connection failed'));

      const result = await repo.isAvailable();

      expect(result).toBe(false);
    });
  });

  describe('MariaDB support', () => {
    it('should use correct SQL syntax for MariaDB', async () => {
      const mariaDbFactory = createMockFactory(false);
      const mariaDbConnection = await mariaDbFactory.create();

      const repo = new TestRepository({
        connectionFactory: mariaDbFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mariaDbConnection.query.mockResolvedValueOnce([]);

      await repo.findAll(10, 5);

      expect(mariaDbConnection.query).toHaveBeenCalledWith(
        'SELECT * FROM `test_table` LIMIT ? OFFSET ?',
        [10, 5]
      );
    });

    it('should use execute for insert in MariaDB', async () => {
      const mariaDbFactory = createMockFactory(false);
      const mariaDbConnection = await mariaDbFactory.create();

      const repo = new TestRepository({
        connectionFactory: mariaDbFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mariaDbConnection.execute.mockResolvedValueOnce({ affectedRows: 1, insertId: 42 });

      const result = await repo.insert({ name: 'New', email: 'new@example.com' });

      expect(result).toBe(42);
      expect(mariaDbConnection.execute).toHaveBeenCalledWith(
        'INSERT INTO `test_table` (`name`, `email`) VALUES (?, ?)',
        ['New', 'new@example.com']
      );
    });

    it('should return null when MariaDB reports no insertId', async () => {
      const mariaDbFactory = createMockFactory(false);
      const mariaDbConnection = await mariaDbFactory.create();

      const repo = new TestRepository({
        connectionFactory: mariaDbFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mariaDbConnection.execute.mockResolvedValueOnce({ affectedRows: 1 });

      const result = await repo.insert({ name: 'New', email: 'new@example.com' });

      expect(result).toBeNull();
    });
  });

  describe('count edge cases', () => {
    it('should parse a string count into a number', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockConnection.query.mockResolvedValueOnce([{ count: '7' }]);

      expect(await repo.count()).toBe(7);
    });

    it('should fall back to 0 for an unparseable count', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockConnection.query.mockResolvedValueOnce([{ count: 'not-a-number' }]);

      expect(await repo.count()).toBe(0);
    });
  });

  describe('transaction error handling', () => {
    it('should return false from beginTransaction when acquiring a connection throws', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      mockFactory.create.mockRejectedValueOnce(new Error('no connection'));

      expect(await repo.beginTransaction()).toBe(false);
    });

    it('should return false when committing without an active transaction', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      expect(await repo.commit()).toBe(false);
    });

    it('should return false when rolling back without an active transaction', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      expect(await repo.rollback()).toBe(false);
    });

    it('should return false when commit throws', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      await repo.beginTransaction();
      mockConnection.commit.mockRejectedValueOnce(new Error('commit failed'));

      expect(await repo.commit()).toBe(false);
    });

    it('should return false when rollback throws', async () => {
      const repo = new TestRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
      });

      await repo.beginTransaction();
      mockConnection.rollback.mockRejectedValueOnce(new Error('rollback failed'));

      expect(await repo.rollback()).toBe(false);
    });
  });

  describe('encryption', () => {
    class EncryptedRepository extends AbstractRepository<TestEntity> {
      protected getTable(): string {
        return 'test_table';
      }
      protected getEncryptedFields(): string[] {
        return ['secret'];
      }
    }

    it('should encrypt flagged fields on insert when a key is configured', async () => {
      const repo = new EncryptedRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
        encryptionKey: 'key',
      });

      // 1st query: encrypt_text; 2nd query: INSERT ... RETURNING id
      mockConnection.query
        .mockResolvedValueOnce([{ encrypted: 'ENC' }])
        .mockResolvedValueOnce([{ id: 1 }]);

      const result = await repo.insert({ name: 'N', email: 'e', secret: 'plain' });

      expect(result).toBe(1);
      expect(mockConnection.query).toHaveBeenNthCalledWith(
        1,
        expect.stringContaining('encrypt_text'),
        ['plain', 'key']
      );
    });

    it('should decrypt flagged fields when reading a row', async () => {
      const repo = new EncryptedRepository({
        connectionFactory: mockFactory as never,
        auditLogger: new NullAuditLogger(),
        encryptionKey: 'key',
      });

      mockConnection.query
        .mockResolvedValueOnce([{ id: 1, name: 'N', email: 'e', secret: 'ENC' }])
        .mockResolvedValueOnce([{ decrypted: 'plain' }]);

      const row = await repo.find(1);

      expect(row?.secret).toBe('plain');
      expect(mockConnection.query).toHaveBeenNthCalledWith(
        2,
        expect.stringContaining('decrypt_text'),
        ['ENC', 'key']
      );
    });

    it('should leave flagged fields untouched when no key is configured', async () => {
      // Force "no key" deterministically: without an explicit key the repository
      // falls back to loadEncryptionKey(), which reads ENCRYPTION_KEY / the secret
      // file. CI sets those, which would enable encryption and change the flow —
      // so clear the env var and point the key file at a non-existent path.
      const originalKey = process.env.ENCRYPTION_KEY;
      const originalKeyFile = process.env.ENCRYPTION_KEY_FILE;
      delete process.env.ENCRYPTION_KEY;
      process.env.ENCRYPTION_KEY_FILE = '/nonexistent/encryption_key.txt';

      try {
        const repo = new EncryptedRepository({
          connectionFactory: mockFactory as never,
          auditLogger: new NullAuditLogger(),
        });

        mockConnection.query.mockResolvedValueOnce([{ id: 1 }]);

        const result = await repo.insert({ name: 'N', email: 'e', secret: 'plain' });

        expect(result).toBe(1);
        // Only the INSERT runs — no encrypt_text round-trip without a key
        expect(mockConnection.query).toHaveBeenCalledTimes(1);
      } finally {
        if (originalKey === undefined) delete process.env.ENCRYPTION_KEY;
        else process.env.ENCRYPTION_KEY = originalKey;
        if (originalKeyFile === undefined) delete process.env.ENCRYPTION_KEY_FILE;
        else process.env.ENCRYPTION_KEY_FILE = originalKeyFile;
      }
    });
  });
});
