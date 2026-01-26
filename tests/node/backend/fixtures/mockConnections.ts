/**
 * Mock database connections for testing
 */

import { vi } from 'vitest';
import type { Pool as PgPool, PoolClient as PgClient } from 'pg';
import type { Pool as MySqlPool, PoolConnection as MySqlConnection } from 'mysql2/promise';

/**
 * Create a mock PostgreSQL client
 */
export function createMockPgClient(): PgClient {
  return {
    query: vi.fn().mockResolvedValue({ rows: [], rowCount: 0 }),
    release: vi.fn(),
  } as unknown as PgClient;
}

/**
 * Create a mock PostgreSQL pool
 */
export function createMockPgPool(): PgPool {
  const mockClient = createMockPgClient();
  return {
    connect: vi.fn().mockResolvedValue(mockClient),
    query: vi.fn().mockResolvedValue({ rows: [], rowCount: 0 }),
    end: vi.fn().mockResolvedValue(undefined),
    on: vi.fn(),
  } as unknown as PgPool;
}

/**
 * Create a mock MySQL connection
 */
export function createMockMySqlConnection(): MySqlConnection {
  return {
    query: vi.fn().mockResolvedValue([[], []]),
    execute: vi.fn().mockResolvedValue([{ affectedRows: 0, insertId: 0 }, []]),
    beginTransaction: vi.fn().mockResolvedValue(undefined),
    commit: vi.fn().mockResolvedValue(undefined),
    rollback: vi.fn().mockResolvedValue(undefined),
    release: vi.fn(),
  } as unknown as MySqlConnection;
}

/**
 * Create a mock MySQL pool
 */
export function createMockMySqlPool(): MySqlPool {
  const mockConnection = createMockMySqlConnection();
  return {
    getConnection: vi.fn().mockResolvedValue(mockConnection),
    end: vi.fn().mockResolvedValue(undefined),
  } as unknown as MySqlPool;
}
