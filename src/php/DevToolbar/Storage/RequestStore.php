<?php

declare(strict_types=1);

namespace DevToolbar\Storage;

/**
 * Request Store
 *
 * Stores and retrieves request history for the Developer Toolbar
 */
class RequestStore
{
    private const MAX_REQUESTS = 20;
    private const SESSION_KEY = 'dev_toolbar_requests';

    /**
     * Store request data
     *
     * @param string $requestId Unique request identifier
     * @param array<string, mixed> $data Request data from collectors
     * @return void
     */
    public static function store(string $requestId, array $data): void
    {
        self::ensureSessionStarted();

        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        $_SESSION[self::SESSION_KEY][$requestId] = [
            'id' => $requestId,
            'method' => $data['request']['method'] ?? 'GET',
            'uri' => $data['request']['uri'] ?? '/',
            'status' => $data['request']['status_code'] ?? 200,
            'time' => $data['request']['execution_time'] ?? 0,
            'memory' => ($data['request']['memory_peak'] ?? 0) * 1024 * 1024, // Convert MB to bytes
            'query_count' => count($data['queries']['queries'] ?? []),
            'http_count' => $data['http']['count'] ?? 0,
            'cache_count' => $data['cache']['count'] ?? 0,
            'timestamp' => time(),
            'data' => $data, // Full collector data for detailed view
        ];

        // Keep only last N requests
        if (count($_SESSION[self::SESSION_KEY]) > self::MAX_REQUESTS) {
            // Remove oldest request
            $keys = array_keys($_SESSION[self::SESSION_KEY]);
            $oldestKey = $keys[0];
            unset($_SESSION[self::SESSION_KEY][$oldestKey]);
        }
    }

    /**
     * Get all stored requests
     *
     * @return array<string, array<string, mixed>> All stored requests (newest first)
     */
    public static function getAll(): array
    {
        self::ensureSessionStarted();

        $requests = $_SESSION[self::SESSION_KEY] ?? [];

        // Sort by timestamp descending (newest first)
        uasort($requests, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $requests;
    }

    /**
     * Get a specific request by ID
     *
     * @param string $requestId Request identifier
     * @return array<string, mixed>|null Request data or null if not found
     */
    public static function get(string $requestId): ?array
    {
        self::ensureSessionStarted();

        return $_SESSION[self::SESSION_KEY][$requestId] ?? null;
    }

    /**
     * Clear all stored requests
     *
     * @return void
     */
    public static function clear(): void
    {
        self::ensureSessionStarted();

        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Get request history statistics
     *
     * @return array<string, mixed> History statistics
     */
    public static function getStatistics(): array
    {
        $requests = self::getAll();

        if (empty($requests)) {
            return [
                'total_requests' => 0,
                'avg_time' => 0,
                'avg_memory' => 0,
                'avg_queries' => 0,
            ];
        }

        $totalTime = array_sum(array_column($requests, 'time'));
        $totalMemory = array_sum(array_column($requests, 'memory'));
        $totalQueries = array_sum(array_column($requests, 'query_count'));
        $count = count($requests);

        return [
            'total_requests' => $count,
            'avg_time' => round($totalTime / $count, 2),
            'avg_memory' => round($totalMemory / $count / 1024 / 1024, 2), // MB
            'avg_queries' => round($totalQueries / $count, 1),
            'slowest_time' => max(array_column($requests, 'time')),
            'fastest_time' => min(array_column($requests, 'time')),
        ];
    }

    /**
     * Get filtered requests
     *
     * @param array<string, mixed> $filters Filter criteria
     * @return array<string, array<string, mixed>> Filtered requests
     */
    public static function filter(array $filters): array
    {
        $requests = self::getAll();

        // Filter by method
        if (isset($filters['method'])) {
            $requests = array_filter(
                $requests,
                fn($r) => strtoupper($r['method']) === strtoupper($filters['method'])
            );
        }

        // Filter by status code range
        if (isset($filters['status'])) {
            $statusPrefix = substr($filters['status'], 0, 1); // e.g., '2' for 2xx
            $requests = array_filter(
                $requests,
                fn($r) => str_starts_with((string)$r['status'], $statusPrefix)
            );
        }

        // Filter by URI pattern
        if (isset($filters['uri']) && $filters['uri'] !== '') {
            $requests = array_filter(
                $requests,
                fn($r) => str_contains($r['uri'], $filters['uri'])
            );
        }

        // Filter by minimum time
        if (isset($filters['min_time'])) {
            $requests = array_filter(
                $requests,
                fn($r) => $r['time'] >= $filters['min_time']
            );
        }

        return $requests;
    }

    /**
     * Get time-based performance trend data
     *
     * @param int $limit Number of recent requests to analyze
     * @return array<string, mixed> Trend data for charts
     */
    public static function getTrends(int $limit = 20): array
    {
        $requests = array_slice(self::getAll(), 0, $limit, true);
        $requests = array_reverse($requests, true); // Oldest first for chart

        $labels = [];
        $times = [];
        $memories = [];
        $queries = [];

        foreach ($requests as $request) {
            $labels[] = '#' . substr($request['id'], -4);
            $times[] = round($request['time'], 0);
            $memories[] = round($request['memory'] / 1024 / 1024, 1); // MB
            $queries[] = $request['query_count'];
        }

        return [
            'labels' => $labels,
            'time' => $times,
            'memory' => $memories,
            'queries' => $queries,
        ];
    }

    /**
     * Generate a unique request ID
     *
     * @return string Unique request ID
     */
    public static function generateId(): string
    {
        return 'req_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Get formatted time ago string
     *
     * @param int $timestamp Unix timestamp
     * @return string Time ago string (e.g., "5s ago", "2m ago")
     */
    public static function timeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return $diff . 's ago';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . 'm ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . 'h ago';
        } else {
            return floor($diff / 86400) . 'd ago';
        }
    }

    /**
     * Get status code color/icon
     *
     * @param int $statusCode HTTP status code
     * @return array<string, string> Color and icon
     */
    public static function getStatusDisplay(int $statusCode): array
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            return ['color' => 'green', 'icon' => '🟢'];
        } elseif ($statusCode >= 300 && $statusCode < 400) {
            return ['color' => 'blue', 'icon' => '🔵'];
        } elseif ($statusCode >= 400 && $statusCode < 500) {
            return ['color' => 'yellow', 'icon' => '🟡'];
        } else {
            return ['color' => 'red', 'icon' => '🔴'];
        }
    }

    /**
     * Ensure session is started
     *
     * @return void
     */
    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }
}
