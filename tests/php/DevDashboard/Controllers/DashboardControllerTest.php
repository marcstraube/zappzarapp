<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * @covers \DevDashboard\Controllers\DashboardController
 */
class DashboardControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Load required dependencies
        require_once __DIR__ . '/../../../../src/php/DevDashboard/Services/HealthCheckService.php';
        require_once __DIR__ . '/../../../../src/php/DevDashboard/Services/SystemInfoService.php';
        require_once __DIR__ . '/../../../../src/php/DevDashboard/Controllers/DashboardController.php';
    }

    public function testControllerCanBeInstantiated(): void
    {
        $controller = new \DevDashboard\Controllers\DashboardController();

        $this->assertInstanceOf(\DevDashboard\Controllers\DashboardController::class, $controller);
    }

    public function testApiHealthCheckReturnsValidJson(): void
    {
        $controller = new \DevDashboard\Controllers\DashboardController();

        ob_start();
        $controller->apiHealthCheck();
        $output = ob_get_clean();

        $this->assertNotFalse($output, 'Output buffer should not be empty');
        $this->assertJson($output);

        $data = json_decode($output, true);
        $this->assertArrayHasKey('status', $data);
    }

    public function testApiContainerStatusReturnsValidJson(): void
    {
        $controller = new \DevDashboard\Controllers\DashboardController();

        ob_start();
        $controller->apiContainerStatus();
        $output = ob_get_clean();

        $this->assertNotFalse($output, 'Output buffer should not be empty');
        $this->assertJson($output);

        $data = json_decode($output, true);
        $this->assertNotEmpty($data);
    }
}
