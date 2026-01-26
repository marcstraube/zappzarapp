/**
 * PostgreSQL/MariaDB Connection Pool
 *
 * Provides a connection pool for database access.
 * Configuration is loaded from environment variables via config/database.ts.
 *
 * Usage:
 * ```typescript
 * import { getPool, query } from '@backend/db/pool';
 *
 * // Direct query
 * const result = await query('SELECT NOW()');
 *
 * // Get pool for more control
 * const pool = getPool();
 * const client = await pool.connect();
 * try {
 *   const result = await client.query('SELECT 1');
 * } finally {
 *   client.release();
 * }
 * ```
 */

import { Pool, PoolConfig, QueryResult, QueryResultRow } from 'pg';
import { existsSync, readFileSync } from 'fs';
import {
  getDatabaseConfig,
  getSslConfig,
  hasSsl,
  isPostgres,
  DatabaseConfig,
} from './DatabaseConfig';

// Pool singleton
let pool: Pool | null = null;

// Pool configuration constants
const DEFAULT_MIN_CONNECTIONS = 2;
const DEFAULT_MAX_CONNECTIONS = 10;
const DEFAULT_IDLE_TIMEOUT_MS = 30000;
const DEFAULT_CONNECTION_TIMEOUT_MS = 5000;

/**
 * Get environment variable with fallback.
 */
function getEnv(name: string, fallback: string): string {
  const value = process.env[name];
  return value !== undefined && value !== '' ? value : fallback;
}

/**
 * Build SSL configuration for pg Pool.
 */
function buildSslConfig(dbConfig: DatabaseConfig): PoolConfig['ssl'] {
  if (!hasSsl(dbConfig)) {
    // PostgreSQL: Use 'prefer' mode (try SSL, fall back to non-SSL)
    // This allows connections to work in both dev (no SSL) and prod (SSL)
    return undefined;
  }

  const sslConfig = getSslConfig(dbConfig);
  const ca = existsSync(sslConfig.ca) ? readFileSync(sslConfig.ca, 'utf-8') : undefined;

  return {
    ca,
    rejectUnauthorized: sslConfig.verify,
  };
}

/**
 * Create pool configuration from database config.
 */
function createPoolConfig(): PoolConfig {
  const dbConfig = getDatabaseConfig();

  if (!isPostgres(dbConfig)) {
    throw new Error('MySQL/MariaDB is not supported by pg pool. Use mysql2 for MariaDB.');
  }

  const minConnections = parseInt(getEnv('DB_POOL_MIN', String(DEFAULT_MIN_CONNECTIONS)), 10);
  const maxConnections = parseInt(getEnv('DB_POOL_MAX', String(DEFAULT_MAX_CONNECTIONS)), 10);
  const idleTimeoutMs = parseInt(
    getEnv('DB_POOL_IDLE_TIMEOUT', String(DEFAULT_IDLE_TIMEOUT_MS)),
    10
  );
  const connectionTimeoutMs = parseInt(
    getEnv('DB_POOL_CONNECTION_TIMEOUT', String(DEFAULT_CONNECTION_TIMEOUT_MS)),
    10
  );

  return {
    host: dbConfig.host,
    port: dbConfig.port,
    database: dbConfig.name,
    user: dbConfig.user,
    password: dbConfig.password,
    min: minConnections,
    max: maxConnections,
    idleTimeoutMillis: idleTimeoutMs,
    connectionTimeoutMillis: connectionTimeoutMs,
    ssl: buildSslConfig(dbConfig),
  };
}

/**
 * Get or create the database pool singleton.
 *
 * @returns PostgreSQL connection pool
 * @throws Error if database type is not PostgreSQL
 */
export function getPool(): Pool {
  if (!pool) {
    pool = new Pool(createPoolConfig());

    // Handle pool errors
    pool.on('error', (err) => {
      console.error('Unexpected error on idle client', err);
    });
  }

  return pool;
}

/**
 * Execute a query using the pool.
 *
 * @param text SQL query string
 * @param values Optional query parameters
 * @returns Query result
 */
export async function query<T extends QueryResultRow = QueryResultRow>(
  text: string,
  values?: unknown[]
): Promise<QueryResult<T>> {
  return getPool().query<T>(text, values);
}

/**
 * Close the pool and release all connections.
 * Call this during graceful shutdown.
 */
export async function closePool(): Promise<void> {
  if (pool) {
    await pool.end();
    pool = null;
  }
}

/**
 * Check if pool is connected and responsive.
 *
 * @returns True if database is reachable
 */
export async function isConnected(): Promise<boolean> {
  try {
    await query('SELECT 1');
    return true;
  } catch {
    return false;
  }
}

/**
 * Check if using PostgreSQL (MariaDB not supported by this pool).
 */
export function isPostgresPool(): boolean {
  return isPostgres();
}
