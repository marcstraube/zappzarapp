/**
 * Encryption Service for GDPR-compliant column-level encryption
 *
 * GDPR Art. 32: Encryption of personal data
 *
 * IMPORTANT: This is OPTIONAL! Only use for highly sensitive data (GDPR Art. 9).
 * Most applications don't need column-level encryption - TLS + disk encryption
 * is sufficient.
 *
 * When to use:
 * ✓ Health data
 * ✓ Biometric data
 * ✓ Financial account numbers
 * ✓ Social security numbers
 * ✓ Government ID numbers
 *
 * When NOT to use:
 * ✗ Regular personal data (name, email, address)
 * ✗ Data that needs to be searched/indexed
 * ✗ Low-sensitivity data
 *
 * @example
 * ```typescript
 * const encryptionKey = process.env.ENCRYPTION_KEY;
 * if (!encryptionKey) {
 *   throw new Error('ENCRYPTION_KEY not set');
 * }
 *
 * // Encrypt
 * const encrypted = EncryptionService.encrypt('sensitive data', encryptionKey);
 * // Store encrypted (string) in database VARCHAR/TEXT column
 *
 * // Decrypt
 * const decrypted = EncryptionService.decrypt(encrypted, encryptionKey);
 * ```
 *
 * Database integration:
 * - PostgreSQL: Use encrypt_text()/decrypt_text() functions (see migrations/postgresql/000_encryption_helpers.sql)
 * - MariaDB: Use encrypt_text()/decrypt_text() functions (see migrations/mariadb/000_encryption_helpers.sql)
 * - OR use this TypeScript class for application-level encryption
 */

import { createCipheriv, createDecipheriv, randomBytes, createHash, getCiphers } from 'crypto';

export class EncryptionService {
  /**
   * Encryption cipher (AES-256-GCM)
   */
  private static readonly CIPHER = 'aes-256-gcm';

  /**
   * IV (Initialization Vector) length in bytes
   */
  private static readonly IV_LENGTH = 12; // 96 bits for GCM mode

  /**
   * Auth tag length in bytes
   */
  private static readonly TAG_LENGTH = 16; // 128 bits

  /**
   * Encrypt text using AES-256-GCM
   *
   * Returns base64-encoded string in format: iv:tag:ciphertext
   * Store this in a VARCHAR or TEXT column in database.
   *
   * @param plaintext - Text to encrypt
   * @param key - Encryption key (from process.env.ENCRYPTION_KEY)
   * @returns Base64-encoded encrypted data (iv:tag:ciphertext)
   * @throws Error if encryption fails
   */
  static encrypt(plaintext: string, key: string): string {
    if (!plaintext) {
      throw new Error('Cannot encrypt empty string');
    }

    if (!key) {
      throw new Error('Encryption key cannot be empty');
    }

    // Generate random IV (Initialization Vector)
    const iv = randomBytes(this.IV_LENGTH);

    // Derive 256-bit key from the provided key using SHA-256
    const derivedKey = createHash('sha256').update(key).digest();

    // Create cipher
    const cipher = createCipheriv(this.CIPHER, derivedKey, iv);

    // Encrypt
    let ciphertext = cipher.update(plaintext, 'utf8');
    ciphertext = Buffer.concat([ciphertext, cipher.final()]);

    // Get authentication tag
    const tag = cipher.getAuthTag();

    // Combine IV + tag + ciphertext and encode as base64
    // Format: iv:tag:ciphertext (each base64-encoded)
    return `${iv.toString('base64')}:${tag.toString('base64')}:${ciphertext.toString('base64')}`;
  }

  /**
   * Decrypt text encrypted with encrypt()
   *
   * @param encrypted - Base64-encoded encrypted data (iv:tag:ciphertext)
   * @param key - Encryption key (must be same as used for encryption)
   * @returns Decrypted plaintext
   * @throws Error if decryption fails (wrong key, corrupted data)
   */
  static decrypt(encrypted: string, key: string): string {
    if (!encrypted) {
      throw new Error('Cannot decrypt empty string');
    }

    if (!key) {
      throw new Error('Encryption key cannot be empty');
    }

    // Split iv:tag:ciphertext
    const parts = encrypted.split(':');
    if (parts.length !== 3) {
      throw new Error('Invalid encrypted data format (expected iv:tag:ciphertext)');
    }

    const ivEncoded = parts[0]!;
    const tagEncoded = parts[1]!;
    const ciphertextEncoded = parts[2]!;

    // Decode from base64
    const iv = Buffer.from(ivEncoded, 'base64');
    const tag = Buffer.from(tagEncoded, 'base64');
    const ciphertext = Buffer.from(ciphertextEncoded, 'base64');

    // Validate lengths
    if (iv.length !== this.IV_LENGTH) {
      throw new Error(`Invalid IV length: expected ${this.IV_LENGTH}, got ${iv.length}`);
    }

    if (tag.length !== this.TAG_LENGTH) {
      throw new Error(`Invalid tag length: expected ${this.TAG_LENGTH}, got ${tag.length}`);
    }

    // Derive 256-bit key (must match encryption)
    const derivedKey = createHash('sha256').update(key).digest();

    // Create decipher
    const decipher = createDecipheriv(this.CIPHER, derivedKey, iv);
    decipher.setAuthTag(tag);

    // Decrypt
    let plaintext = decipher.update(ciphertext);
    try {
      plaintext = Buffer.concat([plaintext, decipher.final()]);
    } catch {
      throw new Error('Decryption failed: Invalid key or corrupted data');
    }

    return plaintext.toString('utf8');
  }

  /**
   * Check if the cipher is supported by the Node.js crypto module
   *
   * @returns True if AES-256-GCM is available
   */
  static isSupported(): boolean {
    try {
      return getCiphers().includes(this.CIPHER);
    } catch {
      return false;
    }
  }
}
