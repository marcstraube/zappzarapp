<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Controllers;

use DevDashboard\Controllers\DashboardController;
use DevDashboard\Response\JsonResponse;
use DevDashboard\Response\Response;
use DevDashboard\Services\DatabaseService;
use DevDashboard\Services\HealthCheckService;
use DevDashboard\Services\LogService;
use DevDashboard\Services\QualityService;
use DevDashboard\Services\SystemInfoService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DevDashboard\Controllers\DashboardController
 */
class DashboardControllerTest extends TestCase
{
    private function createController(): DashboardController
    {
        return new DashboardController(
            new HealthCheckService(),
            new SystemInfoService(),
            new QualityService(),
            new LogService(),
            new DatabaseService(),
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
}
