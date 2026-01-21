/**
 * Abstract Repository Implementation
 *
 * Base class for repositories with dual-database support.
 * Uses ConnectionFactory to abstract PostgreSQL (pg) and MariaDB (mysql2).
 *
 * Features:
 * - Lazy connection initialization via ConnectionFactory
 * - Automatic encrypted field handling via DB functions
 * - Graceful error handling (returns null/false, no throws)
 * - Transaction support
 * - Database-agnostic SQL building
 *
 * @package Infrastructure/Repository
 */

import type { RepositoryInterface, Row, Criteria, RepositoryOptions } from './RepositoryInterface';
import {
  ConnectionFactory,
  getConnectionFactory,
  type ExtendedDatabaseConnection,
} from '../db/ConnectionFactory';
import { existsSync, readFileSync } from 'fs';

/**
 * Abstract Repository Options
 */
export interface AbstractRepositoryOptions extends RepositoryOptions {
  /** Encryption key (loaded from environment/secrets if not provided) */
  encryptionKey?: string;
  /** Connection factory (uses default singleton if not provided) */
  connectionFactory?: ConnectionFactory;
}

/**
 * Default encryption key file paths
 */
const ENCRYPTION_KEY_FILE = '/run/secrets/encryption_key.txt';
const ENCRYPTION_KEY_ENV = 'ENCRYPTION_KEY';

/**
 * Abstract Repository Base Class
 *
 * Extend this class and implement getTable() to create a repository.
 *
 * @example
 * ```typescript
 * class UserRepository extends AbstractRepository<User> {
 *   protected getTable(): string {
 *     return 'users';
 *   }
 *
 *   protected getEncryptedFields(): string[] {
 *     return ['totp_secret'];
 *   }
 *
 *   async findByEmail(email: string): Promise<User | null> {
 *     const results = await this.findBy({ email }, 1);
 *     return results[0] ?? null;
 *   }
 * }
 * ```
 */
export abstract class AbstractRepository<T extends Row = Row> implements RepositoryInterface<T> {
  private connection: ExtendedDatabaseConnection | null = null;
  private inTransaction = false;
  private readonly primaryKey: string;
  private readonly encryptionKey: string | null;
  private readonly connectionFactory: ConnectionFactory;

  constructor(options: AbstractRepositoryOptions = {}) {
    this.primaryKey = options.primaryKey ?? 'id';
    this.encryptionKey = options.encryptionKey ?? this.loadEncryptionKey();
    this.connectionFactory = options.connectionFactory ?? getConnectionFactory();
  }

  /**
   * Get the table name (must be implemented by subclasses)
   */
  protected abstract getTable(): string;

  /**
   * Get list of encrypted field names (override in subclass)
   */
  protected getEncryptedFields(): string[] {
    return [];
  }

  /**
   * Get the primary key column name
   *
   * TODO: Use in subclass for dynamic primary key handling (e.g., composite keys)
   */
  // noinspection JSUnusedGlobalSymbols - Available for subclasses to override or use
  protected getPrimaryKey(): string {
    return this.primaryKey;
  }

  /**
   * Check if using PostgreSQL
   */
  protected isPostgres(): boolean {
    return this.connectionFactory.isPostgres();
  }

  // =========================================================================
  // Connection Management
  // =========================================================================

  /**
   * Get or create database connection (lazy initialization)
   */
  protected async getConnection(): Promise<ExtendedDatabaseConnection | null> {
    if (this.connection !== null) {
      return this.connection;
    }

    try {
      this.connection = await this.connectionFactory.create();
      return this.connection;
    } catch {
      return null;
    }
  }

  /**
   * Release the connection back to the pool
   */
  protected releaseConnection(): void {
    if (this.connection !== null && !this.inTransaction) {
      this.connection.release?.();
      this.connection = null;
    }
  }

  // =========================================================================
  // SQL Building Helpers
  // =========================================================================

