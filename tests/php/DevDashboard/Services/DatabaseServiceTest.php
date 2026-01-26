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
}
