/**
 * Session Service for server-side session management
 *
 * Provides secure session storage using a CacheService backend.
 * Supports multi-session per user with session regeneration.
 *
 * Features:
 * - 256-bit secure session IDs (64 hex characters)
 * - Configurable TTL with automatic expiration
 * - Session regeneration for security
 * - Multi-session tracking per user
 * - User-wide session invalidation
 *
 * Key Patterns:
 * - Session data: session:{id} (JSON object)
 * - User sessions: user:{userId}:sessions (JSON array of session IDs)
 *
 * Security:
 * - Session IDs generated with crypto.randomBytes()
 * - Transmit via HttpOnly, Secure, SameSite=Strict cookies
 * - Regenerate after login/privilege changes
 *
 * Usage:
 * ```typescript
 * const session = new SessionService(cache);
 *
 * // Create session
 * const sessionId = session.generateId();
 * await session.set(sessionId, { userId: 123, role: 'admin' });
 *
 * // Set secure cookie
 * res.cookie('session', sessionId, {
 *   httpOnly: true,
 *   secure: true,
 *   sameSite: 'strict',
 * });
 *
 * // Retrieve session
 * const data = await session.get(sessionId);
 *
 * // After login, regenerate ID
 * const newId = await session.regenerate(sessionId);
 *
 * // Logout everywhere
 * await session.destroyUserSessions(userId);
 * ```
 */

import { randomBytes } from 'node:crypto';
import type { CacheServiceInterface } from './CacheService';

/**
 * Session data structure
 */
export interface SessionData {
  userId?: number;
  createdAt?: string;
  lastAccessedAt?: string;
  [key: string]: unknown;
}

/**
 * Session Service Interface
 */
export interface SessionServiceInterface {
  /**
   * Get session data
   */
  get(sessionId: string): Promise<SessionData | null>;

  /**
   * Store session data with optional TTL (null = use default)
   */
  set(sessionId: string, data: SessionData, ttl?: number | null): Promise<boolean>;

  /**
   * Update session data (merge with existing)
   */
  update(sessionId: string, data: Partial<SessionData>): Promise<boolean>;

  /**
   * Destroy a session
   */
  destroy(sessionId: string): Promise<boolean>;

  /**
   * Regenerate session ID
   * @returns New session ID or null if old session not found
   */
  regenerate(oldSessionId: string): Promise<string | null>;

  /**
   * Touch session (extend TTL)
   */
  touch(sessionId: string): Promise<boolean>;

  /**
   * Get all active sessions for a user
   */
  getUserSessions(userId: number): Promise<string[]>;

  /**
   * Destroy all sessions for a user
   * @returns Number of sessions destroyed
   */
  destroyUserSessions(userId: number): Promise<number>;

  /**
   * Generate a secure session ID
   */
  generateId(): string;
}

/**
 * Default configuration
 */
const DEFAULT_TTL = 86400; // 24 hours
const SESSION_PREFIX = 'session:';
const USER_SESSIONS_PREFIX = 'user:';
const USER_SESSIONS_SUFFIX = ':sessions';
const SESSION_ID_LENGTH = 64;
const SESSION_ID_REGEX = /^[a-f0-9]{64}$/i;

/**
 * Session Service Implementation
 */
export class SessionService implements SessionServiceInterface {
  private readonly cache: CacheServiceInterface;
  private readonly defaultTtl: number;

  constructor(cache: CacheServiceInterface, defaultTtl: number = DEFAULT_TTL) {
    this.cache = cache;
    this.defaultTtl = defaultTtl;
  }

  async get(sessionId: string): Promise<SessionData | null> {
    if (!this.isValidSessionId(sessionId)) {
      return null;
    }

    const json = await this.cache.get(this.sessionKey(sessionId));
    if (json === null) {
      return null;
    }

    try {
      const data = JSON.parse(json) as unknown;
      return typeof data === 'object' && data !== null ? (data as SessionData) : null;
    } catch {
      return null;
    }
  }

  async set(sessionId: string, data: SessionData, ttl?: number | null): Promise<boolean> {
    if (!this.isValidSessionId(sessionId)) {
      return false;
    }

    const json = JSON.stringify(data);
    const success = await this.cache.set(this.sessionKey(sessionId), json, ttl ?? this.defaultTtl);

    // Track session in user's session list if userId is present
    if (success && data.userId !== undefined) {
      await this.addSessionToUser(data.userId, sessionId);
    }

    return success;
  }

