<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Session;

use App\Infrastructure\Cache\CacheInterface;
use App\Infrastructure\Session\RedisSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

/**
 * Edge case tests for RedisSession — error paths and boundary conditions
 * not covered by RedisSessionTest (kept separate to respect the 20-method limit).
 */
#[CoversClass(RedisSession::class)]
final class RedisSessionEdgeCasesTest extends TestCase
{
    #[Test]
    public function testDestroyReturnsFalseForInvalidSessionId(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');
        $cache->expects($this->never())->method('delete');

        $session = new RedisSession($cache);

        $this->assertFalse($session->destroy('too-short'));
    }

    #[Test]
    public function testRegenerateReturnsNullWhenStoringNewSessionFails(): void
    {
        $oldSessionId = $this->generateValidSessionId();
        $sessionData  = ['userId' => 42];

        $cache = $this->createStub(CacheInterface::class);

        // get() returns session data for the old ID, null for user sessions list
        $cache->method('get')
            ->willReturnCallback(
                fn(string $key): ?string => $key === 'session:' . $oldSessionId ? (string) json_encode($sessionData) : null
            );

        $cache->method('ttl')->willReturn(3600);

        // set() always fails — the new session cannot be stored
        $cache->method('set')->willReturn(false);

        $session = new RedisSession($cache);

        $this->assertNull($session->regenerate($oldSessionId));
    }

    #[Test]
    public function testGetUserSessionsReturnsEmptyArrayWhenDecodedValueIsNotArray(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        // Cache contains a JSON scalar (not an array)
        $cache->expects($this->once())
            ->method('get')
            ->with('user:7:sessions')
            ->willReturn(json_encode('not-an-array'));

        $session = new RedisSession($cache);

        $this->assertSame([], $session->getUserSessions(7));
    }

    #[Test]
    public function testGetUserSessionsReturnsEmptyArrayOnCorruptedJson(): void
    {
        $cache = $this->createMock(CacheInterface::class);

        $cache->expects($this->once())
            ->method('get')
            ->with('user:8:sessions')
            ->willReturn('{invalid json]');

        $session = new RedisSession($cache);

        $this->assertSame([], $session->getUserSessions(8));
    }

    #[Test]
    public function testDestroyDeletesUserSessionsKeyWhenLastSessionIsRemoved(): void
    {
        $userId    = 99;
        $sessionId = $this->generateValidSessionId();

        $cache = $this->createMock(CacheInterface::class);

        // First call: get session data (has userId) — second call: get user sessions list
        $cache->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(
                fn(string $key): string => $key === 'session:' . $sessionId
                    ? (string) json_encode(['userId' => $userId])
                    : (string) json_encode([$sessionId])
            );

        // has() checks whether session key exists (for getUserSessions filter step)
        $cache->expects($this->once())
            ->method('has')
            ->willReturn(true);

        // After removing the only session from the list, the user sessions key is
        // deleted (not updated) because the remaining list is empty.
        // delete() is called twice: once for the user sessions key (removeSessionFromUser
        // empty-list branch) and once for the session key itself (destroy()).
        $cache->expects($this->exactly(2))
            ->method('delete')
            ->willReturn(true);

        $session = new RedisSession($cache);

        $this->assertTrue($session->destroy($sessionId));
    }

    /**
     * Generate a valid 64-character hex session ID for testing
     */
    private function generateValidSessionId(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (RandomException) {
            return str_repeat('a', 64);
        }
    }
}
