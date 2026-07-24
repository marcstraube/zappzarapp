<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\CommandRunner;
use DevDashboard\Services\CoverageParser;
use DevDashboard\Services\QualityService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QualityService::class)]
#[UsesClass(CommandRunner::class)]
#[UsesClass(CoverageParser::class)]
class QualityServiceTest extends TestCase
{
    private QualityService $service;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../../../src/php/DevDashboard/Services/QualityService.php';
        $this->service = new QualityService();

        // Create temporary directory for test files
        $this->tempDir = sys_get_temp_dir() . '/quality_service_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temporary files
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    public function testGetQualityMetrics(): void
    {
        $metrics = $this->service->getQualityMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('php', $metrics);
        $this->assertArrayHasKey('node', $metrics);
        $this->assertArrayHasKey('code_stats', $metrics);
        $this->assertArrayHasKey('test_coverage', $metrics);

        // Test coverage should have php and node keys
        $this->assertArrayHasKey('php', $metrics['test_coverage']);
        $this->assertArrayHasKey('node', $metrics['test_coverage']);
    }

    public function testGetQuickActions(): void
    {
        $actions = $this->service->getQuickActions();

        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);

        // Check structure of first action
        $firstAction = $actions[0];
        $this->assertArrayHasKey('label', $firstAction);
        $this->assertArrayHasKey('command', $firstAction);
        $this->assertArrayHasKey('description', $firstAction);
    }
}
