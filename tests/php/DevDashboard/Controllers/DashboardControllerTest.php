<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Controllers;

use DevDashboard\Controllers\DashboardController;
use DevDashboard\Response\JsonResponse;
use DevDashboard\Response\Response;
use DevDashboard\Services\DatabaseService;
use DevDashboard\Services\DocsService;
use DevDashboard\Services\HealthCheckService;
use DevDashboard\Services\LogService;
use DevDashboard\Services\QualityService;
use DevDashboard\Services\SystemInfoService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \DevDashboard\Controllers\DashboardController
 */
class DashboardControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set minimal database config for testing (if not already set)
        if (!getenv('DB_PASSWORD') && !getenv('DB_PASSWORD_FILE')) {
            putenv('DB_PASSWORD=test_password');
        }

        if (!getenv('DATABASE_URL')) {
            putenv('DATABASE_URL=postgres://test:test@localhost:5432/test');
        }
    }

    private function createController(): DashboardController
    {
        return new DashboardController(
            new HealthCheckService(),
            new SystemInfoService(),
            new QualityService(),
            new LogService(),
            new DatabaseService(),
            new DocsService(),
        );
    }

    /**
     * Helper to get JSON data from a Response object
     *
     * @return array<string, mixed>
     */
    private function getJsonFromResponse(Response $response): array
    {
        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertNotFalse($output, 'Output buffer should not be empty');
        $this->assertJson($output);

        return json_decode($output, true);
    }

    public function testControllerCanBeInstantiated(): void
    {
        $controller = $this->createController();

        $this->assertInstanceOf(DashboardController::class, $controller);
    }

    public function testApiHealthCheckReturnsJsonResponse(): void
    {
        $controller = $this->createController();

        $response = $controller->apiHealthCheck();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = $this->getJsonFromResponse($response);
        $this->assertArrayHasKey('status', $data);
    }

    public function testApiServicesStatusReturnsJsonResponse(): void
    {
        $controller = $this->createController();

        $response = $controller->apiServicesStatus();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = $this->getJsonFromResponse($response);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('core', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('optional', $data);
    }

    public function testApiLogContentReturnsErrorWithoutFilename(): void
    {
        $_GET       = [];
        $controller = $this->createController();

        $response = $controller->apiLogContent();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = $this->getJsonFromResponse($response);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('No filename provided', $data['error']);
    }

    public function testApiLogContentReturnsErrorForNonExistentFile(): void
    {
        $_GET['file'] = 'nonexistent.log';
        $controller   = $this->createController();

        $response = $controller->apiLogContent();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = $this->getJsonFromResponse($response);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Log file not found', $data['error']);
    }

    // ==================== BACKUP API ENDPOINT TESTS ====================

    public function testApiListBackupsReturnsJsonResponse(): void
    {
        $controller = $this->createController();

        $response = $controller->apiListBackups();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = $this->getJsonFromResponse($response);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('backups', $data);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['backups']);
    }

    public function testApiCreateBackupReturnsJsonResponse(): void
    {
        $controller = $this->createController();

        $response = $controller->apiCreateBackup();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = $this->getJsonFromResponse($response);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertIsBool($data['success']);
        $this->assertIsString($data['message']);
    }

    public function testApiCreateBackupWithRetentionParameter(): void
    {
        $_GET['retention'] = '7';
        $controller        = $this->createController();

        $response = $controller->apiCreateBackup();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = $this->getJsonFromResponse($response);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('message', $data);

        // Clean up
        unset($_GET['retention']);
    }

    public function testApiRestoreBackupReturnsErrorWithoutFilename(): void
    {
        // Mock php://input for missing filename
        $controller = $this->createController();

        // We can't easily mock php://input, so we'll test the response structure
        $response = $controller->apiRestoreBackup();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = $this->getJsonFromResponse($response);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Missing required parameter', $data['message']);
    }

    public function testApiDeleteBackupReturnsErrorWithoutFilename(): void
    {
        $controller = $this->createController();

        $response = $controller->apiDeleteBackup();

        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = $this->getJsonFromResponse($response);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Missing required parameter', $data['message']);
    }

    public function testDatabaseMethodIncludesBackupStats(): void
    {
        $controller = $this->createController();

        $response = $controller->database();

        $this->assertInstanceOf(Response::class, $response);

        // Extract rendered HTML to verify backup_stats is passed to template
        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertNotFalse($output);
        // The template should render backup stats
        $this->assertStringContainsString('Backups', $output);
    }

    public function testApiBackupEndpointsReturnCorrectStatusCodes(): void
    {
        $controller = $this->createController();

        // List backups should return 200
        $response   = $controller->apiListBackups();
        $reflection = new ReflectionClass($response);
        $property   = $reflection->getProperty('status');
        $this->assertEquals(200, $property->getValue($response));

        // Create backup (may succeed or fail depending on environment)
        $response   = $controller->apiCreateBackup();
        $statusCode = $property->getValue($response);
        $this->assertContains($statusCode, [200, 500]);

        // Restore without filename should return 400
        $response = $controller->apiRestoreBackup();
        $this->assertEquals(400, $property->getValue($response));

        // Delete without filename should return 400
        $response = $controller->apiDeleteBackup();
        $this->assertEquals(400, $property->getValue($response));
    }
}
