<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\DatabaseService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Utility method tests for DatabaseService
 *
 * @covers \DevDashboard\Services\DatabaseService
 */
class DatabaseServiceUtilityTest extends TestCase
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
}
