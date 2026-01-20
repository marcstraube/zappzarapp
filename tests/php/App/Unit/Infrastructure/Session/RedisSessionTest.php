<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Session;

use App\Infrastructure\Cache\CacheInterface;
use App\Infrastructure\Session\RedisSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

/**
 * Unit tests for RedisSession
 *
 * Uses a mocked CacheInterface to test session logic in isolation.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
#[CoversClass(RedisSession::class)]
final class RedisSessionTest extends TestCase
{
    private const int DEFAULT_TTL = 86400;

    public function testGetReturnsSessionData(): void
    {
        $sessionId   = $this->generateValidSessionId();
        $sessionData = ['userId' => 123, 'role' => 'admin'];

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with('session:' . $sessionId)
            ->willReturn(json_encode($sessionData));

        $session = new RedisSession($cache);

        $this->assertEquals($sessionData, $session->get($sessionId));
    }

    public function testGetReturnsNullForMissingSession(): void
    {
        $sessionId = $this->generateValidSessionId();

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $session = new RedisSession($cache);

        $this->assertNull($session->get($sessionId));
    }

    public function testGetReturnsNullForInvalidSessionId(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())
            ->method('get');

        $session = new RedisSession($cache);

        // Invalid session ID (too short)
        $this->assertNull($session->get('invalid'));
    }

    public function testGetReturnsNullForCorruptedJson(): void
    {
        $sessionId = $this->generateValidSessionId();

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->willReturn('not-valid-json');

        $session = new RedisSession($cache);

        $this->assertNull($session->get($sessionId));
    }

    public function testSetStoresSessionData(): void
    {
        $sessionId   = $this->generateValidSessionId();
        $sessionData = ['userId' => 123, 'role' => 'user'];

        $cache = $this->createMock(CacheInterface::class);

        // Expect session data storage
        $cache->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function (string $key, string $value, int $ttl) use ($sessionId): true {
                if ($key === 'session:' . $sessionId) {
                    $this->assertEquals(self::DEFAULT_TTL, $ttl);
                    $this->assertEquals(['userId' => 123, 'role' => 'user'], json_decode($value, true));
                }

                return true;
            });

        // Expect user sessions list retrieval for tracking
        $cache->expects($this->once())
            ->method('get')
            ->with('user:123:sessions')
            ->willReturn(null);

        $session = new RedisSession($cache);

        $this->assertTrue($session->set($sessionId, $sessionData));
    }

    public function testSetWithCustomTtl(): void
    {
        $sessionId   = $this->generateValidSessionId();
        $sessionData = ['data' => 'value'];
        $customTtl   = 3600;

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('set')
            ->with('session:' . $sessionId, $this->anything(), $customTtl)
            ->willReturn(true);

        $session = new RedisSession($cache);

        $this->assertTrue($session->set($sessionId, $sessionData, $customTtl));
    }

    public function testSetReturnsFalseForInvalidSessionId(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())
            ->method('set');

        $session = new RedisSession($cache);

        $this->assertFalse($session->set('invalid', ['data' => 'value']));
    }

    public function testUpdateMergesData(): void
    {
        $sessionId      = $this->generateValidSessionId();
        $existingData   = ['userId' => 123, 'role' => 'user'];
        $updateData     = ['lastActivity' => 1234567890];
        $expectedMerged = ['userId' => 123, 'role' => 'user', 'lastActivity' => 1234567890];

        $cache = $this->createMock(CacheInterface::class);

        // First get returns existing data
        $cache->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function (string $key) use ($sessionId, $existingData) {
                if ($key === 'session:' . $sessionId) {
                    return json_encode($existingData);
                }

                return null; // user sessions list
            });

        // Get TTL to preserve it
        $cache->expects($this->once())
            ->method('ttl')
            ->with('session:' . $sessionId)
            ->willReturn(3500);

        // Set with merged data
        $cache->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function (string $key, string $value, int $ttl) use ($sessionId, $expectedMerged): true {
                if ($key === 'session:' . $sessionId) {
                    $this->assertEquals(3500, $ttl);
                    $this->assertEquals($expectedMerged, json_decode($value, true));
                }

                return true;
            });

        $session = new RedisSession($cache);

        $this->assertTrue($session->update($sessionId, $updateData));
    }

    public function testUpdateReturnsFalseForNonExistentSession(): void
    {
        $sessionId = $this->generateValidSessionId();

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $session = new RedisSession($cache);

        $this->assertFalse($session->update($sessionId, ['data' => 'value']));
    }

    public function testDestroyRemovesSession(): void
    {
        $sessionId = $this->generateValidSessionId();

        $cache = $this->createMock(CacheInterface::class);

        // Get session data for userId lookup
        $cache->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function (string $key) use ($sessionId) {
                if ($key === 'session:' . $sessionId) {
                    return json_encode(['userId' => 123, 'data' => 'value']);
                }

                return json_encode([$sessionId]); // user sessions list
            });

        // Check if sessions exist for cleanup
        $cache->expects($this->once())
            ->method('has')
            ->willReturn(true);

        // Delete session and update user list
        $cache->expects($this->exactly(2))
            ->method('delete')
            ->willReturn(true);

        $session = new RedisSession($cache);

        $this->assertTrue($session->destroy($sessionId));
    }

    public function testDestroyReturnsTrueForNonExistentSession(): void
    {
        $sessionId = $this->generateValidSessionId();

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $cache->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $session = new RedisSession($cache);

        $this->assertTrue($session->destroy($sessionId));
    }

    public function testRegenerateCreatesNewIdAndCopiesData(): void
    {
        $oldSessionId = $this->generateValidSessionId();
        $sessionData  = ['userId' => 123, 'role' => 'admin'];

        $cache = $this->createMock(CacheInterface::class);

        // Get old session data and user sessions list (multiple calls)
        $cache->method('get')
            ->willReturnCallback(function (string $key) use ($oldSessionId, $sessionData) {
                if ($key === 'session:' . $oldSessionId) {
                    return json_encode($sessionData);
                }

                return null; // user sessions list
            });

        // Get TTL to preserve it (called once in regenerate)
        $cache->expects($this->once())
            ->method('ttl')
            ->willReturn(3500);

        // Store new session
        $cache->method('set')
            ->willReturn(true);

        // Delete old session
        $cache->method('delete')
            ->willReturn(true);

        $session      = new RedisSession($cache);
        $newSessionId = $session->regenerate($oldSessionId);

        $this->assertNotNull($newSessionId);
        $this->assertNotEquals($oldSessionId, $newSessionId);
        $this->assertEquals(64, strlen($newSessionId));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $newSessionId);
    }

    public function testRegenerateReturnsNullForNonExistentSession(): void
    {
        $sessionId = $this->generateValidSessionId();

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $session = new RedisSession($cache);

        $this->assertNull($session->regenerate($sessionId));
    }

    public function testTouchExtendsTtl(): void
    {
        $sessionId   = $this->generateValidSessionId();
        $sessionData = ['data' => 'value'];

        $cache = $this->createMock(CacheInterface::class);

        $cache->expects($this->once())
            ->method('get')
            ->willReturn(json_encode($sessionData));

        // Touch should re-set with default TTL
        $cache->expects($this->once())
            ->method('set')
            ->with('session:' . $sessionId, $this->anything(), self::DEFAULT_TTL)
            ->willReturn(true);

        $session = new RedisSession($cache);

        $this->assertTrue($session->touch($sessionId));
    }

    public function testTouchReturnsFalseForNonExistentSession(): void
    {
        $sessionId = $this->generateValidSessionId();

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $session = new RedisSession($cache);

        $this->assertFalse($session->touch($sessionId));
    }

    public function testGetUserSessionsReturnsActiveSessions(): void
    {
        $userId   = 123;
        $session1 = $this->generateValidSessionId();
        $session2 = $this->generateValidSessionId();

        $cache = $this->createMock(CacheInterface::class);

        $cache->expects($this->once())
            ->method('get')
            ->with('user:123:sessions')
            ->willReturn(json_encode([$session1, $session2]));

        // Check which sessions still exist
        $cache->expects($this->exactly(2))
            ->method('has')
            ->willReturn(true);

        $session = new RedisSession($cache);

        $sessions = $session->getUserSessions($userId);
        $this->assertCount(2, $sessions);
        $this->assertContains($session1, $sessions);
        $this->assertContains($session2, $sessions);
    }

    public function testGetUserSessionsFiltersExpiredSessions(): void
    {
        $userId         = 123;
        $activeSession  = $this->generateValidSessionId();
        $expiredSession = $this->generateValidSessionId();

        $cache = $this->createMock(CacheInterface::class);

        $cache->method('get')
            ->with('user:123:sessions')
            ->willReturn(json_encode([$activeSession, $expiredSession]));

        // Only first session exists
        $cache->method('has')
            ->willReturnCallback(fn(string $key): bool => str_contains($key, $activeSession));

        // List should be updated to remove expired session
        $cache->expects($this->once())
            ->method('set')
            ->with('user:123:sessions', $this->anything(), $this->anything())
            ->willReturn(true);

        $session = new RedisSession($cache);

        $sessions = $session->getUserSessions($userId);
        $this->assertCount(1, $sessions);
        $this->assertContains($activeSession, $sessions);
    }

    public function testGetUserSessionsReturnsEmptyArrayWhenNoSessions(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $session = new RedisSession($cache);

        $this->assertEquals([], $session->getUserSessions(123));
    }

    public function testDestroyUserSessionsRemovesAllSessions(): void
    {
        $userId   = 123;
        $session1 = $this->generateValidSessionId();
        $session2 = $this->generateValidSessionId();

        $cache = $this->createMock(CacheInterface::class);

        // Get user sessions list
        $cache->expects($this->once())
            ->method('get')
            ->willReturn(json_encode([$session1, $session2]));

        // Check which sessions exist
        $cache->expects($this->exactly(2))
            ->method('has')
            ->willReturn(true);

        // Delete both sessions + user list
        $cache->expects($this->exactly(3))
            ->method('delete')
            ->willReturn(true);

        $session = new RedisSession($cache);

        $this->assertEquals(2, $session->destroyUserSessions($userId));
    }

    public function testGenerateIdReturns64HexCharacters(): void
    {
        $cache   = $this->createMock(CacheInterface::class);
        $session = new RedisSession($cache);

        $id = $session->generateId();

        $this->assertEquals(64, strlen($id));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $id);
    }

    public function testGenerateIdIsUnique(): void
    {
        $cache   = $this->createMock(CacheInterface::class);
        $session = new RedisSession($cache);

        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = $session->generateId();
        }

        $uniqueIds = array_unique($ids);
        $this->assertCount(100, $uniqueIds);
    }

    public function testSessionIdValidationRejectsInvalidFormats(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');
        $cache->expects($this->never())->method('set');

        $session = new RedisSession($cache);

        // Too short
        $this->assertNull($session->get('abc123'));
        $this->assertFalse($session->set('abc123', ['data' => 'value']));

        // Too long
        $longId = str_repeat('a', 65);
        $this->assertNull($session->get($longId));
        $this->assertFalse($session->set($longId, ['data' => 'value']));

        // Invalid characters
        $invalidChars = str_repeat('g', 64);
        $this->assertNull($session->get($invalidChars));
        $this->assertFalse($session->set($invalidChars, ['data' => 'value']));
    }

    /**
     * Generate a valid 64-character hex session ID for testing
     */
    private function generateValidSessionId(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (RandomException) {
            // Fallback for the extremely rare case when no entropy source is available
            return str_repeat('a', 64);
        }
    }
}
