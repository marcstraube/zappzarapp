<?php

declare(strict_types=1);

namespace DevToolbar\DataCollectors;
use Redis;

/**
 * Cache Collector
 *
 * Monitors Redis/Cache operations (get, set, delete, hits, misses)
 */
class CacheCollector implements CollectorInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $operations = [];
    private int $hits         = 0;
    private int $misses       = 0;
    private bool $collecting  = false;

    /**
     * Start collecting cache operations
     */
    public function start(): void
    {
        $this->collecting = true;
        $this->operations = [];
        $this->hits       = 0;
        $this->misses     = 0;
    }

    /**
     * Stop collecting cache operations
     */
    public function stop(): void
    {
        $this->collecting = false;
    }

    /**
     * Get collector name
     */
    public function getName(): string
    {
        return 'cache';
    }

    /**
     * Get collected cache operation data
     */
    public function getData(): array
    {
        $total   = $this->hits + $this->misses;
        $hitRate = $total > 0 ? round(($this->hits / $total) * 100, 1) : 0;

        return [
            'operations' => $this->operations,
            'hits'       => $this->hits,
            'misses'     => $this->misses,
            'hit_rate'   => $hitRate,
            'total_time' => round(array_sum(array_column($this->operations, 'time')), 2),
            'count'      => count($this->operations),
        ];
    }

    /**
     * Track a cache operation
     *
     * @param string $type Operation type (get, set, delete, etc.)
     * @param string $key Cache key
     * @param float $time Execution time in milliseconds
     * @param mixed $value Value (for get/set operations)
     * @param bool $hit Whether operation was a cache hit (for get operations)
     * @param int|null $ttl TTL in seconds (for set operations)
     * @return void
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function trackOperation(
        string $type,
        string $key,
        float $time,
        mixed $value = null,
        bool $hit = false,
        ?int $ttl = null
    ): void {
        if (!$this->collecting) {
            return;
        }

        $operation = [
            'type'      => $type,
            'key'       => $key,
            'time'      => round($time, 2),
            'backtrace' => $this->getRelevantBacktrace(),
        ];

        if ($type === 'get') {
            $operation['hit'] = $hit;
            if ($hit) {
                $this->hits++;
                $operation['value'] = $this->filterValue($value);
            } else {
                $this->misses++;
            }
        } elseif ($type === 'set') {
            $operation['ttl']  = $ttl;
            $operation['size'] = $this->calculateSize($value);
        } elseif ($type === 'delete') {
            $operation['success'] = (bool)$value;
        }

        $this->operations[] = $operation;
    }

    /**
     * Wrapper for Redis GET operation
     *
     * @param Redis|object $redis Redis instance
     * @param string $key Cache key
     * @return mixed Cached value or false
     * @phpstan-param \Redis $redis
     */
    public function wrapRedisGet($redis, string $key): mixed
    {
        if (!$this->collecting) {
            return $redis->get($key);
        }

        $start  = hrtime(true);
        $result = $redis->get($key);
        $time   = (hrtime(true) - $start) / 1_000_000; // Convert to milliseconds

        $isHit = $result !== false;
        $ttl   = $isHit ? $redis->ttl($key) : null;

        $this->trackOperation('get', $key, $time, $result, $isHit);

        if ($isHit && $ttl !== null) {
            // Store TTL info for display
            $lastOp        = &$this->operations[count($this->operations) - 1];
            $lastOp['ttl'] = $ttl;
        }

        return $result;
    }

    /**
     * Wrapper for Redis SET operation
     *
     * @param Redis|object $redis Redis instance
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl TTL in seconds
     * @return bool Success status
     * @phpstan-param \Redis $redis
     */
    public function wrapRedisSet($redis, string $key, mixed $value, ?int $ttl = null): bool
    {
        if (!$this->collecting) {
            if ($ttl !== null) {
                return $redis->setex($key, $ttl, $value);
            }
            return $redis->set($key, $value);
        }

        $start = hrtime(true);
        if ($ttl !== null) {
            $result = $redis->setex($key, $ttl, $value);
        } else {
            $result = $redis->set($key, $value);
        }
        $time = (hrtime(true) - $start) / 1_000_000; // Convert to milliseconds

        $this->trackOperation('set', $key, $time, $value, false, $ttl);

        return $result;
    }

    /**
     * Wrapper for Redis DELETE operation
     *
     * @param Redis|object $redis Redis instance
     * @param string|array<string> $key Cache key(s)
     * @return int Number of keys deleted
     * @phpstan-param \Redis $redis
     */
    public function wrapRedisDelete($redis, string|array $key): int
    {
        if (!$this->collecting) {
            return $redis->del($key);
        }

        $start  = hrtime(true);
        $result = $redis->del($key);
        $time   = (hrtime(true) - $start) / 1_000_000; // Convert to milliseconds

        $keyString = is_array($key) ? implode(', ', $key) : $key;
        $this->trackOperation('delete', $keyString, $time, $result);

        return $result;
    }

    /**
     * Get relevant backtrace (filter out internal calls)
     *
     * @return array<int, array<string, mixed>> Filtered backtrace
     */
    private function getRelevantBacktrace(): array
    {
        $trace    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $relevant = [];

        foreach ($trace as $frame) {
            // Skip internal DevToolbar calls
            if (isset($frame['class']) && str_starts_with($frame['class'], 'DevToolbar\\')) {
                continue;
            }

            // Skip PHP internal functions
            if (!isset($frame['file'])) {
                continue;
            }

            $relevant[] = [
                'file'     => str_replace(getcwd() . '/', '', $frame['file'] ?? ''),
                'line'     => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? '',
                'class'    => $frame['class'] ?? '',
            ];

            // Only keep first relevant frame
            if (count($relevant) >= 1) {
                break;
            }
        }

        return $relevant;
    }

    /**
     * Filter sensitive data from cached values
     *
     * @param mixed $value Value to filter
     * @return mixed Filtered value
     */
    private function filterValue(mixed $value): mixed
    {
        if ($value === false || $value === null) {
            return $value;
        }

        // Try to unserialize if serialized
        if (is_string($value) && $this->isSerializedString($value)) {
            $value = $this->safeUnserialize($value);
        }

        if (is_string($value)) {
            return $this->filterString($value);
        }

        if (is_array($value)) {
            return $this->filterArray($value);
        }

        return $value;
    }

    /**
     * Check if string appears to be serialized
     */
    private function isSerializedString(string $value): bool
    {
        return str_starts_with($value, 'a:')
            || str_starts_with($value, 'O:')
            || str_starts_with($value, 's:');
    }

    /**
     * Safely unserialize a value without error suppression
     */
    private function safeUnserialize(string $value): mixed
    {
        try {
            $unserialized = unserialize($value);
            return $unserialized !== false ? $unserialized : $value;
        } catch (\Throwable $e) {
            return $value;
        }
    }

    /**
     * Filter sensitive data from string values
     */
    private function filterString(string $value): string
    {
        // Truncate long strings
        if (strlen($value) > 1000) {
            return substr($value, 0, 1000) . '... (truncated)';
        }

        // Filter sensitive patterns
        $value = preg_replace('/("password"\s*:\s*)"[^"]*"/', '$1"[FILTERED]"', $value);
        $value = preg_replace('/("token"\s*:\s*)"[^"]*"/', '$1"[FILTERED]"', $value);

        return $value;
    }

    /**
     * Filter sensitive data from array values
     */
    private function filterArray(array $value): array
    {
        foreach (array_keys($value) as $key) {
            if (in_array(strtolower((string)$key), ['password', 'token', 'secret', 'api_key'])) {
                $value[$key] = '[FILTERED]';
            }
        }

        // Truncate large arrays
        if (count($value) > 50) {
            $value                = array_slice($value, 0, 50, true);
            $value['__truncated'] = '... (' . (count($value) - 50) . ' more items)';
        }

        return $value;
    }

    /**
     * Calculate size of cached value
     *
     * @param mixed $value Value to measure
     * @return string Human-readable size
     */
    private function calculateSize(mixed $value): string
    {
        $serialized = serialize($value);
        $bytes      = strlen($serialized);

        if ($bytes < 1024) {
            return $bytes . 'B';
        } elseif ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . 'KB';
        } else {
            return round($bytes / (1024 * 1024), 1) . 'MB';
        }
    }

    /**
     * Check if collecting is active
     *
     * @return bool True if collecting
     */
    public function isCollecting(): bool
    {
        return $this->collecting;
    }
}
