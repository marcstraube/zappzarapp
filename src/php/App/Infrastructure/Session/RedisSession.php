<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Infrastructure\Cache\CacheInterface;
use JsonException;

/**
 * Redis Session Implementation
 *
 * Server-side session management using a CacheInterface backend.
 * Provides secure session storage with multi-session support per user.
 *
 * Key Patterns:
 * - Session data: session:{id} (JSON object)
 * - User sessions: user:{userId}:sessions (JSON array of session IDs)
 *
 * Configuration:
 * - Default TTL: 24 hours (86400 seconds)
 * - Session ID: 64 hex characters (256 bits)
 *
 * Threading/Concurrency:
 * - Read-modify-write operations (update) are NOT atomic
 * - For high-concurrency scenarios, consider Redis WATCH/MULTI or Lua scripts
 *
 * Usage:
 * <code>
 * $session = new RedisSession($cache);
 *
 * // Create session
 * $id = $session->generateId();
 * $session->set($id, ['userId' => 123, 'role' => 'admin']);
 *
 * // Retrieve
 * $data = $session->get($id);
 *
 * // Update specific field
 * $session->update($id, ['lastActivity' => time()]);
 *
 * // Security: regenerate after login
 * $newId = $session->regenerate($id);
 *
 * // Logout everywhere
 * $session->destroyUserSessions(123);
 * </code>
 *
 * @package Infrastructure\Session
 */
