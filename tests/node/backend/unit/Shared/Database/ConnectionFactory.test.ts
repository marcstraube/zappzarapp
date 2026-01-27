/**
 * Tests for Database Connection Factory
 *
 * Coverage target: ~65% of ConnectionFactory.ts (218 lines)
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { ConnectionFactory } from '@backend/Shared/Database/ConnectionFactory';
import { createMockPgPool, createMockMySqlPool } from '../../../fixtures/mockConnections';

// Mock the database config
vi.mock('@backend/Shared/Database/DatabaseConfig', () => ({
  getDatabaseConfig: vi.fn(() => ({
    type: 'postgres',
    host: 'localhost',
    port: 5432,
    name: 'test_db',
    user: 'test_user',
    password: 'test_password',
  })),
  isPostgres: vi.fn(() => true),
}));

describe('ConnectionFactory', () => {
  const originalEnv = { ...process.env };

  beforeEach(() => {
    process.env = { ...originalEnv };
    vi.clearAllMocks();
  });

  afterEach(() => {
    process.env = originalEnv;
  });

  describe('Constructor', () => {
    it('should create factory with default options', () => {
      const factory = new ConnectionFactory();
      expect(factory).toBeDefined();
      expect(factory.isPostgres()).toBe(true);
    });

    it('should create factory with explicit PostgreSQL type', () => {
      const factory = new ConnectionFactory({ dbType: 'postgres' });
      expect(factory.isPostgres()).toBe(true);
    });

    it('should create factory with explicit MySQL type', () => {
      const factory = new ConnectionFactory({ dbType: 'mysql' });
      expect(factory.isPostgres()).toBe(false);
    });

    it('should accept injected pool for testing', () => {
      const mockPool = createMockPgPool();
      const factory = new ConnectionFactory({ pool: mockPool });
      expect(factory).toBeDefined();
    });

    it('should detect database type from config when not specified', async () => {
      const { isPostgres } = await import('@backend/Shared/Database/DatabaseConfig');
      vi.mocked(isPostgres).mockReturnValue(true);

      const factory = new ConnectionFactory();
      expect(factory.isPostgres()).toBe(true);
    });
  });

  describe('PostgreSQL Connection', () => {
    it('should create PostgreSQL connection', async () => {
      const mockPool = createMockPgPool();
      const factory = new ConnectionFactory({ dbType: 'postgres', pool: mockPool });

      const connection = await factory.create();

      expect(connection).toBeDefined();
      expect(mockPool.connect).toHaveBeenCalled();
    });

    it('should wrap pg client with DatabaseConnection interface', async () => {
      const mockPool = createMockPgPool();
      const factory = new ConnectionFactory({ dbType: 'postgres', pool: mockPool });

      const connection = await factory.create();

      expect(connection).toHaveProperty('query');
      expect(connection).toHaveProperty('execute');
      expect(connection).toHaveProperty('beginTransaction');
      expect(connection).toHaveProperty('commit');
      expect(connection).toHaveProperty('rollback');
      expect(connection).toHaveProperty('release');
    });

    it('should support extended connection methods', async () => {
      const mockPool = createMockPgPool();
      const factory = new ConnectionFactory({ dbType: 'postgres', pool: mockPool });

      const connection = await factory.create();

      expect(connection.isPostgres()).toBe(true);
      expect(connection).toHaveProperty('quoteIdentifier');
      expect(connection).toHaveProperty('param');
    });

    it('should use PostgreSQL parameter placeholders ($1, $2)', async () => {
      const mockPool = createMockPgPool();
      const factory = new ConnectionFactory({ dbType: 'postgres', pool: mockPool });

      const connection = await factory.create();

      expect(connection.param(1)).toBe('$1');
      expect(connection.param(2)).toBe('$2');
      expect(connection.param(10)).toBe('$10');
    });

    it('should quote PostgreSQL identifiers with double quotes', async () => {
      const mockPool = createMockPgPool();
      const factory = new ConnectionFactory({ dbType: 'postgres', pool: mockPool });

      const connection = await factory.create();

      expect(connection.quoteIdentifier('users')).toBe('"users"');
      expect(connection.quoteIdentifier('table"with"quotes')).toBe('"table""with""quotes"');
    });

    it('should execute queries with PostgreSQL client', async () => {
      const mockPool = createMockPgPool();
      const factory = new ConnectionFactory({ dbType: 'postgres', pool: mockPool });

      const connection = await factory.create();
      await connection.query('SELECT * FROM users');

      const mockClient = await mockPool.connect();
      expect(mockClient.query).toHaveBeenCalledWith('SELECT * FROM users', undefined);
    });

    it('should execute statements with PostgreSQL client', async () => {
      const mockPool = createMockPgPool();
      const factory = new ConnectionFactory({ dbType: 'postgres', pool: mockPool });

      const connection = await factory.create();
      await connection.execute('INSERT INTO users (name) VALUES ($1)', ['Test']);

      const mockClient = await mockPool.connect();
      expect(mockClient.query).toHaveBeenCalledWith('INSERT INTO users (name) VALUES ($1)', [
        'Test',
      ]);
    });
  });

  describe('MySQL/MariaDB Connection', () => {
    it('should create MySQL connection', async () => {
      const mockPool = createMockMySqlPool();
      const factory = new ConnectionFactory({ dbType: 'mysql', pool: mockPool });

      const connection = await factory.create();

      expect(connection).toBeDefined();
      expect(mockPool.getConnection).toHaveBeenCalled();
    });

    it('should wrap mysql2 connection with DatabaseConnection interface', async () => {
      const mockPool = createMockMySqlPool();
      const factory = new ConnectionFactory({ dbType: 'mysql', pool: mockPool });

      const connection = await factory.create();

      expect(connection).toHaveProperty('query');
      expect(connection).toHaveProperty('execute');
      expect(connection).toHaveProperty('beginTransaction');
      expect(connection).toHaveProperty('commit');
      expect(connection).toHaveProperty('rollback');
      expect(connection).toHaveProperty('release');
    });

    it('should support extended connection methods', async () => {
      const mockPool = createMockMySqlPool();
      const factory = new ConnectionFactory({ dbType: 'mysql', pool: mockPool });

      const connection = await factory.create();

      expect(connection.isPostgres()).toBe(false);
      expect(connection).toHaveProperty('quoteIdentifier');
      expect(connection).toHaveProperty('param');
    });

    it('should use MySQL parameter placeholders (?)', async () => {
      const mockPool = createMockMySqlPool();
      const factory = new ConnectionFactory({ dbType: 'mysql', pool: mockPool });

      const connection = await factory.create();

      expect(connection.param(1)).toBe('?');
      expect(connection.param(2)).toBe('?');
      expect(connection.param(10)).toBe('?');
    });

    it('should quote MySQL identifiers with backticks', async () => {
      const mockPool = createMockMySqlPool();
      const factory = new ConnectionFactory({ dbType: 'mysql', pool: mockPool });

      const connection = await factory.create();

      expect(connection.quoteIdentifier('users')).toBe('`users`');
      expect(connection.quoteIdentifier('table`with`backticks')).toBe('`table``with``backticks`');
    });
  });

  describe('Transaction Support', () => {
    it('should begin transaction on PostgreSQL', async () => {
      const mockPool = createMockPgPool();
      const factory = new ConnectionFactory({ dbType: 'postgres', pool: mockPool });

      const connection = await factory.create();
      await connection.beginTransaction();

      const mockClient = await mockPool.connect();
      expect(mockClient.query).toHaveBeenCalledWith('BEGIN');
    });

    it('should commit transaction on PostgreSQL', async () => {
      const mockPool = createMockPgPool();
      const factory = new ConnectionFactory({ dbType: 'postgres', pool: mockPool });

      const connection = await factory.create();
      await connection.beginTransaction();
      await connection.commit();

      const mockClient = await mockPool.connect();
      expect(mockClient.query).toHaveBeenCalledWith('COMMIT');
    });

    it('should rollback transaction on PostgreSQL', async () => {
      const mockPool = createMockPgPool();
      const factory = new ConnectionFactory({ dbType: 'postgres', pool: mockPool });

      const connection = await factory.create();
      await connection.beginTransaction();
      await connection.rollback();

      const mockClient = await mockPool.connect();
      expect(mockClient.query).toHaveBeenCalledWith('ROLLBACK');
    });

    it('should begin transaction on MySQL', async () => {
      const mockPool = createMockMySqlPool();
      const factory = new ConnectionFactory({ dbType: 'mysql', pool: mockPool });

      const connection = await factory.create();
      await connection.beginTransaction();

      const mockConn = await mockPool.getConnection();
      expect(mockConn.beginTransaction).toHaveBeenCalled();
    });

    it('should commit transaction on MySQL', async () => {
      const mockPool = createMockMySqlPool();
      const factory = new ConnectionFactory({ dbType: 'mysql', pool: mockPool });

      const connection = await factory.create();
      await connection.beginTransaction();
      await connection.commit();

      const mockConn = await mockPool.getConnection();
      expect(mockConn.commit).toHaveBeenCalled();
    });

    it('should rollback transaction on MySQL', async () => {
      const mockPool = createMockMySqlPool();
      const factory = new ConnectionFactory({ dbType: 'mysql', pool: mockPool });

      const connection = await factory.create();
      await connection.beginTransaction();
      await connection.rollback();

      const mockConn = await mockPool.getConnection();
      expect(mockConn.rollback).toHaveBeenCalled();
    });
  });

  describe('Connection Release', () => {
    it('should release PostgreSQL connection', async () => {
      const mockPool = createMockPgPool();
      const factory = new ConnectionFactory({ dbType: 'postgres', pool: mockPool });

      const connection = await factory.create();
      connection.release?.();

      const mockClient = await mockPool.connect();
      expect(mockClient.release).toHaveBeenCalled();
    });

    it('should release MySQL connection', async () => {
      const mockPool = createMockMySqlPool();
      const factory = new ConnectionFactory({ dbType: 'mysql', pool: mockPool });

      const connection = await factory.create();
      connection.release?.();

      const mockConn = await mockPool.getConnection();
      expect(mockConn.release).toHaveBeenCalled();
    });

    it('should rollback transaction before releasing if in transaction (PostgreSQL)', async () => {
      const mockPool = createMockPgPool();
      const factory = new ConnectionFactory({ dbType: 'postgres', pool: mockPool });

      const connection = await factory.create();
      await connection.beginTransaction();
      connection.release?.();

      const mockClient = await mockPool.connect();
      // Should attempt rollback before release
      expect(mockClient.query).toHaveBeenCalledWith('ROLLBACK');
    });

    it('should rollback transaction before releasing if in transaction (MySQL)', async () => {
      const mockPool = createMockMySqlPool();
      const factory = new ConnectionFactory({ dbType: 'mysql', pool: mockPool });

      const connection = await factory.create();
      await connection.beginTransaction();
      connection.release?.();

      const mockConn = await mockPool.getConnection();
      expect(mockConn.rollback).toHaveBeenCalled();
    });
  });

  describe('Pool Management', () => {
    it('should close PostgreSQL pool', async () => {
      const mockPool = createMockPgPool();
      const factory = new ConnectionFactory({ dbType: 'postgres', pool: mockPool });

      await factory.close();

      expect(mockPool.end).toHaveBeenCalled();
    });

    it('should close MySQL pool', async () => {
      const mockPool = createMockMySqlPool();
      const factory = new ConnectionFactory({ dbType: 'mysql', pool: mockPool });

      await factory.close();

      expect(mockPool.end).toHaveBeenCalled();
    });

    it('should handle closing when pool is null', async () => {
      const factory = new ConnectionFactory({ dbType: 'postgres' });

      // Should not throw when pool is not initialized
      await expect(factory.close()).resolves.toBeUndefined();
    });

    it('should reuse pool for multiple connections', async () => {
      const mockPool = createMockPgPool();
      const factory = new ConnectionFactory({ dbType: 'postgres', pool: mockPool });

      await factory.create();
      await factory.create();

      // Pool should be connected only once, but connect() called twice
      expect(mockPool.connect).toHaveBeenCalledTimes(2);
    });
  });

  describe('isPostgres', () => {
    it('should return true for PostgreSQL factory', () => {
      const factory = new ConnectionFactory({ dbType: 'postgres' });
      expect(factory.isPostgres()).toBe(true);
    });

    it('should return false for MySQL factory', () => {
      const factory = new ConnectionFactory({ dbType: 'mysql' });
      expect(factory.isPostgres()).toBe(false);
    });
  });

  describe('Error Handling', () => {
    it('should propagate connection errors', async () => {
      const failingPool = {
        connect: vi.fn().mockRejectedValue(new Error('Connection failed')),
      };

      const factory = new ConnectionFactory({
        dbType: 'postgres',
        pool: failingPool as unknown as import('pg').Pool,
      });

      await expect(factory.create()).rejects.toThrow('Connection failed');
    });

    it('should handle pool closure errors gracefully', async () => {
      const failingPool = {
        end: vi.fn().mockRejectedValue(new Error('Close failed')),
      };

      const factory = new ConnectionFactory({
        dbType: 'postgres',
        pool: failingPool as unknown as import('pg').Pool,
      });

      await expect(factory.close()).rejects.toThrow('Close failed');
    });
  });
});
