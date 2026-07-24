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
 * Unit tests for RedisCache — get, set, has, delete, isAvailable, and constructor
 *
 * These tests use mocking to test the cache logic without a real Redis connection.
 * For integration tests with actual Redis, see tests/php/App/Feature/.
 */
#[CoversClass(RedisCache::class)]
#[UsesClass(TlsConfig::class)]
final class RedisCacheTest extends TestCase
{
    private const string TEST_URL    = 'redis://localhost:6379';

    private const string TEST_PREFIX = 'test:';

    #[Test]
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

    #[Test]
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

    #[Test]
    public function testGetReturnsNullOnRedisException(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('get')
            ->willThrowException(new RedisException('Connection lost'));

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertNull($cache->get('anykey'));
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function testSetReturnsFalseOnRedisException(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('setex')
            ->willThrowException(new RedisException('Connection lost'));

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertFalse($cache->set('mykey', 'myvalue'));
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function testHasReturnsFalseOnRedisException(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('exists')
            ->willThrowException(new RedisException('Connection lost'));

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertFalse($cache->has('anykey'));
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function testDeleteReturnsFalseOnRedisException(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('del')
            ->willThrowException(new RedisException('Connection lost'));

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertFalse($cache->delete('anykey'));
    }

    #[Test]
    public function testIsAvailableReturnsTrueWhenPingSucceeds(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('ping')
            ->willReturn('+PONG');

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertTrue($cache->isAvailable());
    }

    #[Test]
    public function testIsAvailableReturnsFalseOnRedisException(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('ping')
            ->willThrowException(new RedisException('Connection lost'));

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertFalse($cache->isAvailable());
    }

    #[Test]
    public function testIsAvailableReturnsFalseWhenPingReturnsFalse(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('ping')
            ->willReturn(false);

        $cache = $this->createCacheWithMockedRedis($mockRedis);

        $this->assertFalse($cache->isAvailable());
    }

    #[Test]
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

    #[Test]
    public function testConstructorAcceptsCustomUrl(): void
    {
        $cache = new RedisCache('redis://custom:1234', 'prefix:');

        // Use reflection to check private property
        $reflection  = new ReflectionClass($cache);
        $urlProperty = $reflection->getProperty('redisUrl');

        $this->assertEquals('redis://custom:1234', $urlProperty->getValue($cache));
    }

    #[Test]
    public function testConstructorUsesEnvRedisUrl(): void
    {
        $_ENV['REDIS_URL'] = 'redis://envhost:1234';

        $cache      = new RedisCache();
        $reflection = new ReflectionClass($cache);
        $property   = $reflection->getProperty('redisUrl');

        $this->assertEquals('redis://envhost:1234', $property->getValue($cache));

        unset($_ENV['REDIS_URL']);
    }

    #[Test]
    public function testConstructorUsesDefaultUrlWhenEnvIsEmpty(): void
    {
        unset($_ENV['REDIS_URL']);

        $cache      = new RedisCache(null, 'app:', 2);
        $reflection = new ReflectionClass($cache);
        $property   = $reflection->getProperty('redisUrl');

        $this->assertEquals('rediss://redis:6379', $property->getValue($cache));
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
