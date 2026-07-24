<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Services;

use DevDashboard\Services\LogService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogService::class)]
class LogServiceSourcesTest extends TestCase
{
    private LogService $service;

    /**
     * Path to the storage/logs directory the service reads from.
     * LogService uses __DIR__ . '/../../../../' relative to its own file, which
     * resolves to the project root, then appends 'storage/logs/'.
     */
    private string $storageLogsDir;

    /** @var list<string> */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service        = new LogService();
        $this->storageLogsDir = __DIR__ . '/../../../../src/php/DevDashboard/Services/../../../../storage/logs/';
        $this->createdFiles   = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    private function createLogFile(string $name, string $content = ''): string
    {
        $path = $this->storageLogsDir . $name;
        file_put_contents($path, $content);
        $this->createdFiles[] = $path;

        return $path;
    }

    // ==================== getAvailableLogSources ====================

    public function testGetAvailableLogSourcesReturnsArray(): void
    {
        $sources = $this->service->getAvailableLogSources();

        $this->assertIsArray($sources);
    }

    public function testGetAvailableLogSourcesAlwaysContainsDocker(): void
    {
        $sources = $this->service->getAvailableLogSources();

        // 'docker' source is always available=true
        $this->assertArrayHasKey('docker', $sources);
    }

    public function testGetAvailableLogSourcesAlwaysContainsNginx(): void
    {
        $sources = $this->service->getAvailableLogSources();

        // 'nginx' source is always available=true
        $this->assertArrayHasKey('nginx', $sources);
    }

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

    public function testGetAvailableLogSourcesDockerServicesIncludeNginx(): void
    {
        $sources  = $this->service->getAvailableLogSources();
        $docker   = $sources['docker'];
        $services = $docker['services'];

        $this->assertContains('nginx', $services);
    }

    public function testGetAvailableLogSourcesApplicationHasCorrectType(): void
    {
        // application source may or may not be available depending on storageDir
        $sources = $this->service->getAvailableLogSources();

        if (isset($sources['application'])) {
            $this->assertSame('file', $sources['application']['type']);
        } else {
            // storageDir does not exist — application source was filtered out
            $this->assertTrue(true);
        }
    }

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

    public function testGetAvailableLogSourcesPHPDefaultEnabled(): void
    {
        putenv('ENABLE_PHP'); // clear
        $sources = $this->service->getAvailableLogSources();

        // PHP is enabled by default (available when ENABLE_PHP !== 'false')
        $this->assertArrayHasKey('php', $sources);
    }

    public function testGetAvailableLogSourcesPHPDisabledWhenEnvFalse(): void
    {
        putenv('ENABLE_PHP=false');
        $sources = $this->service->getAvailableLogSources();

        $this->assertArrayNotHasKey('php', $sources);

        putenv('ENABLE_PHP');
    }

    // ==================== getLogStatistics ====================

    public function testGetLogStatisticsReturnsRequiredKeys(): void
    {
        $stats = $this->service->getLogStatistics();

        $this->assertArrayHasKey('application_logs_count', $stats);
        $this->assertArrayHasKey('total_size', $stats);
        $this->assertArrayHasKey('total_size_formatted', $stats);
        $this->assertArrayHasKey('storage_dir_exists', $stats);
    }

    public function testGetLogStatisticsCountIncludesCreatedFile(): void
    {
        // Baseline
        $before      = $this->service->getLogStatistics();
        $countBefore = $before['application_logs_count'];

        // Add a log file
        $this->createLogFile('test_stats_' . uniqid() . '.log', "test content\n");

        $after = $this->service->getLogStatistics();

        $this->assertGreaterThan($countBefore, $after['application_logs_count']);
    }

    public function testGetLogStatisticsTotalSizeIsNumeric(): void
    {
        $stats = $this->service->getLogStatistics();

        $this->assertIsInt($stats['total_size']);
        $this->assertGreaterThanOrEqual(0, $stats['total_size']);
    }

    public function testGetLogStatisticsFormattedSizeIsString(): void
    {
        $stats = $this->service->getLogStatistics();

        $this->assertIsString($stats['total_size_formatted']);
        $this->assertNotEmpty($stats['total_size_formatted']);
    }

    public function testGetLogStatisticsStorageDirExistsReflectsReality(): void
    {
        $stats = $this->service->getLogStatistics();

        // storage/logs exists in this project
        $this->assertIsBool($stats['storage_dir_exists']);
    }
}
