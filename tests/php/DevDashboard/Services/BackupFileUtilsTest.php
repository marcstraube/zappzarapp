<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\BackupFileUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BackupFileUtils — filename parsing and validation
 */
#[CoversClass(BackupFileUtils::class)]
class BackupFileUtilsTest extends TestCase
{
    private BackupFileUtils $utils;

    protected function setUp(): void
    {
        parent::setUp();
        $this->utils = new BackupFileUtils();
    }

    // ==================== parseFilename – new format ====================

    public function testParseFilenameNewFormatPostgresUnencrypted(): void
    {
        $result = $this->utils->parseFilename('postgres_mydb_20260126_121905.sql');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('encrypted', $result);
        $this->assertArrayHasKey('dbType', $result);
        $this->assertArrayHasKey('dbName', $result);

        $this->assertFalse($result['encrypted']);
        $this->assertSame(strtotime('2026-01-26 12:19:05'), $result['timestamp']);
        // dbType/dbName are optional in the union type; assertArrayHasKey confirms
        // their presence at runtime; PHPStan cannot narrow optional shape keys here
        $this->assertSame('postgres', $result['dbType']); // @phpstan-ignore offsetAccess.notFound (confirmed by assertArrayHasKey above)
        $this->assertSame('mydb', $result['dbName']);     // @phpstan-ignore offsetAccess.notFound (confirmed by assertArrayHasKey above)
    }

    public function testParseFilenameNewFormatMariadbUnencrypted(): void
    {
        $result = $this->utils->parseFilename('mariadb_shopdb_20260301_093000.sql');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('dbType', $result);
        $this->assertArrayHasKey('dbName', $result);
        $this->assertSame('mariadb', $result['dbType']); // @phpstan-ignore offsetAccess.notFound (confirmed by assertArrayHasKey above)
        $this->assertSame('shopdb', $result['dbName']);  // @phpstan-ignore offsetAccess.notFound (confirmed by assertArrayHasKey above)
        $this->assertFalse($result['encrypted']);
    }

    public function testParseFilenameNewFormatEncrypted(): void
    {
        $result = $this->utils->parseFilename('postgres_mydb_20260126_121905.sql.gz.enc');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('dbType', $result);
        $this->assertArrayHasKey('dbName', $result);
        $this->assertSame('postgres', $result['dbType']); // @phpstan-ignore offsetAccess.notFound (confirmed by assertArrayHasKey above)
        $this->assertSame('mydb', $result['dbName']);     // @phpstan-ignore offsetAccess.notFound (confirmed by assertArrayHasKey above)
        $this->assertTrue($result['encrypted']);
    }

    public function testParseFilenameNewFormatTimestampIsCorrect(): void
    {
        $result = $this->utils->parseFilename('postgres_app_20260615_235959.sql');

        $this->assertIsArray($result);
        $this->assertSame(strtotime('2026-06-15 23:59:59'), $result['timestamp']);
    }

    // ==================== parseFilename – legacy format ====================

    public function testParseFilenameLegacyFormat(): void
    {
        $result = $this->utils->parseFilename('backup_2026-01-26_12-30-45.sql');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertArrayHasKey('encrypted', $result);
        $this->assertFalse($result['encrypted']);
        $this->assertArrayNotHasKey('dbType', $result);
        $this->assertArrayNotHasKey('dbName', $result);
        $this->assertSame(strtotime('2026-01-26 12:30:45'), $result['timestamp']);
    }

    // ==================== parseFilename – invalid input ====================

    public function testParseFilenameInvalidFormatReturnsNull(): void
    {
        $this->assertNull($this->utils->parseFilename('random_file.txt'));
    }

    public function testParseFilenameEmptyStringReturnsNull(): void
    {
        $this->assertNull($this->utils->parseFilename(''));
    }

    public function testParseFilenamePartialNewFormatReturnsNull(): void
    {
        // Wrong db type
        $this->assertNull($this->utils->parseFilename('mysql_mydb_20260126_121905.sql'));
    }

    public function testParseFilenamePartialLegacyFormatReturnsNull(): void
    {
        // Missing seconds component
        $this->assertNull($this->utils->parseFilename('backup_2026-01-26_12-30.sql'));
    }

    // ==================== validateFilename ====================

    /**
     * @return array<string, array{string}>
     */
    public static function validFilenameProvider(): array
    {
        return [
            'postgres unencrypted' => ['postgres_mydb_20260126_121905.sql'],
            'mariadb unencrypted'  => ['mariadb_shopdb_20260301_093000.sql'],
            'postgres encrypted'   => ['postgres_mydb_20260126_121905.sql.gz.enc'],
            'mariadb encrypted'    => ['mariadb_app_20260615_235959.sql.gz.enc'],
            'legacy format'        => ['backup_2026-01-26_12-30-45.sql'],
        ];
    }

    #[DataProvider('validFilenameProvider')]
    public function testValidateFilenameAcceptsValidFilenames(string $filename): void
    {
        $this->assertTrue($this->utils->validateFilename($filename));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidFilenameProvider(): array
    {
        return [
            'plain text'            => ['random.txt'],
            'wrong db type'         => ['mysql_mydb_20260126_121905.sql'],
            'missing date'          => ['postgres_mydb.sql'],
            'empty string'          => [''],
            'path traversal'        => ['../etc/passwd'],
            'wrong legacy prefix'   => ['dump_2026-01-26_12-30-45.sql'],
            'wrong extension'       => ['postgres_mydb_20260126_121905.tar.gz'],
        ];
    }

    #[DataProvider('invalidFilenameProvider')]
    public function testValidateFilenameRejectsInvalidFilenames(string $filename): void
    {
        $this->assertFalse($this->utils->validateFilename($filename));
    }
}
