<?php

declare(strict_types=1);

namespace Tests\DevToolbar\Analyzers;

use DevToolbar\Analyzers\PerformanceAnalyzer;
use PHPUnit\Framework\TestCase;

/**
 * Test PerformanceAnalyzer alerts and analysis
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods") Test classes need one public method per test case
 * @phpstan-ignore-next-line PHPMD annotation syntax not recognized by PHPStan
 */
class PerformanceAnalyzerTest extends TestCase
{
    public function testDetectsSlowRequest(): void
    {
        $data = [
            'request' => ['execution_time' => 1500, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertEquals('slow_request', $alerts[0]['type']);
        $this->assertEquals('critical', $alerts[0]['level']);
    }

    public function testDetectsHighMemoryUsage(): void
    {
        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 60], // 60 MB
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertEquals('high_memory', $alerts[0]['type']);
        $this->assertEquals('warning', $alerts[0]['level']);
    }

    public function testDetectsExcessiveQueries(): void
    {
        $queries = array_fill(0, 60, ['sql' => 'SELECT 1', 'time' => 1]);

        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => $queries],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertEquals('excessive_queries', $alerts[0]['type']);
        $this->assertEquals('warning', $alerts[0]['level']);
    }

    public function testDetectsSlowQueries(): void
    {
        $queries = [
            ['sql' => 'SELECT 1', 'time' => 300],
            ['sql' => 'SELECT 2', 'time' => 300],
        ];

        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => $queries],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertEquals('slow_queries', $alerts[0]['type']);
    }

    public function testDetectsExcessiveHttpRequests(): void
    {
        $requests = array_fill(0, 15, ['method' => 'GET', 'url' => 'https://api.example.com', 'time' => 10]);

        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => $requests],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertEquals('excessive_http', $alerts[0]['type']);
    }

    public function testDetectsSlowHttpRequests(): void
    {
        $requests = [
            ['method' => 'GET', 'url' => 'https://slow-api.example.com', 'time' => 600],
            ['method' => 'GET', 'url' => 'https://slow-api2.example.com', 'time' => 600],
        ];

        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => $requests],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertEquals('slow_http', $alerts[0]['type']);
    }

    public function testDetectsLowCacheHitRate(): void
    {
        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 40, 'count' => 10], // Must have operations
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertCount(1, $alerts);
        $this->assertEquals('low_cache_hit_rate', $alerts[0]['type']);
        $this->assertEquals('warning', $alerts[0]['level']);
    }

    public function testNoAlertWhenNoCacheOperations(): void
    {
        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 0, 'count' => 0], // No operations
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        // Should have no cache-related alerts
        $cacheAlerts = array_filter($alerts, fn($a) => $a['type'] === 'low_cache_hit_rate');
        $this->assertEmpty($cacheAlerts);
    }

    public function testSortsByCriticalFirst(): void
    {
        $data = [
            'request' => ['execution_time' => 1500, 'memory_peak' => 60],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        // Should have 2 alerts (slow request + high memory)
        $this->assertCount(2, $alerts);

        // First should be critical (slow request)
        $this->assertEquals('critical', $alerts[0]['level']);

        // Second should be warning (high memory)
        $this->assertEquals('warning', $alerts[1]['level']);
    }

    public function testNoAlertsForGoodPerformance(): void
    {
        $data = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => [['sql' => 'SELECT 1', 'time' => 5]]],
            'http'    => ['requests' => [['method' => 'GET', 'url' => 'https://api.example.com', 'time' => 50]]],
            'cache'   => ['hit_rate' => 90],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertEmpty($alerts);
    }

    public function testGetSummary(): void
    {
        $data = [
            'request' => ['execution_time' => 1500, 'memory_peak' => 60],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 40, 'count' => 10],
        ];

        $summary = PerformanceAnalyzer::getSummary($data);

        $this->assertEquals(3, $summary['total_alerts']);
        $this->assertEquals(1, $summary['critical_count']);
        $this->assertEquals(2, $summary['warning_count']);
        $this->assertEquals(0, $summary['info_count']);
        $this->assertTrue($summary['has_issues']);
    }

    public function testHasIssues(): void
    {
        $dataWithIssues = [
            'request' => ['execution_time' => 1500, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $dataWithoutIssues = [
            'request' => ['execution_time' => 100, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $this->assertTrue(PerformanceAnalyzer::hasIssues($dataWithIssues));
        $this->assertFalse(PerformanceAnalyzer::hasIssues($dataWithoutIssues));
    }

    public function testAlertsContainRequiredFields(): void
    {
        $data = [
            'request' => ['execution_time' => 1500, 'memory_peak' => 9.5],
            'queries' => ['queries' => []],
            'http'    => ['requests' => []],
            'cache'   => ['hit_rate' => 100],
        ];

        $alerts = PerformanceAnalyzer::analyze($data);

        $this->assertNotEmpty($alerts);

        foreach ($alerts as $alert) {
            $this->assertArrayHasKey('level', $alert);
            $this->assertArrayHasKey('type', $alert);
            $this->assertArrayHasKey('icon', $alert);
            $this->assertArrayHasKey('message', $alert);
            $this->assertArrayHasKey('threshold', $alert);
            $this->assertArrayHasKey('actual', $alert);
            $this->assertArrayHasKey('action', $alert);
        }
    }
}
