<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Cache;

use App\Infrastructure\Cache\RedisCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Redis;
use RedisException;
use ReflectionClass;

/**
 * Unit tests for RedisCache
 *
 * These tests use mocking to test the cache logic without a real Redis connection.
 * For integration tests with actual Redis, see tests/php/App/Feature/.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
#[CoversClass(RedisCache::class)]
final class RedisCacheTest extends TestCase
{
    private const string TEST_URL    = 'redis://localhost:6379';

    private const string TEST_PREFIX = 'test:';

    public function testGetReturnsValueWhenKeyExists(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('get')
            ->with('test:mykey')
            ->willReturn('myvalue');

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertEquals('myvalue', $cache->get('mykey'));
    }

    public function testGetReturnsNullWhenKeyDoesNotExist(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('get')
            ->with('test:missing')
            ->willReturn(false);

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertNull($cache->get('missing'));
    }

    public function testGetReturnsNullOnRedisException(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('get')
            ->willThrowException(new RedisException('Connection lost'));

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertNull($cache->get('anykey'));
    }

    public function testSetStoresValueWithTtl(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('setex')
            ->with('test:mykey', 7200, 'myvalue')
            ->willReturn(true);

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertTrue($cache->set('mykey', 'myvalue', 7200));
    }

    public function testSetUsesDefaultTtlWhenNotSpecified(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('setex')
            ->with('test:mykey', 3600, 'myvalue')
            ->willReturn(true);

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertTrue($cache->set('mykey', 'myvalue'));
    }

    public function testSetReturnsFalseOnRedisException(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('setex')
            ->willThrowException(new RedisException('Connection lost'));

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertFalse($cache->set('mykey', 'myvalue'));
    }

    public function testHasReturnsTrueWhenKeyExists(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('exists')
            ->with('test:mykey')
            ->willReturn(1);

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertTrue($cache->has('mykey'));
    }

    public function testHasReturnsFalseWhenKeyDoesNotExist(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('exists')
            ->with('test:missing')
            ->willReturn(0);

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertFalse($cache->has('missing'));
    }

    public function testHasReturnsFalseOnRedisException(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('exists')
            ->willThrowException(new RedisException('Connection lost'));

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertFalse($cache->has('anykey'));
    }

    public function testDeleteRemovesKey(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('del')
            ->with('test:mykey')
            ->willReturn(1);

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertTrue($cache->delete('mykey'));
    }

    public function testDeleteReturnsTrueEvenIfKeyDidNotExist(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('del')
            ->with('test:missing')
            ->willReturn(0);

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertTrue($cache->delete('missing'));
    }

    public function testDeleteReturnsFalseOnRedisException(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('del')
            ->willThrowException(new RedisException('Connection lost'));

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertFalse($cache->delete('anykey'));
    }

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

    public function testDeletePatternReturnsZeroOnRedisException(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('keys')
            ->willThrowException(new RedisException('Connection lost'));

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertEquals(0, $cache->deletePattern('any:*'));
    }

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

    public function testTtlReturnsNullOnRedisException(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('ttl')
            ->willThrowException(new RedisException('Connection lost'));

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertNull($cache->ttl('anykey'));
    }

    public function testIsAvailableReturnsTrueWhenPingSucceeds(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('ping')
            ->willReturn('+PONG');

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertTrue($cache->isAvailable());
    }

    public function testIsAvailableReturnsFalseOnRedisException(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('ping')
            ->willThrowException(new RedisException('Connection lost'));

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertFalse($cache->isAvailable());
    }

    public function testKeyPrefixIsApplied(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('get')
            ->with('custom:mykey') // Custom prefix
            ->willReturn('value');

        $cache = $this->createCacheWithMockedRedis($mockRedis, 'custom:');

        $this->assertEquals('value', $cache->get('mykey'));
    }

    public function testConstructorAcceptsCustomUrl(): void
    {
        $cache = new RedisCache('redis://custom:1234', 'prefix:');

        // Use reflection to check private property
        $reflection  = new ReflectionClass($cache);
        $urlProperty = $reflection->getProperty('redisUrl');

        $this->assertEquals('redis://custom:1234', $urlProperty->getValue($cache));
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
