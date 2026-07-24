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
 * Unit tests for RedisCache — connection factory, TLS, auth, URL parsing, and unavailable-connection paths
 *
 * These tests use mocking to test the cache logic without a real Redis connection.
 * For integration tests with actual Redis, see tests/php/App/Feature/.
 */
#[CoversClass(RedisCache::class)]
#[UsesClass(TlsConfig::class)]
final class RedisCacheConnectionTest extends TestCase
{
    private const string TEST_URL    = 'redis://localhost:6379';

    private const string TEST_PREFIX = 'test:';

    // ===== getConnection() — factory-based connection scenarios =====

    #[Test]
    public function testGetConnectionUsesFactoryForPlainConnection(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('connect')
            ->with('localhost', 6379, 2)
            ->willReturn(true);
        $mockRedis->expects($this->once())
            ->method('get')
            ->willReturn('cached');

        $cache = $this->createCacheWithFactory('redis://localhost:6379', $mockRedis);

        $this->assertEquals('cached', $cache->get('key'));
    }

    #[Test]
    public function testGetConnectionUsesTlsForRedissScheme(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('connect')
            ->with(
                'redis',
                6379,
                2,
                '',
                0,
                0,
                $this->isArray()
            )
            ->willReturn(true);
        $mockRedis->expects($this->once())
            ->method('get')
            ->willReturn('value');

        $cache = $this->createCacheWithFactory('rediss://redis:6379', $mockRedis);

        $this->assertEquals('value', $cache->get('key'));
    }

    #[Test]
    public function testGetConnectionSkipsAuthWhenNoPassword(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('connect')
            ->willReturn(true);
        $mockRedis->expects($this->never())
            ->method('auth');
        $mockRedis->expects($this->once())
            ->method('get')
            ->willReturn('value');

        $cache = $this->createCacheWithFactory('redis://localhost:6379', $mockRedis);

        $this->assertEquals('value', $cache->get('key'));
    }

    #[Test]
    public function testGetConnectionAuthenticatesWithUsernameAndPassword(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('connect')
            ->willReturn(true);
        $mockRedis->expects($this->once())
            ->method('auth')
            ->with(['admin', 'secret']);
        $mockRedis->expects($this->once())
            ->method('get')
            ->willReturn('value');

        $cache = $this->createCacheWithFactory('redis://admin:secret@localhost:6379', $mockRedis);

        $this->assertEquals('value', $cache->get('key'));
    }

    #[Test]
    public function testGetConnectionSelectsDatabaseWhenSpecified(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('connect')
            ->willReturn(true);
        $mockRedis->expects($this->once())
            ->method('select')
            ->with(3);
        $mockRedis->expects($this->once())
            ->method('get')
            ->willReturn('value');

        $cache = $this->createCacheWithFactory('redis://localhost:6379/3', $mockRedis);

        $this->assertEquals('value', $cache->get('key'));
    }

    #[Test]
    public function testGetConnectionReturnsNullWhenConnectFails(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('connect')
            ->willReturn(false);

        $cache = $this->createCacheWithFactory('redis://localhost:6379', $mockRedis);

        $this->assertNull($cache->get('key'));
    }

    #[Test]
    public function testGetConnectionReturnsNullOnRedisException(): void
    {
        $mockRedis = $this->createMock(Redis::class);
        $mockRedis->expects($this->once())
            ->method('connect')
            ->willThrowException(new RedisException('Connection refused'));

        $cache = $this->createCacheWithFactory('redis://localhost:6379', $mockRedis);

        $this->assertNull($cache->get('key'));
    }

    // ===== parseRedisUrl() =====

    #[Test]
    public function testParseRedisUrlHandlesUrlWithDatabase(): void
    {
        $cache = new RedisCache('redis://localhost:6379/5', 'app:');

        $reflection = new ReflectionClass($cache);
        $method     = $reflection->getMethod('parseRedisUrl');

        /** @var array{host: string, port: int, user: string|null, password: string|null, database: int} $result */
        $result = $method->invoke($cache, 'redis://localhost:6379/5');

        $this->assertEquals('localhost', $result['host']);
        $this->assertEquals(6379, $result['port']);
        $this->assertEquals(5, $result['database']);
        $this->assertNull($result['user']);
        $this->assertNull($result['password']);
    }

    #[Test]
    public function testParseRedisUrlHandlesRedissScheme(): void
    {
        $cache = new RedisCache('rediss://redis:6379', 'app:');

        $reflection = new ReflectionClass($cache);
        $method     = $reflection->getMethod('parseRedisUrl');

        /** @var array{host: string, port: int, user: string|null, password: string|null, database: int} $result */
        $result = $method->invoke($cache, 'rediss://redis:6379');

        $this->assertEquals('redis', $result['host']);
        $this->assertEquals(6379, $result['port']);
        $this->assertEquals(0, $result['database']);
    }

