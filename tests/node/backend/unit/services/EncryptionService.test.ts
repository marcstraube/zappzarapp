/**
 * Tests for EncryptionService
 */

import { describe, it, expect } from 'vitest';
import { EncryptionService } from '@backend/Shared/Encryption/EncryptionService';

describe('EncryptionService', () => {
  const TEST_KEY = 'test-encryption-key-32-bytes-long';

  describe('encrypt', () => {
    it('should return a non-empty string', () => {
      const plaintext = 'sensitive data';
      const encrypted = EncryptionService.encrypt(plaintext, TEST_KEY);

      expect(encrypted).toBeTruthy();
      expect(typeof encrypted).toBe('string');
      expect(encrypted).not.toBe(plaintext);
    });

    it('should produce encrypted data with three colon-separated parts', () => {
      const plaintext = 'test';
      const encrypted = EncryptionService.encrypt(plaintext, TEST_KEY);

      // Format: iv:tag:ciphertext (each base64-encoded)
      const parts = encrypted.split(':');
      expect(parts).toHaveLength(3);
    });

    it('should produce different ciphertext for same plaintext (due to random IV)', () => {
      const plaintext = 'test data';

      const encrypted1 = EncryptionService.encrypt(plaintext, TEST_KEY);
      const encrypted2 = EncryptionService.encrypt(plaintext, TEST_KEY);

      // Should be different (due to random IV)
      expect(encrypted1).not.toBe(encrypted2);

      // But both should decrypt to same plaintext
      expect(EncryptionService.decrypt(encrypted1, TEST_KEY)).toBe(plaintext);
      expect(EncryptionService.decrypt(encrypted2, TEST_KEY)).toBe(plaintext);
    });

    it('should produce different ciphertext for different keys', () => {
      const plaintext = 'test data';
      const key1 = 'key-1';
      const key2 = 'key-2';

      const encrypted1 = EncryptionService.encrypt(plaintext, key1);
      const encrypted2 = EncryptionService.encrypt(plaintext, key2);

      expect(encrypted1).not.toBe(encrypted2);
    });

    it('should throw error when encrypting empty string', () => {
      expect(() => {
        EncryptionService.encrypt('', TEST_KEY);
      }).toThrow('Cannot encrypt empty string');
    });

    it('should throw error when key is empty', () => {
      expect(() => {
        EncryptionService.encrypt('test', '');
      }).toThrow('Encryption key cannot be empty');
    });

    it('should handle long strings', () => {
      const longText = 'Lorem ipsum dolor sit amet. '.repeat(1000); // ~28KB
      const encrypted = EncryptionService.encrypt(longText, TEST_KEY);
      const decrypted = EncryptionService.decrypt(encrypted, TEST_KEY);

      expect(decrypted).toBe(longText);
    });

    it('should handle unicode characters', () => {
      const unicode = '🔒 Verschlüsselte Daten 🔑 中文 العربية';
      const encrypted = EncryptionService.encrypt(unicode, TEST_KEY);
      const decrypted = EncryptionService.decrypt(encrypted, TEST_KEY);

      expect(decrypted).toBe(unicode);
    });

    it('should handle special characters', () => {
      const specialChars = 'Line1\nLine2\tTabbed\r\nWindows\0Null\'Quote"DoubleQuote';
      const encrypted = EncryptionService.encrypt(specialChars, TEST_KEY);
      const decrypted = EncryptionService.decrypt(encrypted, TEST_KEY);

      expect(decrypted).toBe(specialChars);
    });
  });

  describe('decrypt', () => {
    it('should return original plaintext', () => {
      const plaintext = 'sensitive data';
      const encrypted = EncryptionService.encrypt(plaintext, TEST_KEY);
      const decrypted = EncryptionService.decrypt(encrypted, TEST_KEY);

      expect(decrypted).toBe(plaintext);
    });

    it('should throw error when decrypting with wrong key', () => {
      const plaintext = 'sensitive data';
      const correctKey = 'correct-key';
      const wrongKey = 'wrong-key';

      const encrypted = EncryptionService.encrypt(plaintext, correctKey);

      expect(() => {
        EncryptionService.decrypt(encrypted, wrongKey);
      }).toThrow('Decryption failed: Invalid key or corrupted data');
    });

    it('should throw error when decrypting empty string', () => {
      expect(() => {
        EncryptionService.decrypt('', TEST_KEY);
      }).toThrow('Cannot decrypt empty string');
    });

    it('should throw error when key is empty', () => {
      expect(() => {
        EncryptionService.decrypt('some:encrypted:data', '');
      }).toThrow('Encryption key cannot be empty');
    });

    it('should throw error for invalid encrypted data format', () => {
      expect(() => {
        EncryptionService.decrypt('invalid-format', TEST_KEY);
      }).toThrow('Invalid encrypted data format');
    });

    it('should throw error for invalid IV length', () => {
      // Create encrypted data with invalid IV length
      const invalidEncrypted = 'aW52YWxpZA==:dGFn:Y2lwaGVydGV4dA=='; // "invalid" base64

      expect(() => {
        EncryptionService.decrypt(invalidEncrypted, TEST_KEY);
      }).toThrow('Invalid IV length');
    });

    it('should throw error for invalid tag length', () => {
      // Create encrypted data with valid IV but invalid tag
      const validIv = Buffer.alloc(12).toString('base64'); // 12 bytes IV
      const invalidTag = 'aW52YWxpZA=='; // Invalid tag length
      const ciphertext = 'Y2lwaGVydGV4dA==';
      const invalidEncrypted = `${validIv}:${invalidTag}:${ciphertext}`;

      expect(() => {
        EncryptionService.decrypt(invalidEncrypted, TEST_KEY);
      }).toThrow('Invalid tag length');
    });
  });

  describe('isSupported', () => {
    it('should return true for AES-256-GCM support', () => {
      // AES-256-GCM should be supported in modern Node.js
      expect(EncryptionService.isSupported()).toBe(true);
    });
  });

  describe('roundtrip tests', () => {
    it('should correctly encrypt and decrypt multiple times', () => {
      const plaintext = 'test data';
      let encrypted = plaintext;

      // Encrypt 3 times
      encrypted = EncryptionService.encrypt(encrypted, TEST_KEY);
      encrypted = EncryptionService.encrypt(encrypted, TEST_KEY);
      encrypted = EncryptionService.encrypt(encrypted, TEST_KEY);

      // Decrypt 3 times
      let decrypted = EncryptionService.decrypt(encrypted, TEST_KEY);
      decrypted = EncryptionService.decrypt(decrypted, TEST_KEY);
      decrypted = EncryptionService.decrypt(decrypted, TEST_KEY);

      expect(decrypted).toBe(plaintext);
    });

    it('should handle empty object serialization', () => {
      const data = JSON.stringify({});
      const encrypted = EncryptionService.encrypt(data, TEST_KEY);
      const decrypted = EncryptionService.decrypt(encrypted, TEST_KEY);

      expect(decrypted).toBe(data);
      expect(JSON.parse(decrypted)).toEqual({});
    });

    it('should handle complex JSON objects', () => {
      const complexObject = {
        user: 'john',
        data: ['item1', 'item2'],
        nested: { key: 'value', number: 123 },
        unicode: '中文',
      };
      const data = JSON.stringify(complexObject);
      const encrypted = EncryptionService.encrypt(data, TEST_KEY);
      const decrypted = EncryptionService.decrypt(encrypted, TEST_KEY);

      expect(decrypted).toBe(data);
      expect(JSON.parse(decrypted)).toEqual(complexObject);
    });
  });
});
