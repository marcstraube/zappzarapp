/**
 * Database Connection Factory
 *
 * Creates database connections that implement the DatabaseConnection interface.
 * Abstracts the differences between PostgreSQL (pg) and MariaDB (mysql2).
 *
 * Usage:
 * ```typescript
 * const factory = new ConnectionFactory();
 * const connection = await factory.create();
 *
 * // Use unified API regardless of database type
 * const users = await connection.query<User>('SELECT * FROM users');
 * await connection.beginTransaction();
 * await connection.execute('INSERT INTO logs (msg) VALUES (?)', ['test']);
 * await connection.commit();
 * connection.release();
 * ```
 *
 * @package Infrastructure/Database
 */

import type { Pool as PgPool, PoolClient as PgClient } from 'pg';
import type {
  Pool as MySqlPool,
  PoolConnection as MySqlConnection,
  ResultSetHeader,
} from 'mysql2/promise';
import type { DatabaseConnection, Row } from '../Repository/RepositoryInterface.js';
import { getDatabaseConfig, isPostgres } from './DatabaseConfig.js';

/**
 * Database type identifier
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export type DatabaseType = 'postgres' | 'mysql';

/**
 * Connection factory options
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export interface ConnectionFactoryOptions {
  /** Override database type detection */
  dbType?: DatabaseType;
  /** Inject a pool for testing */
  pool?: PgPool | MySqlPool;
}

/**
 * PostgreSQL Connection Implementation
 *
 * Wraps pg.PoolClient to implement DatabaseConnection interface.
 */
class PostgresConnectionImpl implements DatabaseConnection {
  private inTransaction = false;

  constructor(private readonly client: PgClient) {}

  async query<T extends Row = Row>(sql: string, params?: unknown[]): Promise<T[]> {
    const result = await this.client.query(sql, params);
    return result.rows as T[];
  }

  async execute(
    sql: string,
    params?: unknown[]
  ): Promise<{ affectedRows: number; insertId?: number }> {
    const result = await this.client.query(sql, params);
    return {
      affectedRows: result.rowCount ?? 0,
      // PostgreSQL uses RETURNING clause for insert IDs, handled separately
      insertId: undefined,
    };
  }

  async beginTransaction(): Promise<void> {
    await this.client.query('BEGIN');
    this.inTransaction = true;
  }

  async commit(): Promise<void> {
    await this.client.query('COMMIT');
    this.inTransaction = false;
  }

  async rollback(): Promise<void> {
    await this.client.query('ROLLBACK');
    this.inTransaction = false;
  }

  release(): void {
    if (this.inTransaction) {
      // Force rollback if releasing during transaction
      this.client.query('ROLLBACK').catch(() => {
        // Ignore errors during cleanup
      });
    }
    this.client.release();
  }

  /**
   * Get the last inserted ID using a sequence name
   *
   * PostgreSQL requires explicit sequence lookup for auto-increment values.
   */
  async getLastInsertId(sequenceName: string): Promise<number | undefined> {
    const result = await this.client.query<{ currval: string | number }>(`SELECT currval($1)`, [
      sequenceName,
    ]);
    const value = result.rows[0]?.currval;
    return value !== undefined ? Number(value) : undefined;
  }

  /**
   * Check if this is a PostgreSQL connection
   */
  isPostgres(): boolean {
    return true;
  }

  /**
   * Quote an identifier (table/column name) for PostgreSQL
   */
  quoteIdentifier(name: string): string {
    return `"${name.replace(/"/g, '""')}"`;
  }

  /**
   * Get parameter placeholder for PostgreSQL ($1, $2, etc.)
   */
  param(index: number): string {
    return `$${index}`;
  }
}

/**
 * MariaDB/MySQL Connection Implementation
 *
 * Wraps mysql2 PoolConnection to implement DatabaseConnection interface.
 */
class MariaDbConnectionImpl implements DatabaseConnection {
  private inTransaction = false;

  constructor(private readonly connection: MySqlConnection) {}

  async query<T extends Row = Row>(sql: string, params?: unknown[]): Promise<T[]> {
    const [rows] = await this.connection.query(sql, params);
    return rows as T[];
  }

  async execute(
    sql: string,
    params?: unknown[]
  ): Promise<{ affectedRows: number; insertId?: number }> {
    // Cast at adapter boundary: mysql2 3.22+ tightened ExecuteValues (string |
    // number | bigint | boolean | Date | Blob | Buffer | Uint8Array | null and
    // their arrays/records). Our DatabaseConnection API is provider-agnostic and
    // accepts unknown[]; callers pass primitive SQL parameter values.
    const [result] = await this.connection.execute(
      sql,
      params as Parameters<typeof this.connection.execute>[1]
    );
    const header = result as ResultSetHeader;
    return {
      affectedRows: header.affectedRows,
      insertId: header.insertId,
    };
  }

