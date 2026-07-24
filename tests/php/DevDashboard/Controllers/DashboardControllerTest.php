<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Controllers;

use App\Infrastructure\DatabaseConfig as AppDatabaseConfig;
use DevDashboard\Controllers\DashboardController;
use DevDashboard\Infrastructure\TwigService;
use DevDashboard\Services\CommandRunner;
use DevDashboard\Services\CoverageParser;
use DevDashboard\Services\DatabaseBackupService;
use DevDashboard\Services\DatabaseCommandBuilder;
use DevDashboard\Services\DatabaseConfig;
use DevDashboard\Services\DatabaseMetricsService;
use DevDashboard\Services\DatabaseService;
use DevDashboard\Services\DocsService;
use DevDashboard\Services\HealthCheckService;
use DevDashboard\Services\LogService;
use DevDashboard\Services\QualityService;
use DevDashboard\Services\SystemInfoService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DashboardController::class)]
#[UsesClass(AppDatabaseConfig::class)]
#[UsesClass(CommandRunner::class)]
#[UsesClass(CoverageParser::class)]
#[UsesClass(DatabaseBackupService::class)]
#[UsesClass(DatabaseCommandBuilder::class)]
#[UsesClass(DatabaseConfig::class)]
#[UsesClass(DatabaseMetricsService::class)]
#[UsesClass(DatabaseService::class)]
#[UsesClass(DocsService::class)]
#[UsesClass(HealthCheckService::class)]
#[UsesClass(LogService::class)]
#[UsesClass(QualityService::class)]
#[UsesClass(SystemInfoService::class)]
#[UsesClass(TwigService::class)]
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
