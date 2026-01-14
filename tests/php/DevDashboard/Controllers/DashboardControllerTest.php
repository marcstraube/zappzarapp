<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Controllers;

use DevDashboard\Controllers\DashboardController;
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

    public function testControllerCanBeInstantiated(): void
    {
        $controller = $this->createController();

        $this->assertInstanceOf(DashboardController::class, $controller);
    }

    public function testApiHealthCheckReturnsValidJson(): void
    {
        $controller = $this->createController();

        ob_start();
        $controller->apiHealthCheck();
        $output = ob_get_clean();

        $this->assertNotFalse($output, 'Output buffer should not be empty');
        $this->assertJson($output);

        $data = json_decode($output, true);
        $this->assertArrayHasKey('status', $data);
    }

    public function testApiServicesStatusReturnsValidJson(): void
    {
        $controller = $this->createController();

        ob_start();
        $controller->apiServicesStatus();
        $output = ob_get_clean();

        $this->assertNotFalse($output, 'Output buffer should not be empty');
        $this->assertJson($output);

        $data = json_decode($output, true);
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('core', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('optional', $data);
    }

    public function testApiLogContentReturnsErrorWithoutFilename(): void
    {
        $_GET       = [];
        $controller = $this->createController();

        ob_start();
        $controller->apiLogContent();
        $output = ob_get_clean();

        $this->assertNotFalse($output, 'Output buffer should not be empty');
        $this->assertJson($output);

        $data = json_decode($output, true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('No filename provided', $data['error']);
    }

    public function testApiLogContentReturnsErrorForNonExistentFile(): void
    {
        $_GET['file'] = 'nonexistent.log';
        $controller   = $this->createController();

        ob_start();
        $controller->apiLogContent();
        $output = ob_get_clean();

        $this->assertNotFalse($output, 'Output buffer should not be empty');
        $this->assertJson($output);

        $data = json_decode($output, true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Log file not found', $data['error']);
    }
}