  /**
   * Quote an identifier (table/column name) for the current database
   */
  protected quoteIdentifier(name: string): string {
    if (this.connection !== null) {
      return this.connection.quoteIdentifier(name);
    }
    // Fallback based on factory type
    return this.isPostgres() ? `"${name.replace(/"/g, '""')}"` : `\`${name.replace(/`/g, '``')}\``;
  }

  /**
   * Get parameter placeholder for the given index (1-based)
   */
  protected param(index: number): string {
    if (this.connection !== null) {
      return this.connection.param(index);
    }
    // Fallback based on factory type
    return this.isPostgres() ? `$${index}` : '?';
  }

  /**
   * Build WHERE clause from criteria
   */
  private buildWhereClause(criteria: Criteria): { whereClause: string; params: unknown[] } {
    const conditions: string[] = [];
    const params: unknown[] = [];

    for (const [column, value] of Object.entries(criteria)) {
      conditions.push(`${this.quoteIdentifier(column)} = ${this.param(params.length + 1)}`);
      params.push(value);
    }

    return {
      whereClause: conditions.join(' AND '),
      params,
    };
  }

  /**
   * Append LIMIT and OFFSET clauses to SQL query
   */
  private appendLimitOffset(
    sql: string,
    params: unknown[],
    limit?: number,
    offset?: number
  ): string {
    if (limit !== undefined) {
      sql += ` LIMIT ${this.param(params.length + 1)}`;
      params.push(limit);
    }

    if (offset !== undefined) {
      sql += ` OFFSET ${this.param(params.length + 1)}`;
      params.push(offset);
    }

    return sql;
  }

  // =========================================================================
  // Query Execution
  // =========================================================================

  /**
   * Execute a query and return rows
   */
  protected async query<R extends Row = T>(sql: string, params: unknown[] = []): Promise<R[]> {
    try {
      const conn = await this.getConnection();
      if (conn === null) {
        return [];
      }

      const results = await conn.query<R>(sql, params);

      // Release connection if not in transaction
      if (!this.inTransaction) {
        this.releaseConnection();
      }

      return results;
    } catch {
      this.releaseConnection();
      return [];
    }
  }

  /**
   * Execute a statement (INSERT/UPDATE/DELETE)
   */
  protected async execute(
    sql: string,
    params: unknown[] = []
  ): Promise<{ affectedRows: number; insertId?: number }> {
    try {
      const conn = await this.getConnection();
      if (conn === null) {
        return { affectedRows: 0 };
      }

      const result = await conn.execute(sql, params);

      // Release connection if not in transaction
      if (!this.inTransaction) {
        this.releaseConnection();
      }

      return result;
    } catch {
      this.releaseConnection();
      return { affectedRows: 0 };
    }
  }

  // =========================================================================
  // CRUD Operations
  // =========================================================================

  async find(id: number | string): Promise<T | null> {
    const table = this.quoteIdentifier(this.getTable());
    const pk = this.quoteIdentifier(this.primaryKey);
    const sql = `SELECT * FROM ${table} WHERE ${pk} = ${this.param(1)} LIMIT 1`;

    const results = await this.query<T>(sql, [id]);
    const row = results[0];
    if (row === undefined) {
      return null;
    }

    return this.decryptRow(row);
  }

  async findAll(limit?: number, offset?: number): Promise<T[]> {
    const table = this.quoteIdentifier(this.getTable());
    const baseSql = `SELECT * FROM ${table}`;

    const params: unknown[] = [];
    const sql = this.appendLimitOffset(baseSql, params, limit, offset);

    const results = await this.query<T>(sql, params);
    return Promise.all(results.map((row) => this.decryptRow(row)));
  }

  async findBy(criteria: Criteria, limit?: number, offset?: number): Promise<T[]> {
    const { whereClause, params } = this.buildWhereClause(criteria);
    const table = this.quoteIdentifier(this.getTable());
    const baseSql = `SELECT * FROM ${table} WHERE ${whereClause}`;

    const sql = this.appendLimitOffset(baseSql, params, limit, offset);

    const results = await this.query<T>(sql, params);
    return Promise.all(results.map((row) => this.decryptRow(row)));
  }

  async insert(data: Partial<T>): Promise<number | string | null> {
    const encryptedData = await this.encryptData(data);
    const columns = Object.keys(encryptedData);
    const values = Object.values(encryptedData);

    if (columns.length === 0) {
      return null;
    }

    const table = this.quoteIdentifier(this.getTable());
    const columnList = columns.map((c) => this.quoteIdentifier(c)).join(', ');
    const placeholders = columns.map((_, i) => this.param(i + 1)).join(', ');

    let sql = `INSERT INTO ${table} (${columnList}) VALUES (${placeholders})`;

    // PostgreSQL: Use RETURNING to get the inserted ID
    if (this.isPostgres()) {
      sql += ` RETURNING ${this.quoteIdentifier(this.primaryKey)}`;
      const results = await this.query<{ [key: string]: number | string }>(sql, values);
      return results[0]?.[this.primaryKey] ?? null;
    }

    // MariaDB: Use lastInsertId from execute result
    const result = await this.execute(sql, values);
    return result.insertId ?? null;
  }

  async update(id: number | string, data: Partial<T>): Promise<boolean> {
    const encryptedData = await this.encryptData(data);
    const columns = Object.keys(encryptedData);
    const values = Object.values(encryptedData);

    if (columns.length === 0) {
      return false;
    }

    const table = this.quoteIdentifier(this.getTable());
    const pk = this.quoteIdentifier(this.primaryKey);
    const setClauses = columns.map((c, i) => `${this.quoteIdentifier(c)} = ${this.param(i + 1)}`);

    const sql = `UPDATE ${table} SET ${setClauses.join(', ')} WHERE ${pk} = ${this.param(columns.length + 1)}`;

    const result = await this.execute(sql, [...(values as unknown[]), id]);
    return result.affectedRows > 0;
  }

  async delete(id: number | string): Promise<boolean> {
    const table = this.quoteIdentifier(this.getTable());
    const pk = this.quoteIdentifier(this.primaryKey);
    const sql = `DELETE FROM ${table} WHERE ${pk} = ${this.param(1)}`;

    const result = await this.execute(sql, [id]);
    return result.affectedRows > 0;
  }

  async exists(id: number | string): Promise<boolean> {
    const table = this.quoteIdentifier(this.getTable());
    const pk = this.quoteIdentifier(this.primaryKey);
    const sql = `SELECT 1 FROM ${table} WHERE ${pk} = ${this.param(1)} LIMIT 1`;

    const results = await this.query(sql, [id]);
    return results.length > 0;
  }

  async count(criteria?: Criteria): Promise<number> {
    const table = this.quoteIdentifier(this.getTable());

    let sql: string;
    let params: unknown[] = [];

    if (criteria !== undefined && Object.keys(criteria).length > 0) {
      const { whereClause, params: whereParams } = this.buildWhereClause(criteria);
      sql = `SELECT COUNT(*) as count FROM ${table} WHERE ${whereClause}`;
      params = whereParams;
    } else {
      sql = `SELECT COUNT(*) as count FROM ${table}`;
    }

    const results = await this.query<{ count: number | string }>(sql, params);
    const count = results[0]?.count;
    return typeof count === 'number' ? count : parseInt(String(count), 10) || 0;
  }

  // =========================================================================
  // Transaction Support
  // =========================================================================

  async beginTransaction(): Promise<boolean> {
    try {
      const conn = await this.getConnection();
      if (conn === null) {
        return false;
      }

      await conn.beginTransaction();
      this.inTransaction = true;
      return true;
    } catch {
      return false;
    }
  }

  async commit(): Promise<boolean> {
    if (!this.inTransaction || this.connection === null) {
      return false;
    }

    try {
      await this.connection.commit();
      this.inTransaction = false;
      this.releaseConnection();
      return true;
    } catch {
      return false;
    }
  }

  async rollback(): Promise<boolean> {
    if (!this.inTransaction || this.connection === null) {
      return false;
    }

    try {
      await this.connection.rollback();
      this.inTransaction = false;
      this.releaseConnection();
      return true;
    } catch {
      return false;
    }
  }

  async isAvailable(): Promise<boolean> {
    try {
      const conn = await this.getConnection();
      if (conn === null) {
        return false;
      }

      // Simple query to check connection
      await conn.query('SELECT 1');
      this.releaseConnection();
      return true;
    } catch {
      this.releaseConnection();
      return false;
    }
  }

  // =========================================================================
  // Encryption Support
  // =========================================================================

  /**
   * Encrypt data fields that are marked as encrypted
   */
  private async encryptData(data: Partial<T>): Promise<Partial<T>> {
    const encryptedFields = this.getEncryptedFields();
    const result = { ...data };

    for (const field of encryptedFields) {
      if (field in result && typeof result[field as keyof T] === 'string') {
        const encrypted = await this.encryptValue(result[field as keyof T] as string);
        if (encrypted !== null) {
          (result as Record<string, unknown>)[field] = encrypted;
        }
      }
    }

    return result;
  }

  /**
   * Decrypt a single row's encrypted fields
   */
  private async decryptRow(row: T): Promise<T> {
    const encryptedFields = this.getEncryptedFields();
    const result = { ...row };

    for (const field of encryptedFields) {
      if (field in result && typeof result[field as keyof T] === 'string') {
        const decrypted = await this.decryptValue(result[field as keyof T] as string);
        if (decrypted !== null) {
          (result as Record<string, unknown>)[field] = decrypted;
        }
      }
    }

    return result;
  }

  /**
   * Encrypt a value using database encryption function
   */
  protected async encryptValue(value: string): Promise<string | null> {
    if (this.encryptionKey === null) {
      return null;
    }

    const sql = `SELECT encrypt_text(${this.param(1)}, ${this.param(2)}) as encrypted`;
    const results = await this.query<{ encrypted: string }>(sql, [value, this.encryptionKey]);
    return results[0]?.encrypted ?? null;
  }

  /**
   * Decrypt a value using database decryption function
   */
  protected async decryptValue(encrypted: string): Promise<string | null> {
    if (this.encryptionKey === null) {
      return null;
    }

    const sql = `SELECT decrypt_text(${this.param(1)}, ${this.param(2)}) as decrypted`;
    const results = await this.query<{ decrypted: string }>(sql, [encrypted, this.encryptionKey]);
    return results[0]?.decrypted ?? null;
  }

  /**
   * Load encryption key from Docker secret or environment
   */
  private loadEncryptionKey(): string | null {
    // Try Docker secret file
    const secretFile = process.env['ENCRYPTION_KEY_FILE'] ?? ENCRYPTION_KEY_FILE;
    if (existsSync(secretFile)) {
      try {
        return readFileSync(secretFile, 'utf-8').trim();
      } catch {
        // Fall through to environment variable
      }
    }

    // Fall back to environment variable
    const envKey = process.env[ENCRYPTION_KEY_ENV];
    return envKey !== undefined && envKey !== '' ? envKey : null;
  }

  // =========================================================================
  // Testing Helpers
  // =========================================================================

  /**
   * Get the encryption key (for testing)
   */
  getEncryptionKeyForTesting(): string | null {
    return this.encryptionKey;
  }

  /**
   * Check if in transaction (for testing)
   */
  isInTransaction(): boolean {
    return this.inTransaction;
  }
}
