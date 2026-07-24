<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\BackupFileUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BackupFileUtils — encryption detection and byte formatting
 */
#[CoversClass(BackupFileUtils::class)]
class BackupFileUtilsEncryptionAndBytesTest extends TestCase
{
    private BackupFileUtils $utils;

    protected function setUp(): void
    {
        parent::setUp();
        $this->utils = new BackupFileUtils();
    }

    // ==================== isEncrypted ====================

    public function testIsEncryptedReturnsTrueForGzEnc(): void
    {
        $this->assertTrue($this->utils->isEncrypted('postgres_mydb_20260126_121905.sql.gz.enc'));
    }

    public function testIsEncryptedReturnsTrueForEncSuffix(): void
    {
        $this->assertTrue($this->utils->isEncrypted('backup.enc'));
    }

    public function testIsEncryptedReturnsFalseForSqlFile(): void
    {
        $this->assertFalse($this->utils->isEncrypted('postgres_mydb_20260126_121905.sql'));
    }

    public function testIsEncryptedReturnsFalseForLegacyFile(): void
    {
        $this->assertFalse($this->utils->isEncrypted('backup_2026-01-26_12-30-45.sql'));
    }

    // ==================== formatBytes ====================

    public function testFormatBytesBytes(): void
    {
        $this->assertSame('512 bytes', $this->utils->formatBytes(512));
    }

    public function testFormatBytesKilobytes(): void
    {
        $result = $this->utils->formatBytes(2048);
        $this->assertStringContainsString('KB', $result);
        $this->assertStringContainsString('2.00', $result);
    }

    public function testFormatBytesMegabytes(): void
    {
        $result = $this->utils->formatBytes(1048576);
        $this->assertStringContainsString('MB', $result);
        $this->assertStringContainsString('1.00', $result);
    }

    public function testFormatBytesGigabytes(): void
    {
        $result = $this->utils->formatBytes(1073741824);
        $this->assertStringContainsString('GB', $result);
        $this->assertStringContainsString('1.00', $result);
    }

    public function testFormatBytesZero(): void
    {
        $this->assertSame('0 bytes', $this->utils->formatBytes(0));
    }
}