  async beginTransaction(): Promise<void> {
    await this.connection.beginTransaction();
    this.inTransaction = true;
  }

  async commit(): Promise<void> {
    await this.connection.commit();
    this.inTransaction = false;
  }

  async rollback(): Promise<void> {
    await this.connection.rollback();
    this.inTransaction = false;
  }

  release(): void {
    if (this.inTransaction) {
      // Force rollback if releasing during transaction
      this.connection.rollback().catch(() => {
        // Ignore errors during cleanup
      });
    }
    this.connection.release();
  }

  /**
   * Check if this is a PostgreSQL connection
   */
  isPostgres(): boolean {
    return false;
  }

  /**
   * Quote an identifier (table/column name) for MariaDB/MySQL
   */
  quoteIdentifier(name: string): string {
    return `\`${name.replace(/`/g, '``')}\``;
  }

  /**
   * Get parameter placeholder for MariaDB/MySQL (always ?)
   */
  param(_index: number): string {
    return '?';
  }
}

/**
 * Extended DatabaseConnection with database-specific helpers
 */
export interface ExtendedDatabaseConnection extends DatabaseConnection {
  /** Check if this is a PostgreSQL connection */
  isPostgres(): boolean;
  /** Get last insert ID (PostgreSQL requires sequence name) */
  getLastInsertId?(sequenceName: string): Promise<number | undefined>;
  /** Quote an identifier (table/column name) for the current database */
  quoteIdentifier(name: string): string;
  /** Get parameter placeholder for the given index (1-based) */
  param(index: number): string;
}

/**
 * Database Connection Factory
 *
 * Creates connections that implement the DatabaseConnection interface,
 * abstracting the differences between PostgreSQL and MariaDB.
 */
export class ConnectionFactory {
  private pool: PgPool | MySqlPool | null = null;
  private readonly dbType: DatabaseType;
  private readonly injectedPool: PgPool | MySqlPool | null;

  constructor(options: ConnectionFactoryOptions = {}) {
    this.dbType = options.dbType ?? (isPostgres() ? 'postgres' : 'mysql');
    this.injectedPool = options.pool ?? null;
  }

  /**
   * Create a new database connection
   *
   * @returns A connection implementing DatabaseConnection interface
   * @throws Error if connection cannot be established
   */
  async create(): Promise<ExtendedDatabaseConnection> {
    const pool = await this.getPool();

    if (this.dbType === 'postgres') {
      const pgPool = pool as PgPool;
      const client = await pgPool.connect();
      return new PostgresConnectionImpl(client);
    } else {
      const mysqlPool = pool as MySqlPool;
      const connection = await mysqlPool.getConnection();
      return new MariaDbConnectionImpl(connection);
    }
  }

  /**
   * Get or create the connection pool
   */
  private async getPool(): Promise<PgPool | MySqlPool> {
    if (this.injectedPool !== null) {
      return this.injectedPool;
    }

    if (this.pool !== null) {
      return this.pool;
    }

    if (this.dbType === 'postgres') {
      const { getPool } = await import('./pool');
      this.pool = getPool();
    } else {
      const mysql = await import('mysql2/promise');
      const config = getDatabaseConfig();
      this.pool = mysql.createPool({
        host: config.host,
        port: config.port,
        user: config.user,
        password: config.password,
        database: config.name,
        waitForConnections: true,
        connectionLimit: 10,
      });
    }

    return this.pool;
  }

  /**
   * Close the connection pool
   */
  async close(): Promise<void> {
    const poolToClose = this.injectedPool ?? this.pool;
    if (poolToClose === null) {
      return;
    }

    if (this.dbType === 'postgres') {
      await (poolToClose as PgPool).end();
    } else {
      await (poolToClose as MySqlPool).end();
    }

    this.pool = null;
  }

  /**
   * Check if using PostgreSQL
   */
  isPostgres(): boolean {
    return this.dbType === 'postgres';
  }
}

/**
 * Default factory singleton
 */
let defaultFactory: ConnectionFactory | null = null;

/**
 * Get the default connection factory singleton
 */
export function getConnectionFactory(): ConnectionFactory {
  defaultFactory ??= new ConnectionFactory();
  return defaultFactory;
}

/**
 * Create a connection using the default factory
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export async function createConnection(): Promise<ExtendedDatabaseConnection> {
  return getConnectionFactory().create();
}
