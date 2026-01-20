<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Redis;
use RedisException;

/**
 * Redis Cache Implementation
 *
 * Thread-safe, lazy-connected Redis client with automatic reconnection.
 * Supports both TLS (rediss://) and plain (redis://) connections.
 *
 * Configuration via environment variables:
 * - REDIS_URL: Connection URL (default: rediss://redis:6379)
 *
 * Constructor parameters:
 * - $redisUrl: Override REDIS_URL environment variable
 * - $prefix: Key prefix for namespace isolation (default: 'app:')
 * - $timeout: Connection timeout in seconds (default: 2)
 *
 * Error Handling:
 * - Connection failures return null/false (no exceptions thrown)
 * - Use isAvailable() to check connection status
 * - Automatic reconnection on next operation after failure
 *
 * Usage:
 * <code>
 * $cache = new RedisCache();
 *
 * // Store user data with 1-hour TTL
 * $cache->set('user:123:profile', json_encode($profile), 3600);
 *
 * // Retrieve
 * $json = $cache->get('user:123:profile');
 * if ($json !== null) {
 *     $profile = json_decode($json, true);
 * }
 *
 * // Cache invalidation
 * $cache->deletePattern('user:123:*');
 * </code>
 *
 * @package Infrastructure\Cache
 */
final class RedisCache implements CacheInterface
{
    private ?Redis $redis = null;

    private readonly string $redisUrl;

    public function __construct(
        ?string $redisUrl = null,
        private readonly string $prefix = 'app:',
        private readonly int $timeout = 2
    ) {
        if ($redisUrl === null) {
            $envValue = $_ENV['REDIS_URL'] ?? getenv('REDIS_URL');
            $redisUrl = ($envValue !== false && $envValue !== '') ? $envValue : 'rediss://redis:6379';
        }

        $this->redisUrl = $redisUrl;
    }

    public function get(string $key): ?string
    {
        $redis = $this->getConnection();
        if (!$redis instanceof Redis) {
            return null;
        }

        try {
            $value = $redis->get($this->prefixKey($key));
            return $value === false ? null : $value;
        } catch (RedisException) {
            $this->disconnect();
            return null;
        }
    }

    public function set(string $key, string $value, int $ttl = 3600): bool
    {
        $redis = $this->getConnection();
        if (!$redis instanceof Redis) {
            return false;
        }

        try {
            return $redis->setex($this->prefixKey($key), $ttl, $value);
        } catch (RedisException) {
            $this->disconnect();
            return false;
        }
    }

    public function has(string $key): bool
    {
        $redis = $this->getConnection();
        if (!$redis instanceof Redis) {
            return false;
        }

        try {
            return $redis->exists($this->prefixKey($key)) > 0;
        } catch (RedisException) {
            $this->disconnect();
            return false;
        }
    }

    public function delete(string $key): bool
    {
        $redis = $this->getConnection();
        if (!$redis instanceof Redis) {
            return false;
        }

        try {
            $redis->del($this->prefixKey($key));
            return true;
        } catch (RedisException) {
            $this->disconnect();
            return false;
        }
    }

    public function deletePattern(string $pattern): int
    {
        $redis = $this->getConnection();
        if (!$redis instanceof Redis) {
            return 0;
        }

        try {
            $keys = $redis->keys($this->prefixKey($pattern));
            if ($keys === false || count($keys) === 0) {
                return 0;
            }

            $deleted = $redis->del($keys);
            return $deleted === false ? 0 : $deleted;
        } catch (RedisException) {
            $this->disconnect();
            return 0;
        }
    }

    public function ttl(string $key): ?int
    {
        $redis = $this->getConnection();
        if (!$redis instanceof Redis) {
            return null;
        }

        try {
            $ttl = $redis->ttl($this->prefixKey($key));
            // Redis returns -2 if key doesn't exist, false on error
            if ($ttl === false || $ttl === -2) {
                return null;
            }

            return (int) $ttl;
        } catch (RedisException) {
            $this->disconnect();
            return null;
        }
    }

    public function isAvailable(): bool
    {
        $redis = $this->getConnection();
        if (!$redis instanceof Redis) {
            return false;
        }

        try {
            return $redis->ping() !== false;
        } catch (RedisException) {
            $this->disconnect();
            return false;
        }
    }

    /**
     * Get or create Redis connection (lazy initialization)
     */
    private function getConnection(): ?Redis
    {
        if ($this->redis instanceof Redis) {
            return $this->redis;
        }

        try {
            $parsed = $this->parseRedisUrl($this->redisUrl);
            $redis  = new Redis();

            $useTls = str_starts_with($this->redisUrl, 'rediss://');

            if ($useTls) {
                // TLS connection with self-signed certificate support (dev)
                $connected = $redis->connect(
                    $parsed['host'],
                    $parsed['port'],
                    $this->timeout,
                    '',
                    0,
                    0,
                    [
                        'stream' => [
                            'verify_peer'       => false,
                            'verify_peer_name'  => false,
                            'allow_self_signed' => true,
                        ],
                    ]
                );
            } else {
                // Plain connection
                $connected = $redis->connect(
                    $parsed['host'],
                    $parsed['port'],
                    $this->timeout
                );
            }

            if (!$connected) {
                return null;
            }

            // Authenticate if password is set
            if ($parsed['password'] !== null) {
                if ($parsed['user'] !== null) {
                    // Redis 6+ ACL: AUTH username password
                    $redis->auth([$parsed['user'], $parsed['password']]);
                } else {
                    // Legacy: AUTH password
                    $redis->auth($parsed['password']);
                }
            }

            // Select database if specified
            if ($parsed['database'] !== 0) {
                $redis->select($parsed['database']);
            }

            $this->redis = $redis;
            return $this->redis;
        } catch (RedisException) {
            return null;
        }
    }

    /**
     * Close connection (for reconnection on next use)
     */
    private function disconnect(): void
    {
        if ($this->redis instanceof Redis) {
            try {
                $this->redis->close();
            } catch (RedisException) {
                // Ignore close errors
            }

            $this->redis = null;
        }
    }

    /**
     * Add prefix to key
     */
    private function prefixKey(string $key): string
    {
        return $this->prefix . $key;
    }

    /**
     * Parse Redis URL into components
     *
     * Supports formats:
     * - redis://host:port
     * - redis://host:port/database
     * - redis://user:password@host:port/database
     * - rediss://... (TLS)
     *
     * @return array{host: string, port: int, user: string|null, password: string|null, database: int}
     */
    private function parseRedisUrl(string $url): array
    {
        // Replace rediss:// with redis:// for parse_url
        $normalizedUrl = preg_replace('/^rediss:/', 'redis:', $url);
        $parts         = parse_url($normalizedUrl ?? $url);

        $host     = $parts['host'] ?? 'localhost';
        $port     = $parts['port'] ?? 6379;
        $user     = isset($parts['user']) ? urldecode($parts['user']) : null;
        $password = isset($parts['pass']) ? urldecode($parts['pass']) : null;
        $database = 0;

        if (isset($parts['path']) && $parts['path'] !== '/') {
            $database = (int) ltrim($parts['path'], '/');
        }

        return [
            'host'     => $host,
            'port'     => $port,
            'user'     => $user,
            'password' => $password,
            'database' => $database,
        ];
    }

}
