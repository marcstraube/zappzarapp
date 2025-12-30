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

    public function testControllerHasRequiredMethods(): void
    {
        $controller = new \DevDashboard\Controllers\DashboardController();

        $this->assertTrue(method_exists($controller, 'index'));
        $this->assertTrue(method_exists($controller, 'system'));
        $this->assertTrue(method_exists($controller, 'health'));
        $this->assertTrue(method_exists($controller, 'quality'));
        $this->assertTrue(method_exists($controller, 'database'));
        $this->assertTrue(method_exists($controller, 'logs'));
        $this->assertTrue(method_exists($controller, 'apiHealthCheck'));
        $this->assertTrue(method_exists($controller, 'apiContainerStatus'));
    }

    public function testApiHealthCheckReturnsValidJson(): void
    {
        $controller = new \DevDashboard\Controllers\DashboardController();

        ob_start();
        $controller->apiHealthCheck();
        $output = ob_get_clean();

        $this->assertJson($output);

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
    }

    public function testApiContainerStatusReturnsValidJson(): void
    {
        $controller = new \DevDashboard\Controllers\DashboardController();

        ob_start();
        $controller->apiContainerStatus();
        $output = ob_get_clean();

        $this->assertJson($output);

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }
}