    #[Test]
    public function testParseRedisUrlHandlesUserAndPassword(): void
    {
        $cache = new RedisCache('redis://user:pass@host:1234', 'app:');

        $reflection = new ReflectionClass($cache);
        $method     = $reflection->getMethod('parseRedisUrl');

        /** @var array{host: string, port: int, user: string|null, password: string|null, database: int} $result */
        $result = $method->invoke($cache, 'redis://user:pass@host:1234');

        $this->assertEquals('host', $result['host']);
        $this->assertEquals(1234, $result['port']);
        $this->assertEquals('user', $result['user']);
        $this->assertEquals('pass', $result['password']);
    }

    #[Test]
    public function testParseRedisUrlHandlesUrlEncodedCredentials(): void
    {
        $cache = new RedisCache('redis://user%40domain:p%40ss@host:6379', 'app:');

        $reflection = new ReflectionClass($cache);
        $method     = $reflection->getMethod('parseRedisUrl');

        /** @var array{host: string, port: int, user: string|null, password: string|null, database: int} $result */
        $result = $method->invoke($cache, 'redis://user%40domain:p%40ss@host:6379');

        $this->assertEquals('user@domain', $result['user']);
        $this->assertEquals('p@ss', $result['password']);
    }

    #[Test]
    public function testParseRedisUrlUsesDefaultsForMinimalUrl(): void
    {
        $cache = new RedisCache('redis://localhost', 'app:');

        $reflection = new ReflectionClass($cache);
        $method     = $reflection->getMethod('parseRedisUrl');

        /** @var array{host: string, port: int, user: string|null, password: string|null, database: int} $result */
        $result = $method->invoke($cache, 'redis://localhost');

        $this->assertEquals('localhost', $result['host']);
        $this->assertEquals(6379, $result['port']);
        $this->assertEquals(0, $result['database']);
    }

    #[Test]
    public function testParseRedisUrlIgnoresRootPath(): void
    {
        $cache = new RedisCache('redis://localhost:6379/', 'app:');

        $reflection = new ReflectionClass($cache);
        $method     = $reflection->getMethod('parseRedisUrl');

        /** @var array{host: string, port: int, user: string|null, password: string|null, database: int} $result */
        $result = $method->invoke($cache, 'redis://localhost:6379/');

        $this->assertEquals(0, $result['database']);
    }

    // ===== early-return branches when connection is unavailable =====

    #[Test]
    public function testSetReturnsFalseWhenConnectionUnavailable(): void
    {
        $cache = $this->createCacheWithNoConnection();

        $this->assertFalse($cache->set('key', 'value'));
    }

    #[Test]
    public function testHasReturnsFalseWhenConnectionUnavailable(): void
    {
        $cache = $this->createCacheWithNoConnection();

        $this->assertFalse($cache->has('key'));
    }

    #[Test]
    public function testDeleteReturnsFalseWhenConnectionUnavailable(): void
    {
        $cache = $this->createCacheWithNoConnection();

        $this->assertFalse($cache->delete('key'));
    }

    #[Test]
    public function testDeletePatternReturnsZeroWhenConnectionUnavailable(): void
    {
        $cache = $this->createCacheWithNoConnection();

        $this->assertEquals(0, $cache->deletePattern('key:*'));
    }

    #[Test]
    public function testTtlReturnsNullWhenConnectionUnavailable(): void
    {
        $cache = $this->createCacheWithNoConnection();

        $this->assertNull($cache->ttl('key'));
    }

    #[Test]
    public function testIsAvailableReturnsFalseWhenConnectionUnavailable(): void
    {
        $cache = $this->createCacheWithNoConnection();

        $this->assertFalse($cache->isAvailable());
    }

    /**
     * Create a RedisCache that uses a factory returning the given mock.
     *
     * This allows testing getConnection() logic (TLS, auth, database select,
     * connect failure) without any real network I/O.
     */
    private function createCacheWithFactory(string $url, Redis $mockRedis, string $prefix = self::TEST_PREFIX): RedisCache
    {
        return new RedisCache($url, $prefix, 2, static fn(): Redis => $mockRedis);
    }

    /**
     * Create a RedisCache whose connect() always throws, so getConnection() returns null.
     *
     * This exercises the early-return branches in every public method:
     * set/has/delete/deletePattern/ttl/isAvailable all return false/null/0 when
     * no connection is available.
     */
    private function createCacheWithNoConnection(string $prefix = self::TEST_PREFIX): RedisCache
    {
        $stubRedis = $this->createStub(Redis::class);
        $stubRedis->method('connect')
            ->willThrowException(new RedisException('Connection refused'));

        return $this->createCacheWithFactory(self::TEST_URL, $stubRedis, $prefix);
    }
}