final readonly class RedisSession implements SessionInterface
{
    /** 24 hours */
    private const int DEFAULT_TTL = 86400;

    private const string SESSION_PREFIX = 'session:';

    private const string USER_SESSIONS_PREFIX = 'user:';

    private const string USER_SESSIONS_SUFFIX = ':sessions';

    public function __construct(
        private CacheInterface $cache,
        private int $defaultTtl = self::DEFAULT_TTL
    ) {
    }

    public function get(string $sessionId): ?array
    {
        if (!$this->isValidSessionId($sessionId)) {
            return null;
        }

        $json = $this->cache->get($this->sessionKey($sessionId));
        if ($json === null) {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : null;
        } catch (JsonException) {
            return null;
        }
    }

    public function set(string $sessionId, array $data, ?int $ttl = null): bool
    {
        if (!$this->isValidSessionId($sessionId)) {
            return false;
        }

        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        $success = $this->cache->set(
            $this->sessionKey($sessionId),
            $json,
            $ttl ?? $this->defaultTtl
        );

        // Track session in user's session list if userId is present
        if ($success && isset($data['userId']) && is_int($data['userId'])) {
            $this->addSessionToUser($data['userId'], $sessionId);
        }

        return $success;
    }

    public function update(string $sessionId, array $data): bool
    {
        $existing = $this->get($sessionId);
        if ($existing === null) {
            return false;
        }

        // Merge new data with existing (new values override)
        $merged = array_merge($existing, $data);

        // Preserve remaining TTL
        $remainingTtl = $this->cache->ttl($this->sessionKey($sessionId));
        $ttl          = ($remainingTtl !== null && $remainingTtl > 0) ? $remainingTtl : $this->defaultTtl;

        return $this->set($sessionId, $merged, $ttl);
    }

    public function destroy(string $sessionId): bool
    {
        if (!$this->isValidSessionId($sessionId)) {
            return false;
        }

        // Get session data to find userId for cleanup
        $data = $this->get($sessionId);
        if ($data !== null && isset($data['userId']) && is_int($data['userId'])) {
            $this->removeSessionFromUser($data['userId'], $sessionId);
        }

        return $this->cache->delete($this->sessionKey($sessionId));
    }

    public function regenerate(string $oldSessionId): ?string
    {
        $data = $this->get($oldSessionId);
        if ($data === null) {
            return null;
        }

        // Generate new ID and copy data
        $newSessionId = $this->generateId();

        // Preserve remaining TTL from old session
        $remainingTtl = $this->cache->ttl($this->sessionKey($oldSessionId));
        $ttl          = ($remainingTtl !== null && $remainingTtl > 0) ? $remainingTtl : $this->defaultTtl;

        // Store with new ID
        if (!$this->set($newSessionId, $data, $ttl)) {
            return null;
        }

        // Destroy old session (also removes from user's session list)
        $this->destroy($oldSessionId);

        // The new session was already added to user's session list in set()

        return $newSessionId;
    }

    public function touch(string $sessionId): bool
    {
        $data = $this->get($sessionId);
        if ($data === null) {
            return false;
        }

        // Re-set with full TTL
        return $this->set($sessionId, $data, $this->defaultTtl);
    }

    public function getUserSessions(int $userId): array
    {
        $json = $this->cache->get($this->userSessionsKey($userId));
        if ($json === null) {
            return [];
        }

        try {
            $sessions = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($sessions)) {
                return [];
            }

            // Filter out expired sessions
            $activeSessions = [];
            foreach ($sessions as $sessionId) {
                if (is_string($sessionId) && $this->cache->has($this->sessionKey($sessionId))) {
                    $activeSessions[] = $sessionId;
                }
            }

            // Update the list if any sessions were removed
            if (count($activeSessions) !== count($sessions)) {
                $this->updateUserSessionsList($userId, $activeSessions);
            }

            return $activeSessions;
        } catch (JsonException) {
            return [];
        }
    }

    public function destroyUserSessions(int $userId): int
    {
        $sessions  = $this->getUserSessions($userId);
        $destroyed = 0;

        foreach ($sessions as $sessionId) {
            // Delete session without recursive user-list cleanup
            if ($this->cache->delete($this->sessionKey($sessionId))) {
                $destroyed++;
            }
        }

        // Clear user's session list
        $this->cache->delete($this->userSessionsKey($userId));

        return $destroyed;
    }

    public function generateId(): string
    {
        return bin2hex(random_bytes(32)); // 256 bits = 64 hex chars
    }

    /**
     * Build session cache key
     */
    private function sessionKey(string $sessionId): string
    {
        return self::SESSION_PREFIX . $sessionId;
    }

    /**
     * Build user sessions list cache key
     */
    private function userSessionsKey(int $userId): string
    {
        return self::USER_SESSIONS_PREFIX . $userId . self::USER_SESSIONS_SUFFIX;
    }

    /**
     * Validate session ID format (64 hex characters)
     */
    private function isValidSessionId(string $sessionId): bool
    {
        return preg_match('/^[a-f0-9]{64}$/i', $sessionId) === 1;
    }

    /**
     * Add a session ID to the user's sessions list
     */
    private function addSessionToUser(int $userId, string $sessionId): void
    {
        $sessions = $this->getUserSessions($userId);

        if (!in_array($sessionId, $sessions, true)) {
            $sessions[] = $sessionId;
            $this->updateUserSessionsList($userId, $sessions);
        }
    }

    /**
     * Remove a session ID from the user's sessions list
     */
    private function removeSessionFromUser(int $userId, string $sessionId): void
    {
        $sessions = $this->getUserSessions($userId);
        $sessions = array_values(array_filter(
            $sessions,
            static fn(string $id): bool => $id !== $sessionId
        ));

        if ($sessions !== []) {
            $this->updateUserSessionsList($userId, $sessions);
        } else {
            $this->cache->delete($this->userSessionsKey($userId));
        }
    }

    /**
     * Update the user's sessions list in cache
     *
     * @param int $userId User ID
     * @param array<int, string> $sessions List of session IDs
     */
    private function updateUserSessionsList(int $userId, array $sessions): void
    {
        try {
            $json = json_encode(array_values($sessions), JSON_THROW_ON_ERROR);
            // User sessions list has same TTL as sessions (they'll expire together)
            $this->cache->set($this->userSessionsKey($userId), $json, $this->defaultTtl);
        } catch (JsonException) {
            // Ignore encoding errors for session list
        }
    }
}
