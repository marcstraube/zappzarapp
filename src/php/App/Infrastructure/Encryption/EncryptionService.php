<?php

declare(strict_types=1);

namespace App\Infrastructure\Encryption;

use RuntimeException;

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
 * Usage:
 * <code>
 * $encryptionKey = $_ENV['ENCRYPTION_KEY'] ?? throw new RuntimeException('ENCRYPTION_KEY not set');
 *
 * // Encrypt
 * $encrypted = EncryptionService::encrypt('sensitive data', $encryptionKey);
 * // Store $encrypted (base64 string) in database VARCHAR/TEXT column
 *
 * // Decrypt
 * $decrypted = EncryptionService::decrypt($encrypted, $encryptionKey);
 * </code>
 *
 * Database integration:
 * - PostgreSQL: Use encrypt_text()/decrypt_text() functions (see migrations/postgresql/000_encryption_helpers.sql)
 * - MariaDB: Use encrypt_text()/decrypt_text() functions (see migrations/mariadb/000_encryption_helpers.sql)
 * - OR use this PHP class for application-level encryption
 *
 * @package Infrastructure\Encryption
 */
final class EncryptionService
{
    /**
     * Encryption cipher (AES-256-GCM)
     */
    private const string CIPHER = 'aes-256-gcm';

    /**
     * IV (Initialization Vector) length in bytes
     */
    private const int IV_LENGTH = 12; // 96 bits for GCM mode

    /**
     * Encrypt text using AES-256-GCM
     *
     * Returns base64-encoded string in format: iv:tag:ciphertext
     * Store this in a VARCHAR or TEXT column in database.
     *
     * @param string $plaintext Text to encrypt
     * @param string $key Encryption key (from $_ENV['ENCRYPTION_KEY'])
     * @return string Base64-encoded encrypted data (iv:tag:ciphertext)
     * @throws RuntimeException If encryption fails
     */
    public static function encrypt(string $plaintext, string $key): string
    {
        if ($plaintext === '') {
            throw new RuntimeException('Cannot encrypt empty string');
        }

        if ($key === '') {
            throw new RuntimeException('Encryption key cannot be empty');
        }

        // Generate random IV (Initialization Vector)
        $iv = random_bytes(self::IV_LENGTH);

        // Derive 256-bit key from the provided key using SHA-256
        $derivedKey = hash('sha256', $key, true);

        // Encrypt using AES-256-GCM (provides authenticity + confidentiality)
        $tag        = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $derivedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        // Combine IV + tag + ciphertext and encode as base64
        // Format: iv:tag:ciphertext (each base64-encoded)
        return base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($ciphertext);
    }

    /**
     * Decrypt text encrypted with encrypt()
     *
     * @param string $encrypted Base64-encoded encrypted data (iv:tag:ciphertext)
     * @param string $key Encryption key (must be same as used for encryption)
     * @return string Decrypted plaintext
     * @throws RuntimeException If decryption fails (wrong key, corrupted data)
     */
    public static function decrypt(string $encrypted, string $key): string
    {
        if ($encrypted === '') {
            throw new RuntimeException('Cannot decrypt empty string');
        }

        if ($key === '') {
            throw new RuntimeException('Encryption key cannot be empty');
        }

        // Split iv:tag:ciphertext
        $parts = explode(':', $encrypted);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid encrypted data format (expected iv:tag:ciphertext)');
        }

        [$ivEncoded, $tagEncoded, $ciphertextEncoded] = $parts;

        // Decode from base64
        $iv         = base64_decode($ivEncoded, true);
        $tag        = base64_decode($tagEncoded, true);
        $ciphertext = base64_decode($ciphertextEncoded, true);

        if ($iv === false || $tag === false || $ciphertext === false) {
            throw new RuntimeException('Invalid base64 encoding in encrypted data');
        }

        // Derive 256-bit key (must match encryption)
        $derivedKey = hash('sha256', $key, true);

        // Decrypt using AES-256-GCM
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $derivedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed: Invalid key or corrupted data');
        }

        return $plaintext;
    }

    /**
     * Check if OpenSSL supports the required cipher
     *
     * @return bool True if AES-256-GCM is available
     */
    public static function isSupported(): bool
    {
        return in_array(self::CIPHER, openssl_get_cipher_methods(), true);
    }
}
