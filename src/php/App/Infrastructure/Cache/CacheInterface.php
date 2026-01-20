<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

/**
 * Cache Interface for key-value storage with TTL support
 *
 * Provides a simple, type-safe interface for caching operations.
 * Implementations can use Redis, Memcached, APCu, or filesystem.
 *
 * When to use:
 * - Expensive database queries (cache results)
 * - API responses (rate limiting, response caching)
 * - Computed values (calculations, aggregations)
 * - Session data (via SessionInterface which builds on this)
 *
 * When NOT to use:
 * - Real-time data that changes frequently
 * - Security-critical data (use encryption + database)
 * - Large binary files (use object storage)
 *
 * Usage:
 * <code>
 * // Get from DI container
 * $cache = $container->get(CacheInterface::class);
 *
 * // Simple get/set
 * $cache->set('user:123', json_encode($userData), 3600);
 * $user = $cache->get('user:123');
 *
 * // With existence check
 * if ($cache->has('session:abc')) {
 *     $session = $cache->get('session:abc');
 * }
 *
 * // Pattern deletion (cache invalidation)
 * $cache->deletePattern('user:123:*');
 * </code>
 *
 * @package Infrastructure\Cache
 */
interface CacheInterface
{
    /**
     * Retrieve a value from cache
     *
     * @param string $key Cache key (will be prefixed automatically)
     * @return string|null Cached value or null if not found/expired
     */
    public function get(string $key): ?string;

    /**
     * Store a value in cache
     *
     * @param string $key Cache key (will be prefixed automatically)
     * @param string $value Value to cache (serialize objects to JSON)
     * @param int $ttl Time-to-live in seconds (default: 3600 = 1 hour)
     * @return bool True if stored successfully
     */
    public function set(string $key, string $value, int $ttl = 3600): bool;

    /**
     * Check if a key exists in cache
     *
     * @param string $key Cache key
     * @return bool True if key exists and not expired
     */
    public function has(string $key): bool;

    /**
     * Delete a key from cache
     *
     * @param string $key Cache key
     * @return bool True if key was deleted (or didn't exist)
     */
    public function delete(string $key): bool;

    /**
     * Delete multiple keys matching a pattern
     *
     * Warning: KEYS command can be slow on large datasets.
     * Consider using Redis SCAN in production for large keyspaces.
     *
     * @param string $pattern Pattern with wildcards (e.g., 'user:*', 'session:123:*')
     * @return int Number of keys deleted
     */
    public function deletePattern(string $pattern): int;

    /**
     * Get remaining TTL for a key
     *
     * @param string $key Cache key
     * @return int|null TTL in seconds, null if key doesn't exist, -1 if no expiry
     */
    public function ttl(string $key): ?int;

    /**
     * Check if cache backend is available
     *
     * Use this for health checks or graceful degradation.
     *
     * @return bool True if cache is connected and responsive
     */
    public function isAvailable(): bool;
}