  async update(sessionId: string, data: Partial<SessionData>): Promise<boolean> {
    const existing = await this.get(sessionId);
    if (existing === null) {
      return false;
    }

    // Merge new data with existing
    const merged: SessionData = { ...existing, ...data };

    // Preserve remaining TTL
    const remainingTtl = await this.cache.ttl(this.sessionKey(sessionId));
    const ttl = remainingTtl !== null && remainingTtl > 0 ? remainingTtl : this.defaultTtl;

    return this.set(sessionId, merged, ttl);
  }

  async destroy(sessionId: string): Promise<boolean> {
    if (!this.isValidSessionId(sessionId)) {
      return false;
    }

    // Get session data to find userId for cleanup
    const data = await this.get(sessionId);
    if (data !== null && data.userId !== undefined) {
      await this.removeSessionFromUser(data.userId, sessionId);
    }

    return this.cache.delete(this.sessionKey(sessionId));
  }

  async regenerate(oldSessionId: string): Promise<string | null> {
    const data = await this.get(oldSessionId);
    if (data === null) {
      return null;
    }

    // Generate new ID
    const newSessionId = this.generateId();

    // Preserve remaining TTL from old session
    const remainingTtl = await this.cache.ttl(this.sessionKey(oldSessionId));
    const ttl = remainingTtl !== null && remainingTtl > 0 ? remainingTtl : this.defaultTtl;

    // Store with new ID
    const success = await this.set(newSessionId, data, ttl);
    if (!success) {
      return null;
    }

    // Destroy old session
    await this.destroy(oldSessionId);

    return newSessionId;
  }

  async touch(sessionId: string): Promise<boolean> {
    const data = await this.get(sessionId);
    if (data === null) {
      return false;
    }

    // Re-set with full TTL
    return this.set(sessionId, data, this.defaultTtl);
  }

  async getUserSessions(userId: number): Promise<string[]> {
    const json = await this.cache.get(this.userSessionsKey(userId));
    if (json === null) {
      return [];
    }

    try {
      const sessions = JSON.parse(json) as unknown;
      if (!Array.isArray(sessions)) {
        return [];
      }

      // Filter to valid session IDs that still exist
      const activeSessions: string[] = [];
      for (const sessionId of sessions) {
        if (typeof sessionId === 'string' && (await this.cache.has(this.sessionKey(sessionId)))) {
          activeSessions.push(sessionId);
        }
      }

      // Update list if any sessions were removed
      if (activeSessions.length !== sessions.length) {
        await this.updateUserSessionsList(userId, activeSessions);
      }

      return activeSessions;
    } catch {
      return [];
    }
  }

  async destroyUserSessions(userId: number): Promise<number> {
    const sessions = await this.getUserSessions(userId);
    let destroyed = 0;

    for (const sessionId of sessions) {
      // Delete session without recursive user-list cleanup
      const success = await this.cache.delete(this.sessionKey(sessionId));
      if (success) {
        destroyed++;
      }
    }

    // Clear user's session list
    await this.cache.delete(this.userSessionsKey(userId));

    return destroyed;
  }

  generateId(): string {
    return randomBytes(32).toString('hex'); // 256 bits = 64 hex chars
  }

  /**
   * Build session cache key
   */
  private sessionKey(sessionId: string): string {
    return SESSION_PREFIX + sessionId;
  }

  /**
   * Build user sessions list cache key
   */
  private userSessionsKey(userId: number): string {
    return USER_SESSIONS_PREFIX + userId + USER_SESSIONS_SUFFIX;
  }

  /**
   * Validate session ID format (64 hex characters)
   */
  private isValidSessionId(sessionId: string): boolean {
    return sessionId.length === SESSION_ID_LENGTH && SESSION_ID_REGEX.test(sessionId);
  }

  /**
   * Add a session ID to the user's sessions list
   */
  private async addSessionToUser(userId: number, sessionId: string): Promise<void> {
    const sessions = await this.getUserSessions(userId);

    if (!sessions.includes(sessionId)) {
      sessions.push(sessionId);
      await this.updateUserSessionsList(userId, sessions);
    }
  }

  /**
   * Remove a session ID from the user's sessions list
   */
  private async removeSessionFromUser(userId: number, sessionId: string): Promise<void> {
    const sessions = await this.getUserSessions(userId);
    const filtered = sessions.filter((id) => id !== sessionId);

    if (filtered.length > 0) {
      await this.updateUserSessionsList(userId, filtered);
    } else {
      await this.cache.delete(this.userSessionsKey(userId));
    }
  }

  /**
   * Update the user's sessions list in cache
   */
  private async updateUserSessionsList(userId: number, sessions: string[]): Promise<void> {
    const json = JSON.stringify(sessions);
    // User sessions list has same TTL as sessions
    await this.cache.set(this.userSessionsKey(userId), json, this.defaultTtl);
  }
}
