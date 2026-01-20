<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

/**
 * Session Interface for server-side session management
 *
 * Provides secure session storage with automatic expiration.
 * Uses a cache backend (Redis) for distributed session support across multiple servers.
 *
 * Security Features:
 * - 256-bit session IDs (64 hex characters)
 * - Configurable TTL with automatic expiration
 * - Session regeneration to prevent fixation attacks
 * - Multi-session tracking per user (for logout-everywhere)
 * - Automatic cleanup of expired sessions
 *
 * Session ID Security:
 * - Generate IDs with generateId() (uses random_bytes)
 * - Transmit via HttpOnly, Secure, SameSite=Strict cookies
 * - Never expose in URLs or logs
 * - Regenerate after privilege changes (login, role change)
 *
 * When to use:
 * - User authentication state
 * - Shopping cart data
 * - Multi-step form wizards
 * - CSRF tokens
 *
 * When NOT to use:
 * - Large binary data (use file storage)
 * - Data that must survive cache failures (use database)
 * - Real-time collaboration data (use WebSocket + database)
 *
 * Usage:
 * <code>
 * // Get from DI container
 * $session = $container->get(SessionInterface::class);
 *
 * // Create new session
 * $sessionId = $session->generateId();
 * $session->set($sessionId, [
 *     'userId' => 123,
 *     'role' => 'user',
 *     'loginAt' => time(),
 * ]);
 *
 * // Set secure cookie
 * setcookie('session', $sessionId, [
 *     'httponly' => true,
 *     'secure' => true,
 *     'samesite' => 'Strict',
 *     'expires' => time() + 86400,
 * ]);
 *
 * // Retrieve session
 * $data = $session->get($sessionId);
 *
 * // After login, regenerate ID to prevent fixation
 * $newId = $session->regenerate($sessionId);
 *
 * // Logout everywhere (e.g., after password change)
 * $session->destroyUserSessions($userId);
 * </code>
 *
 * @package Infrastructure\Session
 */
interface SessionInterface
{
    /**
     * Get session data
     *
     * @param string $sessionId Secure session identifier (64 hex chars)
     * @return array<string, mixed>|null Session data or null if not found/expired
     */
    public function get(string $sessionId): ?array;

    /**
     * Store session data
     *
     * @param string $sessionId Secure session identifier
     * @param array<string, mixed> $data Session data (must be JSON-serializable)
     * @param int|null $ttl TTL in seconds (null = use default session lifetime)
     * @return bool True if stored successfully
     */
    public function set(string $sessionId, array $data, ?int $ttl = null): bool;

    /**
     * Update session data (merge with existing)
     *
     * Performs a read-modify-write operation. The new data is merged with
     * existing session data (new values override existing keys).
     *
     * @param string $sessionId Session identifier
     * @param array<string, mixed> $data Data to merge into session
     * @return bool True if updated successfully, false if session doesn't exist
     */
    public function update(string $sessionId, array $data): bool;

    /**
     * Destroy a session
     *
     * Also removes the session from the user's session list if applicable.
     *
     * @param string $sessionId Session identifier
     * @return bool True if destroyed (or didn't exist)
     */
    public function destroy(string $sessionId): bool;

    /**
     * Regenerate session ID (for security after privilege change)
     *
     * Creates a new session ID, copies all data from the old session,
     * destroys the old session, and returns the new ID.
     *
     * Use this after:
     * - Successful login
     * - Password change
     * - Role/permission elevation
     *
     * @param string $oldSessionId Current session ID
     * @return string|null New session ID, null if old session not found
     */
    public function regenerate(string $oldSessionId): ?string;

    /**
     * Touch session (extend TTL without modifying data)
     *
     * Use this on each authenticated request to keep the session alive.
     *
     * @param string $sessionId Session identifier
     * @return bool True if TTL was extended
     */
    public function touch(string $sessionId): bool;

    /**
     * Get all active session IDs for a user
     *
     * Requires that sessions are stored with a 'userId' key.
     *
     * @param int $userId User identifier
     * @return array<int, string> List of session IDs
     */
    public function getUserSessions(int $userId): array;

    /**
     * Destroy all sessions for a user (e.g., after password change)
     *
     * Use this for security-critical events:
     * - Password change
     * - Account compromise
     * - Admin-initiated logout
     *
     * @param int $userId User identifier
     * @return int Number of sessions destroyed
     */
    public function destroyUserSessions(int $userId): int;

    /**
     * Generate a secure session ID
     *
     * @return string 64-character hexadecimal session ID (256 bits of entropy)
     */
    public function generateId(): string;
}
