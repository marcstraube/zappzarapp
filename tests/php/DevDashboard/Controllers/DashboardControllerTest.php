<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Controllers;

use DevDashboard\Controllers\DashboardController;
use DevDashboard\Infrastructure\TwigService;
use DevDashboard\Services\DatabaseService;
use DevDashboard\Services\DocsService;
use DevDashboard\Services\HealthCheckService;
use DevDashboard\Services\LogService;
use DevDashboard\Services\QualityService;
use DevDashboard\Services\SystemInfoService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DashboardController::class)]
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
            TwigService::createForDevelopment(
                __DIR__ . '/../../../../templates',
                __DIR__ . '/../../../../build/cache/twig'
            ),
        );
    }

    public function testControllerCanBeInstantiated(): void
    {
        $controller = $this->createController();

        /** @noinspection PhpConditionAlreadyCheckedInspection Smoke test to verify constructor succeeds */
        $this->assertInstanceOf(DashboardController::class, $controller);
    }

}
