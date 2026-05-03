<?php

declare(strict_types=1);

namespace DevToolbar\Analyzers;

/**
 * Performance Analyzer
 *
 * Analyzes application performance and generates alerts for issues.
 * Thresholds can be customized via the optional $thresholds parameter.
 */
class PerformanceAnalyzer
{
    /** @var array<string, int> Default performance thresholds */
    private const array DEFAULT_THRESHOLDS = [
        'time_ms'       => 1000,   // 1 second
        'memory_mb'     => 50,     // 50 MB
        'query_count'   => 50,     // 50 queries
        'query_time_ms' => 500,    // 500ms total query time
        'http_count'    => 10,     // 10 HTTP requests
        'http_time_ms'  => 1000,   // 1 second total HTTP time
    ];

    /** Alert levels */
    private const string LEVEL_CRITICAL = 'critical';
    private const string LEVEL_WARNING  = 'warning';
    private const string LEVEL_INFO     = 'info';

    /**
     * Get default thresholds
     *
     * @return array<string, int>
     */
    public static function getDefaultThresholds(): array
    {
        return self::DEFAULT_THRESHOLDS;
    }

    /**
     * Analyze performance and generate alerts
     *
     * @param array<string, mixed> $collectorData Data from all collectors
     * @param array<string, int>|null $thresholds Custom thresholds (merged with defaults)
     * @return array<int, array<string, mixed>> Performance alerts
     */
    public static function analyze(array $collectorData, ?array $thresholds = null): array
    {
        $t = $thresholds !== null
            ? array_merge(self::DEFAULT_THRESHOLDS, $thresholds)
            : self::DEFAULT_THRESHOLDS;

        $alerts = [];

        $alerts = array_merge($alerts, self::analyzeExecutionTime($collectorData, $t));
        $alerts = array_merge($alerts, self::analyzeMemoryUsage($collectorData, $t));
        $alerts = array_merge($alerts, self::analyzeQueries($collectorData, $t));
        $alerts = array_merge($alerts, self::analyzeHttpRequests($collectorData, $t));
        $alerts = array_merge($alerts, self::analyzeCacheOperations($collectorData));

        // Sort by level (critical first)
        usort($alerts, fn($a, $b) => self::compareLevels($a['level'], $b['level']));

        return $alerts;
    }

    /**
     * Analyze execution time
     *
     * @param array<string, mixed> $data Collector data
     * @param array<string, int> $t Thresholds
     * @return array<int, array<string, mixed>> Alerts
     */
    private static function analyzeExecutionTime(array $data, array $t): array
    {
        $alerts = [];
        $time   = $data['request']['execution_time'] ?? 0;

        if ($time > $t['time_ms']) {
            $alerts[] = [
                'level'     => self::LEVEL_CRITICAL,
                'type'      => 'slow_request',
                'icon'      => '🔴',
                'message'   => sprintf('Slow Request (%.0fms)', $time),
                'threshold' => $t['time_ms'] . 'ms',
                'actual'    => round($time) . 'ms',
                'action'    => 'Review Timeline tab for bottlenecks',
            ];
        } elseif ($time > $t['time_ms'] * 0.7) {
            $alerts[] = [
                'level'     => self::LEVEL_WARNING,
                'type'      => 'slow_request',
                'icon'      => '🟠',
                'message'   => sprintf('Approaching Slow Request (%.0fms)', $time),
                'threshold' => $t['time_ms'] . 'ms',
                'actual'    => round($time) . 'ms',
                'action'    => 'Monitor request performance',
            ];
        }

        return $alerts;
    }

    /**
     * Analyze memory usage
     *
     * @param array<string, mixed> $data Collector data
     * @param array<string, int> $t Thresholds
     * @return array<int, array<string, mixed>> Alerts
     */
    private static function analyzeMemoryUsage(array $data, array $t): array
    {
        $alerts   = [];
        $memoryMb = $data['request']['memory_peak'] ?? 0;

        if ($memoryMb > $t['memory_mb']) {
            $alerts[] = [
                'level'     => self::LEVEL_WARNING,
                'type'      => 'high_memory',
                'icon'      => '🟠',
                'message'   => sprintf('High Memory Usage (%.1fMB)', $memoryMb),
                'threshold' => $t['memory_mb'] . 'MB',
                'actual'    => round($memoryMb, 1) . 'MB',
                'action'    => 'Check for memory leaks or large datasets',
            ];
        } elseif ($memoryMb > $t['memory_mb'] * 0.8) {
            $alerts[] = [
                'level'     => self::LEVEL_INFO,
                'type'      => 'high_memory',
                'icon'      => '🔵',
                'message'   => sprintf('Elevated Memory Usage (%.1fMB)', $memoryMb),
                'threshold' => $t['memory_mb'] . 'MB',
                'actual'    => round($memoryMb, 1) . 'MB',
                'action'    => 'Monitor memory usage',
            ];
        }

        return $alerts;
    }

