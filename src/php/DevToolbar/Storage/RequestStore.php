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
    private const SESSION_KEY = 'dev_toolbar_requests';
    private const FULL_DATA_KEY = 'dev_toolbar_full_data';
    private const MAX_FULL_DATA = 5; // Only keep full data for last 5 requests

    /**
     * Get maximum number of requests to store
     *
     * @return int Maximum requests (1-100, default: 20)
     */
    public static function getMaxRequests(): int
    {
        $envValue = getenv('DEV_TOOLBAR_MAX_REQUESTS');
        if ($envValue !== false && is_numeric($envValue)) {
            return max(1, min(50, (int)$envValue)); // Clamp 1-50 (reduced from 100)
        }
        return 10; // Default reduced from 20 to prevent memory issues
    }

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
        if (!isset($_SESSION[self::FULL_DATA_KEY])) {
            $_SESSION[self::FULL_DATA_KEY] = [];
        }

        $beforeCount = count($_SESSION[self::SESSION_KEY]);
        error_log(sprintf(
            'DevToolbar: Storing request %s (current count: %d)',
            substr($requestId, 0, 20),
            $beforeCount
        ));

        // Store lightweight metadata for all requests (for list/stats)
        $_SESSION[self::SESSION_KEY][$requestId] = [
            'id' => $requestId,
            'method' => $data['request']['method'] ?? 'GET',
            'uri' => $data['request']['uri'] ?? '/',
            'status' => $data['request']['status_code'] ?? 200,
            'time' => $data['request']['execution_time'] ?? 0,
            'memory' => ($data['request']['memory_peak'] ?? 0) * 1024 * 1024,
            'query_count' => count($data['queries']['queries'] ?? []),
            'http_count' => $data['http']['count'] ?? 0,
            'cache_count' => $data['cache']['count'] ?? 0,
            'timestamp' => time(),
        ];

        // Store full data only for recent requests (for AJAX switching)
        $_SESSION[self::FULL_DATA_KEY][$requestId] = [
            'id' => $requestId,
            'timestamp' => time(),
            'data' => $data,
        ];

        // Keep only last N full data entries
        if (count($_SESSION[self::FULL_DATA_KEY]) > self::MAX_FULL_DATA) {
            $fullData = $_SESSION[self::FULL_DATA_KEY];
            uasort($fullData, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']); // Newest first
            $_SESSION[self::FULL_DATA_KEY] = array_slice($fullData, 0, self::MAX_FULL_DATA, true);

            error_log(sprintf(
                'DevToolbar: Trimmed full data to %d entries',
                count($_SESSION[self::FULL_DATA_KEY])
            ));
        }

        // Remove oldest metadata if we exceed the limit
        $maxRequests = self::getMaxRequests();
        $currentCount = count($_SESSION[self::SESSION_KEY]);

        if ($currentCount > $maxRequests) {
            // Sort by timestamp to find oldest
            $requests = $_SESSION[self::SESSION_KEY];
            uasort($requests, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

            // Remove oldest requests until we're at the limit
            $toRemove = $currentCount - $maxRequests;
            $removed = 0;

            foreach ($requests as $id => $request) {
                if ($removed >= $toRemove) {
                    break;
                }
                unset($_SESSION[self::SESSION_KEY][$id]);
                // Also remove from full data if exists
                unset($_SESSION[self::FULL_DATA_KEY][$id]);
                $removed++;
            }

            error_log(sprintf(
                'DevToolbar: Removed %d old request(s), now storing %d/%d',
                $removed,
                count($_SESSION[self::SESSION_KEY]),
                $maxRequests
            ));
        }

        error_log(sprintf(
            'DevToolbar: Stored successfully (metadata: %d, full data: %d)',
            count($_SESSION[self::SESSION_KEY]),
            count($_SESSION[self::FULL_DATA_KEY])
        ));
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

        $metadata = $_SESSION[self::SESSION_KEY][$requestId] ?? null;
        if (!$metadata) {
            return null;
        }

        // Try to get full data if available
        $fullDataEntry = $_SESSION[self::FULL_DATA_KEY][$requestId] ?? null;
        if ($fullDataEntry && isset($fullDataEntry['data'])) {
            // Merge metadata with full data
            return array_merge($metadata, ['data' => $fullDataEntry['data']]);
        }

        // Full data not available (too old), return null to indicate AJAX won't work
        error_log(sprintf(
            'DevToolbar: Full data not available for request %s (too old)',
            substr($requestId, 0, 20)
        ));
        return null;
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
        unset($_SESSION[self::FULL_DATA_KEY]);
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
     * Get all requests for migration to localStorage
     *
     * Returns all stored requests with their full data for one-time migration.
     * This method is used when migrating from session storage to localStorage.
     *
     * @return array<int, array<string, mixed>> Array of requests with id, metadata, and data
     */
    public static function getAllForMigration(): array
    {
        self::ensureSessionStarted();

        $metadata = $_SESSION[self::SESSION_KEY] ?? [];
        $fullData = $_SESSION[self::FULL_DATA_KEY] ?? [];

        if (empty($metadata)) {
            return [];
        }

        $migrationData = [];

        // Sort by timestamp (newest first)
        uasort($metadata, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        foreach ($metadata as $requestId => $meta) {
            // Check if full data is available
            $fullDataEntry = $fullData[$requestId] ?? null;

            if (!$fullDataEntry || !isset($fullDataEntry['data'])) {
                // Skip requests without full data (can't migrate)
                continue;
            }

            $migrationData[] = [
                'id' => $requestId,
                'metadata' => $meta,
                'data' => $fullDataEntry['data'], // Full collector data
            ];

            // Limit migration to 20 most recent requests (matching MAX_FULL_DATA for localStorage)
            if (count($migrationData) >= 20) {
                break;
            }
        }

        return $migrationData;
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
