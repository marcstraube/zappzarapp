<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Cache;

use App\Infrastructure\Cache\RedisCache;
use App\Infrastructure\TlsConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Redis;
use RedisException;
use ReflectionClass;

/**
 * Unit tests for RedisCache — deletePattern, ttl, and disconnect behaviour
 *
 * These tests use mocking to test the cache logic without a real Redis connection.
 * For integration tests with actual Redis, see tests/php/App/Feature/.
 */
#[CoversClass(RedisCache::class)]
#[UsesClass(TlsConfig::class)]
final class RedisCacheDeletePatternAndTtlTest extends TestCase
{
    private const string TEST_URL    = 'redis://localhost:6379';

    private const string TEST_PREFIX = 'test:';

    // ===== deletePattern() =====

    #[Test]
    public function testDeletePatternDeletesMatchingKeys(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('keys')
            ->with('test:user:123:*')
            ->willReturn(['test:user:123:profile', 'test:user:123:settings']);

        $mockRedis->expects($this->once())
            ->method('del')
            ->with(['test:user:123:profile', 'test:user:123:settings'])
            ->willReturn(2);

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertEquals(2, $cache->deletePattern('user:123:*'));
    }

    #[Test]
    public function testDeletePatternReturnsZeroWhenNoKeysMatch(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('keys')
            ->with('test:nonexistent:*')
            ->willReturn([]);

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertEquals(0, $cache->deletePattern('nonexistent:*'));
    }

    #[Test]
    public function testDeletePatternReturnsZeroOnRedisException(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('keys')
            ->willThrowException(new RedisException('Connection lost'));

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertEquals(0, $cache->deletePattern('any:*'));
    }

    #[Test]
    public function testDeletePatternReturnsZeroWhenKeysReturnsFalse(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('keys')
            ->willReturn(false);

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertEquals(0, $cache->deletePattern('any:*'));
    }

    #[Test]
    public function testDeletePatternReturnsZeroWhenDelReturnsFalse(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('keys')
            ->willReturn(['test:a', 'test:b']);
        $mockRedis->expects($this->once())
            ->method('del')
            ->willReturn(false);

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertEquals(0, $cache->deletePattern('*'));
    }

    // ===== ttl() =====

    #[Test]
    public function testTtlReturnsRemainingTime(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('ttl')
            ->with('test:mykey')
            ->willReturn(3500);

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertEquals(3500, $cache->ttl('mykey'));
    }

    #[Test]
    public function testTtlReturnsNullWhenKeyDoesNotExist(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('ttl')
            ->with('test:missing')
            ->willReturn(-2); // Redis returns -2 for non-existent keys

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertNull($cache->ttl('missing'));
    }

    #[Test]
    public function testTtlReturnsMinusOneForKeyWithoutExpiry(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('ttl')
            ->with('test:persistent')
            ->willReturn(-1); // Redis returns -1 for keys without expiry

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertEquals(-1, $cache->ttl('persistent'));
    }

    #[Test]
    public function testTtlReturnsNullOnRedisException(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('ttl')
            ->willThrowException(new RedisException('Connection lost'));

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertNull($cache->ttl('anykey'));
    }

    #[Test]
    public function testTtlReturnsNullWhenRedisReturnsFalse(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('ttl')
            ->willReturn(false);

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertNull($cache->ttl('anykey'));
    }

    // ===== disconnect() (exercised via get/set exception paths) =====

    #[Test]
    public function testDisconnectClosesConnectionAndClearsReference(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('get')
            ->willThrowException(new RedisException('Connection lost'));
        $mockRedis->expects($this->once())
            ->method('close');

        $cache = $this->createCacheWithMockedRedis($mockRedis);
        $cache->get('key');

        // After exception, redis property should be null (reconnect on next call)
        $reflection = new ReflectionClass($cache);
        $property   = $reflection->getProperty('redis');
        $this->assertNull($property->getValue($cache));
    }

    #[Test]
    public function testDisconnectIgnoresRedisExceptionOnClose(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('get')
            ->willThrowException(new RedisException('Connection lost'));
        $mockRedis->expects($this->once())
            ->method('close')
            ->willThrowException(new RedisException('Close failed'));

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        // Should not throw even when close() fails
        $this->assertNull($cache->get('key'));

        $reflection = new ReflectionClass($cache);
        $property   = $reflection->getProperty('redis');
        $this->assertNull($property->getValue($cache));
    }

    // ===== getConnection() — cached connection =====

    #[Test]
    public function testGetConnectionReturnsCachedConnectionOnSecondCall(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->exactly(2))
            ->method('get')
            ->willReturn('value');

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        // Both calls should use the same injected Redis instance
        $this->assertEquals('value', $cache->get('key1'));
        $this->assertEquals('value', $cache->get('key2'));
    }

    /**
     * Create a RedisCache instance with a mocked Redis connection
     *
     * Note: Connection failure scenarios are covered by RedisException tests
     * (testGetReturnsNullOnRedisException, etc.). Actual connection failures
     * require network I/O and belong in integration tests.
     */
    private function createCacheWithMockedRedis(Redis $mockRedis, string $prefix = self::TEST_PREFIX): RedisCache
    {
        $cache = new RedisCache(self::TEST_URL, $prefix);

        // Inject mock via reflection
        $reflection = new ReflectionClass($cache);
        $property   = $reflection->getProperty('redis');
        $property->setValue($cache, $mockRedis);

        return $cache;
    }
}