    /**
     * Analyze database queries
     *
     * @param array<string, mixed> $data Collector data
     * @param array<string, int> $t Thresholds
     * @return array<int, array<string, mixed>> Alerts
     */
    private static function analyzeQueries(array $data, array $t): array
    {
        $alerts     = [];
        $queries    = $data['queries']['queries'] ?? [];
        $queryCount = count($queries);
        $totalTime  = array_sum(array_column($queries, 'time'));

        if ($queryCount > $t['query_count']) {
            $alerts[] = [
                'level'     => self::LEVEL_WARNING,
                'type'      => 'excessive_queries',
                'icon'      => '🟠',
                'message'   => sprintf('Excessive Queries (%d)', $queryCount),
                'threshold' => $t['query_count'] . ' queries',
                'actual'    => $queryCount . ' queries',
                'action'    => 'Review QUERIES tab for N+1 problems',
            ];
        }

        if ($totalTime > $t['query_time_ms']) {
            $alerts[] = [
                'level'     => self::LEVEL_WARNING,
                'type'      => 'slow_queries',
                'icon'      => '🟠',
                'message'   => sprintf('Slow Database Queries (%.0fms total)', $totalTime),
                'threshold' => $t['query_time_ms'] . 'ms',
                'actual'    => round($totalTime) . 'ms',
                'action'    => 'Optimize slow queries or add indexes',
            ];
        }

        return $alerts;
    }

    /**
     * Analyze HTTP requests
     *
     * @param array<string, mixed> $data Collector data
     * @param array<string, int> $t Thresholds
     * @return array<int, array<string, mixed>> Alerts
     */
    private static function analyzeHttpRequests(array $data, array $t): array
    {
        $alerts       = [];
        $requests     = $data['http']['requests'] ?? [];
        $requestCount = count($requests);
        $totalTime    = array_sum(array_column($requests, 'time'));

        if ($requestCount > $t['http_count']) {
            $alerts[] = [
                'level'     => self::LEVEL_WARNING,
                'type'      => 'excessive_http',
                'icon'      => '🟠',
                'message'   => sprintf('Excessive HTTP Requests (%d)', $requestCount),
                'threshold' => $t['http_count'] . ' requests',
                'actual'    => $requestCount . ' requests',
                'action'    => 'Consider batching or caching HTTP requests',
            ];
        }

        if ($totalTime > $t['http_time_ms']) {
            $alerts[] = [
                'level'     => self::LEVEL_WARNING,
                'type'      => 'slow_http',
                'icon'      => '🟠',
                'message'   => sprintf('Slow HTTP Requests (%.0fms total)', $totalTime),
                'threshold' => $t['http_time_ms'] . 'ms',
                'actual'    => round($totalTime) . 'ms',
                'action'    => 'Review HTTP tab for slow external calls',
            ];
        }

        return $alerts;
    }

    /**
     * Analyze cache operations
     *
     * @param array<string, mixed> $data Collector data
     * @return array<int, array<string, mixed>> Alerts
     */
    private static function analyzeCacheOperations(array $data): array
    {
        $alerts     = [];
        $cacheCount = $data['cache']['count'] ?? 0;
        $hitRate    = $data['cache']['hit_rate'] ?? 100;

        if ($cacheCount === 0) {
            return $alerts;
        }

        if ($hitRate < 50) {
            $alerts[] = [
                'level'     => self::LEVEL_WARNING,
                'type'      => 'low_cache_hit_rate',
                'icon'      => '🟠',
                'message'   => sprintf('Low Cache Hit Rate (%.1f%%)', $hitRate),
                'threshold' => '70%',
                'actual'    => round($hitRate, 1) . '%',
                'action'    => 'Review cache strategy and TTL settings',
            ];
        } elseif ($hitRate < 70) {
            $alerts[] = [
                'level'     => self::LEVEL_INFO,
                'type'      => 'low_cache_hit_rate',
                'icon'      => '🔵',
                'message'   => sprintf('Moderate Cache Hit Rate (%.1f%%)', $hitRate),
                'threshold' => '70%',
                'actual'    => round($hitRate, 1) . '%',
                'action'    => 'Consider increasing cache TTL',
            ];
        }

        return $alerts;
    }

    /**
     * Compare alert levels for sorting
     *
     * @param string $levelA Level A
     * @param string $levelB Level B
     * @return int Comparison result
     */
    private static function compareLevels(string $levelA, string $levelB): int
    {
        $order = [
            self::LEVEL_CRITICAL => 0,
            self::LEVEL_WARNING  => 1,
            self::LEVEL_INFO     => 2,
        ];

        return ($order[$levelA] ?? 99) <=> ($order[$levelB] ?? 99);
    }

    /**
     * Get performance summary
     *
     * @param array<string, mixed> $collectorData Data from all collectors
     * @param array<string, int>|null $thresholds Custom thresholds (merged with defaults)
     * @return array<string, mixed> Performance summary
     */
    public static function getSummary(array $collectorData, ?array $thresholds = null): array
    {
        $alerts = self::analyze($collectorData, $thresholds);

        return [
            'total_alerts'   => count($alerts),
            'critical_count' => count(array_filter($alerts, fn($a) => $a['level'] === self::LEVEL_CRITICAL)),
            'warning_count'  => count(array_filter($alerts, fn($a) => $a['level'] === self::LEVEL_WARNING)),
            'info_count'     => count(array_filter($alerts, fn($a) => $a['level'] === self::LEVEL_INFO)),
            'has_issues'     => !empty($alerts),
        ];
    }

    /**
     * Check if there are any performance issues
     *
     * @param array<string, mixed> $collectorData Data from all collectors
     * @param array<string, int>|null $thresholds Custom thresholds (merged with defaults)
     * @return bool True if issues detected
     */
    public static function hasIssues(array $collectorData, ?array $thresholds = null): bool
    {
        $alerts = self::analyze($collectorData, $thresholds);
        return !empty($alerts);
    }
}
