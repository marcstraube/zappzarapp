<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\LogService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogService::class)]
class LogServiceSourcesTest extends TestCase
{
    private LogService $service;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/log_service_sources_test_' . uniqid();
        mkdir($this->tempDir, 0o755, recursive: true);

        $this->service = new LogService(logsDir: $this->tempDir . '/');
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }

        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    private function createLogFile(string $name, string $content = ''): string
    {
        $path   = $this->tempDir . '/' . $name;
        $result = file_put_contents($path, $content);

        $this->assertNotFalse($result, sprintf('Failed to write fixture file "%s" — check temp dir permissions', $path));

        return $path;
    }

    // ==================== getAvailableLogSources ====================

    #[Test]
    public function testGetAvailableLogSourcesReturnsArray(): void
    {
        $sources = $this->service->getAvailableLogSources();

        $this->assertIsArray($sources);
    }

    #[Test]
    public function testGetAvailableLogSourcesAlwaysContainsDocker(): void
    {
        $sources = $this->service->getAvailableLogSources();

        // 'docker' source is always available=true
        $this->assertArrayHasKey('docker', $sources);
    }

    #[Test]
    public function testGetAvailableLogSourcesAlwaysContainsNginx(): void
    {
        $sources = $this->service->getAvailableLogSources();

        // 'nginx' source is always available=true
        $this->assertArrayHasKey('nginx', $sources);
    }

    #[Test]
    public function testGetAvailableLogSourcesDockerHasRequiredKeys(): void
    {
        $sources = $this->service->getAvailableLogSources();
        $docker  = $sources['docker'];

        $this->assertArrayHasKey('name', $docker);
        $this->assertArrayHasKey('description', $docker);
        $this->assertArrayHasKey('type', $docker);
        $this->assertArrayHasKey('available', $docker);
        $this->assertArrayHasKey('command', $docker);
        $this->assertArrayHasKey('services', $docker);
        $this->assertSame('docker', $docker['type']);
        $this->assertIsArray($docker['services']);
    }

    #[Test]
    public function testGetAvailableLogSourcesDockerServicesIncludeNginx(): void
    {
        $sources  = $this->service->getAvailableLogSources();
        $docker   = $sources['docker'];
        $services = $docker['services'];

        $this->assertContains('nginx', $services);
    }

    #[Test]
    public function testGetAvailableLogSourcesApplicationPresentWhenDirExists(): void
    {
        // tempDir exists, so 'application' source should be included
        $sources = $this->service->getAvailableLogSources();

        $this->assertArrayHasKey('application', $sources);
        $this->assertSame('file', $sources['application']['type']);
    }

    #[Test]
    public function testGetAvailableLogSourcesApplicationAbsentWhenDirMissing(): void
    {
        // Construct with a non-existent dir
        $missingDir = sys_get_temp_dir() . '/log_service_no_such_dir_' . uniqid() . '/';
        $service    = new LogService(logsDir: $missingDir);

        $sources = $service->getAvailableLogSources();

        $this->assertArrayNotHasKey('application', $sources);
    }

    #[Test]
    public function testGetAvailableLogSourcesFilteredToOnlyAvailable(): void
    {
        $sources = $this->service->getAvailableLogSources();

        foreach ($sources as $key => $source) {
            $this->assertTrue(
                $source['available'],
                sprintf('Source "%s" should not be in the result when available=false', $key),
            );
        }
    }

    #[Test]
    public function testGetAvailableLogSourcesOptionalServiceRespectEnv(): void
    {
        // Mercure is only available when ENABLE_MERCURE=true
        putenv('ENABLE_MERCURE=false');

        $sources = $this->service->getAvailableLogSources();
        $this->assertArrayNotHasKey('mercure', $sources);

        putenv('ENABLE_MERCURE=true');
        $sources = $this->service->getAvailableLogSources();
        $this->assertArrayHasKey('mercure', $sources);

        // Restore
        putenv('ENABLE_MERCURE');
    }

    #[Test]
    public function testGetAvailableLogSourcesPHPDefaultEnabled(): void
    {
        putenv('ENABLE_PHP'); // clear
        $sources = $this->service->getAvailableLogSources();

        // PHP is enabled by default (available when ENABLE_PHP !== 'false')
        $this->assertArrayHasKey('php', $sources);
    }

    #[Test]
    public function testGetAvailableLogSourcesPHPDisabledWhenEnvFalse(): void
    {
        putenv('ENABLE_PHP=false');
        $sources = $this->service->getAvailableLogSources();

        $this->assertArrayNotHasKey('php', $sources);

        putenv('ENABLE_PHP');
    }

    // ==================== getLogStatistics ====================

    #[Test]
    public function testGetLogStatisticsReturnsRequiredKeys(): void
    {
        $stats = $this->service->getLogStatistics();

        $this->assertArrayHasKey('application_logs_count', $stats);
        $this->assertArrayHasKey('total_size', $stats);
        $this->assertArrayHasKey('total_size_formatted', $stats);
        $this->assertArrayHasKey('storage_dir_exists', $stats);
    }

    #[Test]
    public function testGetLogStatisticsCountIncludesCreatedFile(): void
    {
        // Baseline (empty temp dir, so count is 0)
        $before      = $this->service->getLogStatistics();
        $countBefore = $before['application_logs_count'];

        // Add a log file
        $this->createLogFile('test_stats_' . uniqid() . '.log', "test content\n");

        $after = $this->service->getLogStatistics();

        $this->assertGreaterThan($countBefore, $after['application_logs_count']);
    }

    #[Test]
    public function testGetLogStatisticsTotalSizeIsNumeric(): void
    {
        $stats = $this->service->getLogStatistics();

        $this->assertIsInt($stats['total_size']);
        $this->assertGreaterThanOrEqual(0, $stats['total_size']);
    }

    #[Test]
    public function testGetLogStatisticsFormattedSizeIsString(): void
    {
        $stats = $this->service->getLogStatistics();

        $this->assertIsString($stats['total_size_formatted']);
        $this->assertNotEmpty($stats['total_size_formatted']);
    }

    #[Test]
    public function testGetLogStatisticsStorageDirExistsTrue(): void
    {
        // tempDir exists, so storage_dir_exists must be true
        $stats = $this->service->getLogStatistics();

        $this->assertTrue($stats['storage_dir_exists']);
    }

    #[Test]
    public function testGetLogStatisticsStorageDirExistsFalseWhenMissing(): void
    {
        $missingDir = sys_get_temp_dir() . '/log_service_no_such_dir_' . uniqid() . '/';
        $service    = new LogService(logsDir: $missingDir);

        $stats = $service->getLogStatistics();

        $this->assertFalse($stats['storage_dir_exists']);
    }
}
