/**
 * User Repository Implementation
 *
 * Concrete repository for the users table with TOTP encryption support.
 * Extends AbstractRepository for CRUD operations and encryption.
 *
 * Features:
 * - Email-based user lookup
 * - TOTP secret encryption/decryption using DB functions
 * - Duplicate email checking (with exclude for updates)
 *
 * Table Schema:
 * - id: Primary key (SERIAL/INT)
 * - email: Unique email address
 * - password_hash: Bcrypt hashed password
 * - name: Display name
 * - totp_secret: Encrypted TOTP secret (uses encrypt_text/decrypt_text)
 * - totp_enabled: Boolean flag for 2FA status
 * - created_at: Timestamp
 * - updated_at: Timestamp
 *
 * @package Infrastructure/Repository
 */

import { AbstractRepository, type AbstractRepositoryOptions } from './AbstractRepository.js';
import type { Row } from './RepositoryInterface.js';

/**
 * User record type
 */
export interface User extends Row {
  id: number;
  email: string;
  password_hash: string;
  name: string | null;
  totp_secret: string | null;
  totp_enabled: boolean;
  created_at: Date;
  updated_at: Date;
}

/**
 * User Repository Interface
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export interface UserRepositoryInterface {
  /**
   * Find a user by email address
   */
  findByEmail(email: string): Promise<User | null>;

  /**
   * Check if an email address is already registered
   */
  emailExists(email: string, excludeUserId?: number): Promise<boolean>;

  /**
   * Enable TOTP 2FA for a user
   */
  enableTotp(userId: number, secret: string): Promise<boolean>;

  /**
   * Disable TOTP 2FA for a user
   */
  disableTotp(userId: number): Promise<boolean>;

  /**
   * Get decrypted TOTP secret for verification
   */
  getTotpSecret(userId: number): Promise<string | null>;
}

/**
 * User Repository Implementation
 */
export class UserRepository extends AbstractRepository<User> implements UserRepositoryInterface {
  constructor(options: AbstractRepositoryOptions) {
    super(options);
  }

  protected override getTable(): string {
    return 'users';
  }

  protected override getEncryptedFields(): string[] {
    return ['totp_secret'];
  }

  /**
   * Find a user by email address
   */
  async findByEmail(email: string): Promise<User | null> {
    const results = await this.findBy({ email }, 1);
    return results[0] ?? null;
  }

  /**
   * Check if an email address is already registered
   */
  async emailExists(email: string, excludeUserId?: number): Promise<boolean> {
    let sql: string;
    let params: unknown[];

    if (excludeUserId === undefined) {
      sql = this.isPostgres()
        ? 'SELECT 1 FROM "users" WHERE "email" = $1 LIMIT 1'
        : 'SELECT 1 FROM `users` WHERE `email` = ? LIMIT 1';
      params = [email];
    } else {
      sql = this.isPostgres()
        ? 'SELECT 1 FROM "users" WHERE "email" = $1 AND "id" != $2 LIMIT 1'
        : 'SELECT 1 FROM `users` WHERE `email` = ? AND `id` != ? LIMIT 1';
      params = [email, excludeUserId];
    }

    try {
      const results = await this.queryDirect(sql, params);
      return results.length > 0;
    } catch {
      return false;
    }
  }

  /**
   * Enable TOTP 2FA for a user
   */
  async enableTotp(userId: number, secret: string): Promise<boolean> {
    // Encrypt the secret
    const encryptedSecret = await this.encryptValue(secret);
    if (encryptedSecret === null) {
      return false;
    }

    const sql = this.isPostgres()
      ? 'UPDATE "users" SET "totp_secret" = $1, "totp_enabled" = $2 WHERE "id" = $3'
      : 'UPDATE `users` SET `totp_secret` = ?, `totp_enabled` = ? WHERE `id` = ?';

    try {
      const result = await this.executeDirect(sql, [
        encryptedSecret,
        this.isPostgres() ? true : 1,
        userId,
      ]);
      return result.affectedRows > 0;
    } catch {
      return false;
    }
  }

  /**
   * Disable TOTP 2FA for a user
   */
  async disableTotp(userId: number): Promise<boolean> {
    const sql = this.isPostgres()
      ? 'UPDATE "users" SET "totp_secret" = NULL, "totp_enabled" = $1 WHERE "id" = $2'
      : 'UPDATE `users` SET `totp_secret` = NULL, `totp_enabled` = ? WHERE `id` = ?';

    try {
      const result = await this.executeDirect(sql, [this.isPostgres() ? false : 0, userId]);
      return result.affectedRows > 0;
    } catch {
      return false;
    }
  }

  /**
   * Get decrypted TOTP secret for verification
   */
  async getTotpSecret(userId: number): Promise<string | null> {
    // First get the encrypted secret (only if TOTP is enabled)
    const sql = this.isPostgres()
      ? 'SELECT "totp_secret" FROM "users" WHERE "id" = $1 AND "totp_enabled" = $2 LIMIT 1'
      : 'SELECT `totp_secret` FROM `users` WHERE `id` = ? AND `totp_enabled` = ? LIMIT 1';

    try {
      const results = await this.queryDirect<{ totp_secret: string | null }>(sql, [
        userId,
        this.isPostgres() ? true : 1,
      ]);

      const row = results[0];
      // Explicit null check required by strict-boolean-expressions
      if (row?.totp_secret == null) {
        return null;
      }

      // Decrypt and return
      return await this.decryptValue(row.totp_secret);
    } catch {
      return null;
    }
  }

  /**
   * Direct query execution (bypasses encryption auto-handling)
   */
  private async queryDirect<R extends Row = User>(sql: string, params: unknown[]): Promise<R[]> {
    return this.query<R>(sql, params);
  }

  /**
   * Direct execute (bypasses encryption auto-handling)
   */
  private async executeDirect(sql: string, params: unknown[]): Promise<{ affectedRows: number }> {
    return this.execute(sql, params);
  }
}
