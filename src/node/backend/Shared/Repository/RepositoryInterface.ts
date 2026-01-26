/**
 * Repository Interface
 *
 * Generic CRUD interface for database repositories.
 * Supports both PostgreSQL and MariaDB via pg and mysql2.
 *
 * Return Value Conventions:
 * - `null`: Entity not found (expected case)
 * - `false`: Operation failed (error)
 * - `undefined`: Operation returned nothing
 * - `data`: Operation succeeded with result
 *
 * @package Infrastructure/Repository
 */

/**
 * Generic row type from database
 */
export type Row = Record<string, unknown>;

/**
 * Criteria for queries (WHERE conditions)
 */
export type Criteria = Record<string, unknown>;

/**
 * Repository Options
 */
export interface RepositoryOptions {
  /** Primary key column name (default: 'id') */
  primaryKey?: string;
}

/**
 * Database Connection Interface
 *
 * Abstraction over pg.Pool or mysql2 pool for cross-database support.
 *
 * @see ConnectionFactory for the implementation
 */
export interface DatabaseConnection {
  query<T extends Row = Row>(sql: string, params?: unknown[]): Promise<T[]>;
  execute(sql: string, params?: unknown[]): Promise<{ affectedRows: number; insertId?: number }>;
  beginTransaction(): Promise<void>;
  commit(): Promise<void>;
  rollback(): Promise<void>;
  release?(): void;
}

/**
 * Repository Interface
 *
 * Generic CRUD operations for database tables.
 */
export interface RepositoryInterface<T extends Row = Row> {
  /**
   * Find a single record by primary key
   *
   * @param id - Primary key value
   * @returns Record or null if not found
   */
  find(id: number | string): Promise<T | null>;

  /**
   * Find all records with optional pagination
   *
   * @param limit - Maximum records to return
   * @param offset - Skip this many records
   * @returns Array of records
   */
  findAll(limit?: number, offset?: number): Promise<T[]>;

  /**
   * Find records matching criteria
   *
   * @param criteria - WHERE conditions
   * @param limit - Maximum records to return
   * @param offset - Skip this many records
   * @returns Array of matching records
   */
  findBy(criteria: Criteria, limit?: number, offset?: number): Promise<T[]>;

  /**
   * Insert a new record
   *
   * @param data - Column values to insert
   * @returns Inserted ID or null on failure
   */
  insert(data: Partial<T>): Promise<number | string | null>;

  /**
   * Update a record by primary key
   *
   * @param id - Primary key value
   * @param data - Column values to update
   * @returns True on success, false on failure
   */
  update(id: number | string, data: Partial<T>): Promise<boolean>;

  /**
   * Delete a record by primary key
   *
   * @param id - Primary key value
   * @returns True on success, false on failure
   */
  delete(id: number | string): Promise<boolean>;

  /**
   * Check if a record exists
   *
   * @param id - Primary key value
   * @returns True if exists
   */
  exists(id: number | string): Promise<boolean>;

  /**
   * Count records, optionally matching criteria
   *
   * @param criteria - Optional WHERE conditions
   * @returns Record count
   */
  count(criteria?: Criteria): Promise<number>;

  /**
   * Begin a database transaction
   *
   * @returns True on success
   */
  beginTransaction(): Promise<boolean>;

  /**
   * Commit current transaction
   *
   * @returns True on success
   */
  commit(): Promise<boolean>;

  /**
   * Rollback current transaction
   *
   * @returns True on success
   */
  rollback(): Promise<boolean>;

  /**
   * Check if database is available
   *
   * @returns True if connected and responsive
   */
  isAvailable(): Promise<boolean>;
}
