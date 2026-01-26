<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Controllers;

use DevDashboard\Controllers\DashboardController;
use DevDashboard\Response\Response;
use DevDashboard\Services\DatabaseService;
use DevDashboard\Services\DocsService;
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

}
