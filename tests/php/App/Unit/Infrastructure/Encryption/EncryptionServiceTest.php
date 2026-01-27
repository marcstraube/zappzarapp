<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Encryption;

use App\Infrastructure\Encryption\EncryptionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for EncryptionService
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
#[CoversClass(EncryptionService::class)]
final class EncryptionServiceTest extends TestCase
{
    private const string TEST_KEY = 'test-encryption-key-32-bytes-long';

    public function testEncryptReturnsNonEmptyString(): void
    {
        $plaintext = 'sensitive data';
        $encrypted = EncryptionService::encrypt($plaintext, self::TEST_KEY);

        $this->assertNotEmpty($encrypted);
        $this->assertIsString($encrypted);
        $this->assertNotEquals($plaintext, $encrypted);
    }

    public function testEncryptedDataContainsThreeColonSeparatedParts(): void
    {
        $plaintext = 'test';
        $encrypted = EncryptionService::encrypt($plaintext, self::TEST_KEY);

        // Format: iv:tag:ciphertext (each base64-encoded)
        $parts = explode(':', $encrypted);
        $this->assertCount(3, $parts);
    }

    public function testDecryptReturnsOriginalPlaintext(): void
    {
        $plaintext = 'sensitive data';
        $encrypted = EncryptionService::encrypt($plaintext, self::TEST_KEY);
        $decrypted = EncryptionService::decrypt($encrypted, self::TEST_KEY);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptWithDifferentKeysProducesDifferentCiphertext(): void
    {
        $plaintext = 'test data';
        $key1      = 'key-1';
        $key2      = 'key-2';

        $encrypted1 = EncryptionService::encrypt($plaintext, $key1);
        $encrypted2 = EncryptionService::encrypt($plaintext, $key2);

        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    public function testDecryptWithWrongKeyThrowsException(): void
    {
        $plaintext  = 'sensitive data';
        $correctKey = 'correct-key';
        $wrongKey   = 'wrong-key';

        $encrypted = EncryptionService::encrypt($plaintext, $correctKey);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed: Invalid key or corrupted data');

        EncryptionService::decrypt($encrypted, $wrongKey);
    }

    public function testEncryptEmptyStringThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot encrypt empty string');

        EncryptionService::encrypt('', self::TEST_KEY);
    }

    public function testEncryptWithEmptyKeyThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Encryption key cannot be empty');

        EncryptionService::encrypt('test', '');
    }

    public function testDecryptEmptyStringThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot decrypt empty string');

        EncryptionService::decrypt('', self::TEST_KEY);
    }

    public function testDecryptWithEmptyKeyThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Encryption key cannot be empty');

        EncryptionService::decrypt('some:encrypted:data', '');
    }

    public function testDecryptInvalidFormatThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid encrypted data format');

        // Invalid format (missing parts)
        EncryptionService::decrypt('invalid-format', self::TEST_KEY);
    }

    public function testDecryptInvalidBase64ThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid base64 encoding in encrypted data');

        // Invalid base64
        EncryptionService::decrypt('!!!:!!!:!!!', self::TEST_KEY);
    }

    public function testEncryptSamePlaintextTwiceProducesDifferentCiphertext(): void
    {
        $plaintext = 'test data';

        // Encrypt same plaintext twice
        $encrypted1 = EncryptionService::encrypt($plaintext, self::TEST_KEY);
        $encrypted2 = EncryptionService::encrypt($plaintext, self::TEST_KEY);

        // Should be different (due to random IV)
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to same plaintext
        $this->assertEquals($plaintext, EncryptionService::decrypt($encrypted1, self::TEST_KEY));
        $this->assertEquals($plaintext, EncryptionService::decrypt($encrypted2, self::TEST_KEY));
    }

    public function testEncryptLongString(): void
    {
        $longText  = str_repeat('Lorem ipsum dolor sit amet. ', 1000); // ~28KB
        $encrypted = EncryptionService::encrypt($longText, self::TEST_KEY);
        $decrypted = EncryptionService::decrypt($encrypted, self::TEST_KEY);

        $this->assertEquals($longText, $decrypted);
    }

    public function testEncryptUnicodeCharacters(): void
    {
        $unicode   = '🔒 Verschlüsselte Daten 🔑 中文 العربية';
        $encrypted = EncryptionService::encrypt($unicode, self::TEST_KEY);
        $decrypted = EncryptionService::decrypt($encrypted, self::TEST_KEY);

        $this->assertEquals($unicode, $decrypted);
    }

    public function testIsSupportedReturnsTrue(): void
    {
        // AES-256-GCM should be supported in modern PHP
        $this->assertTrue(EncryptionService::isSupported());
    }

    public function testEncryptDecryptWithSpecialCharacters(): void
    {
        $specialChars = "Line1\nLine2\tTabbed\r\nWindows\0Null'Quote\"DoubleQuote";
        $encrypted    = EncryptionService::encrypt($specialChars, self::TEST_KEY);
        $decrypted    = EncryptionService::decrypt($encrypted, self::TEST_KEY);

        $this->assertEquals($specialChars, $decrypted);
    }
}
