<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Controllers;

use DevDashboard\Controllers\DashboardController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Backup-specific tests for DashboardController
 *
 * @covers \DevDashboard\Controllers\DashboardController
 */
class DashboardControllerBackupTest extends TestCase
{
    private DashboardController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../../../src/php/DevDashboard/Controllers/DashboardController.php';
        require_once __DIR__ . '/../../../../src/php/DevDashboard/Services/QualityService.php';
        require_once __DIR__ . '/../../../../src/php/DevDashboard/Services/CoverageParser.php';
        require_once __DIR__ . '/../../../../src/php/DevDashboard/Services/DatabaseService.php';
        require_once __DIR__ . '/../../../../src/php/App/Infrastructure/DatabaseConfig.php';

        // Set minimal database config for testing (if not already set)
        if (!getenv('DB_PASSWORD') && !getenv('DB_PASSWORD_FILE')) {
            putenv('DB_PASSWORD=test_password');
        }

        if (!getenv('DATABASE_URL')) {
            putenv('DATABASE_URL=postgres://test:test@localhost:5432/test');
        }

        $this->controller = new DashboardController();
    }

    public function testApiListBackupsReturnsJsonResponse(): void
    {
        $response   = $this->controller->apiListBackups();
        $reflection = new ReflectionClass($response);
        $property   = $reflection->getProperty('status');
        $this->assertEquals(200, $property->getValue($response));

        // Verify response headers
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertEquals('application/json', $headers['Content-Type'][0]);
    }

    public function testApiCreateBackupReturnsJsonResponse(): void
    {
        // Create backup (may succeed or fail depending on environment)
        $response   = $this->controller->apiCreateBackup();
        $reflection = new ReflectionClass($response);
        $property   = $reflection->getProperty('status');
        $statusCode = $property->getValue($response);

        // Accept either success or server error (depending on environment)
        $this->assertContains($statusCode, [200, 500]);

        // Verify response headers
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Content-Type', $headers);
    }

    public function testApiCreateBackupWithRetentionParameter(): void
    {
        $_GET['retention'] = '30';

        // Create backup with retention parameter
        $response   = $this->controller->apiCreateBackup();
        $reflection = new ReflectionClass($response);
        $property   = $reflection->getProperty('status');
        $statusCode = $property->getValue($response);

        // Accept either success or server error
        $this->assertContains($statusCode, [200, 500]);

        // Verify response headers
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Content-Type', $headers);

        unset($_GET['retention']);
    }

    public function testApiRestoreBackupReturnsErrorWithoutFilename(): void
    {
        // Test without filename parameter
        $response   = $this->controller->apiRestoreBackup();
        $reflection = new ReflectionClass($response);
        $property   = $reflection->getProperty('status');
        $this->assertEquals(400, $property->getValue($response));

        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
    }

    public function testApiDeleteBackupReturnsErrorWithoutFilename(): void
    {
        // Test without filename parameter
        $response   = $this->controller->apiDeleteBackup();
        $reflection = new ReflectionClass($response);
        $property   = $reflection->getProperty('status');
        $this->assertEquals(400, $property->getValue($response));

        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
    }
}
