/**
 * Tests for SessionService
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
  SessionService,
  type SessionData,
  type SessionServiceInterface,
} from '@backend/services/SessionService';
import type { CacheServiceInterface } from '@backend/services/CacheService';

/**
 * Create a mock CacheService
 */
function createMockCache(): CacheServiceInterface {
  return {
    connect: vi.fn().mockResolvedValue(undefined),
    disconnect: vi.fn().mockResolvedValue(undefined),
    get: vi.fn().mockResolvedValue(null),
    set: vi.fn().mockResolvedValue(true),
    has: vi.fn().mockResolvedValue(false),
    delete: vi.fn().mockResolvedValue(true),
    deletePattern: vi.fn().mockResolvedValue(0),
    ttl: vi.fn().mockResolvedValue(null),
    isAvailable: vi.fn().mockResolvedValue(true),
  };
}

/**
 * Generate a valid 64-character hex session ID
 */
function generateValidSessionId(): string {
  const chars = '0123456789abcdef';
  let id = '';
  for (let i = 0; i < 64; i++) {
    id += chars[Math.floor(Math.random() * 16)];
  }
  return id;
}

describe('SessionService', () => {
  let mockCache: CacheServiceInterface;

  beforeEach(() => {
    mockCache = createMockCache();
    vi.clearAllMocks();
  });

  describe('get', () => {
    it('should return session data', async () => {
      const sessionId = generateValidSessionId();
      const sessionData: SessionData = { userId: 123, role: 'admin' };

      vi.mocked(mockCache.get).mockResolvedValueOnce(JSON.stringify(sessionData));

      const session = new SessionService(mockCache);
      const result = await session.get(sessionId);

      expect(result).toEqual(sessionData);
      expect(mockCache.get).toHaveBeenCalledWith(`session:${sessionId}`);
    });

    it('should return null for missing session', async () => {
      const sessionId = generateValidSessionId();
      vi.mocked(mockCache.get).mockResolvedValueOnce(null);

      const session = new SessionService(mockCache);
      const result = await session.get(sessionId);

      expect(result).toBeNull();
    });

    it('should return null for invalid session ID', async () => {
      const session = new SessionService(mockCache);

      // Too short
      expect(await session.get('abc123')).toBeNull();

      // Invalid characters
      expect(await session.get('g'.repeat(64))).toBeNull();

      // Too long
      expect(await session.get('a'.repeat(65))).toBeNull();

      // Cache should not be called for invalid IDs
      expect(mockCache.get).not.toHaveBeenCalled();
    });

    it('should return null for corrupted JSON', async () => {
      const sessionId = generateValidSessionId();
      vi.mocked(mockCache.get).mockResolvedValueOnce('not-valid-json');

      const session = new SessionService(mockCache);
      const result = await session.get(sessionId);

      expect(result).toBeNull();
    });
  });

  describe('set', () => {
    it('should store session data', async () => {
      const sessionId = generateValidSessionId();
      const sessionData: SessionData = { userId: 123, role: 'user' };

      const session = new SessionService(mockCache);
      const result = await session.set(sessionId, sessionData);

      expect(result).toBe(true);
      expect(mockCache.set).toHaveBeenCalledWith(
        `session:${sessionId}`,
        JSON.stringify(sessionData),
        86400 // default TTL
      );
    });

    it('should use custom TTL', async () => {
      const sessionId = generateValidSessionId();
      const sessionData: SessionData = { data: 'value' };

      const session = new SessionService(mockCache);
      await session.set(sessionId, sessionData, 3600);

      expect(mockCache.set).toHaveBeenCalledWith(`session:${sessionId}`, expect.any(String), 3600);
    });

    it('should track session in user list when userId present', async () => {
      const sessionId = generateValidSessionId();
      const sessionData: SessionData = { userId: 123 };

      // First call: session set, second call: get user sessions, third call: set user sessions list
      vi.mocked(mockCache.get).mockResolvedValueOnce(null); // no existing user sessions

      const session = new SessionService(mockCache);
      await session.set(sessionId, sessionData);

      // Should have called set twice: once for session, once for user sessions list
      expect(mockCache.set).toHaveBeenCalledTimes(2);
      expect(mockCache.set).toHaveBeenCalledWith(`user:123:sessions`, expect.any(String), 86400);
    });

    it('should return false for invalid session ID', async () => {
      const session = new SessionService(mockCache);

      const result = await session.set('invalid', { data: 'value' });

      expect(result).toBe(false);
      expect(mockCache.set).not.toHaveBeenCalled();
    });
  });

  describe('update', () => {
    it('should merge data with existing session', async () => {
      const sessionId = generateValidSessionId();
      const existingData: SessionData = { userId: 123, role: 'user' };
      const updateData = { lastActivity: 1234567890 };

      vi.mocked(mockCache.get).mockResolvedValueOnce(JSON.stringify(existingData));
      vi.mocked(mockCache.ttl).mockResolvedValueOnce(3500);
      vi.mocked(mockCache.get).mockResolvedValueOnce(null); // user sessions check

      const session = new SessionService(mockCache);
      const result = await session.update(sessionId, updateData);

      expect(result).toBe(true);

      // Should preserve existing TTL
      const setCall = vi.mocked(mockCache.set).mock.calls[0];
      expect(setCall?.[2]).toBe(3500);

      // Should merge data
      const storedData = JSON.parse(setCall?.[1] ?? '{}') as SessionData;
      expect(storedData.userId).toBe(123);
      expect(storedData.role).toBe('user');
      expect(storedData.lastActivity).toBe(1234567890);
    });

    it('should return false for non-existent session', async () => {
      const sessionId = generateValidSessionId();
      vi.mocked(mockCache.get).mockResolvedValueOnce(null);

      const session = new SessionService(mockCache);
      const result = await session.update(sessionId, { data: 'value' });

      expect(result).toBe(false);
    });
  });

  describe('destroy', () => {
    it('should remove session', async () => {
      const sessionId = generateValidSessionId();
      vi.mocked(mockCache.get).mockResolvedValueOnce(null); // no session data

      const session = new SessionService(mockCache);
      const result = await session.destroy(sessionId);

      expect(result).toBe(true);
      expect(mockCache.delete).toHaveBeenCalledWith(`session:${sessionId}`);
    });

    it('should remove session from user list when userId present', async () => {
      const sessionId = generateValidSessionId();
      const session2Id = generateValidSessionId();
      const sessionData: SessionData = { userId: 123 };

      vi.mocked(mockCache.get)
        .mockResolvedValueOnce(JSON.stringify(sessionData)) // get session data
        .mockResolvedValueOnce(JSON.stringify([sessionId, session2Id])); // get user sessions

      vi.mocked(mockCache.has).mockResolvedValue(true); // both sessions exist

      const session = new SessionService(mockCache);
      await session.destroy(sessionId);

      // Should update user sessions list without the destroyed session
      expect(mockCache.set).toHaveBeenCalledWith(
        'user:123:sessions',
        JSON.stringify([session2Id]),
        86400
      );
    });

    it('should return false for invalid session ID', async () => {
      const session = new SessionService(mockCache);
      const result = await session.destroy('invalid');

      expect(result).toBe(false);
    });
  });

  describe('regenerate', () => {
    it('should create new ID and copy data', async () => {
      const oldSessionId = generateValidSessionId();
      const sessionData: SessionData = { userId: 123, role: 'admin' };

      vi.mocked(mockCache.get)
        .mockResolvedValueOnce(JSON.stringify(sessionData)) // get old session
        .mockResolvedValueOnce(null) // get user sessions for new session
        .mockResolvedValueOnce(JSON.stringify(sessionData)) // get for destroy
        .mockResolvedValueOnce(JSON.stringify([oldSessionId])); // user sessions for destroy

      vi.mocked(mockCache.ttl).mockResolvedValue(3500);
      vi.mocked(mockCache.has).mockResolvedValue(true);

      const session = new SessionService(mockCache);
      const newSessionId = await session.regenerate(oldSessionId);

      expect(newSessionId).not.toBeNull();
      expect(newSessionId).not.toBe(oldSessionId);
      expect(newSessionId).toHaveLength(64);
      expect(newSessionId).toMatch(/^[a-f0-9]{64}$/);

      // Should preserve TTL
      expect(mockCache.set).toHaveBeenCalledWith(
        `session:${newSessionId}`,
        JSON.stringify(sessionData),
        3500
      );

      // Should delete old session
      expect(mockCache.delete).toHaveBeenCalledWith(`session:${oldSessionId}`);
    });

    it('should return null for non-existent session', async () => {
      const sessionId = generateValidSessionId();
      vi.mocked(mockCache.get).mockResolvedValueOnce(null);

      const session = new SessionService(mockCache);
      const result = await session.regenerate(sessionId);

      expect(result).toBeNull();
    });
  });

  describe('touch', () => {
    it('should extend TTL', async () => {
      const sessionId = generateValidSessionId();
      const sessionData: SessionData = { data: 'value' };

      vi.mocked(mockCache.get).mockResolvedValueOnce(JSON.stringify(sessionData));

      const session = new SessionService(mockCache);
      const result = await session.touch(sessionId);

      expect(result).toBe(true);
      expect(mockCache.set).toHaveBeenCalledWith(
        `session:${sessionId}`,
        JSON.stringify(sessionData),
        86400 // default TTL
      );
    });

    it('should return false for non-existent session', async () => {
      const sessionId = generateValidSessionId();
      vi.mocked(mockCache.get).mockResolvedValueOnce(null);

      const session = new SessionService(mockCache);
      const result = await session.touch(sessionId);

      expect(result).toBe(false);
    });
  });

  describe('getUserSessions', () => {
    it('should return active sessions', async () => {
      const session1 = generateValidSessionId();
      const session2 = generateValidSessionId();

      vi.mocked(mockCache.get).mockResolvedValueOnce(JSON.stringify([session1, session2]));
      vi.mocked(mockCache.has).mockResolvedValue(true);

      const session = new SessionService(mockCache);
      const sessions = await session.getUserSessions(123);

      expect(sessions).toHaveLength(2);
      expect(sessions).toContain(session1);
      expect(sessions).toContain(session2);
    });

    it('should filter expired sessions', async () => {
      const activeSession = generateValidSessionId();
      const expiredSession = generateValidSessionId();

      vi.mocked(mockCache.get).mockResolvedValueOnce(
        JSON.stringify([activeSession, expiredSession])
      );
      vi.mocked(mockCache.has)
        .mockResolvedValueOnce(true) // activeSession exists
        .mockResolvedValueOnce(false); // expiredSession doesn't exist

      const session = new SessionService(mockCache);
      const sessions = await session.getUserSessions(123);

      expect(sessions).toHaveLength(1);
      expect(sessions).toContain(activeSession);
      expect(sessions).not.toContain(expiredSession);

      // Should update the list
      expect(mockCache.set).toHaveBeenCalledWith(
        'user:123:sessions',
        JSON.stringify([activeSession]),
        86400
      );
    });

    it('should return empty array when no sessions', async () => {
      vi.mocked(mockCache.get).mockResolvedValueOnce(null);

      const session = new SessionService(mockCache);
      const sessions = await session.getUserSessions(123);

      expect(sessions).toEqual([]);
    });
  });

  describe('destroyUserSessions', () => {
    it('should remove all sessions for user', async () => {
      const session1 = generateValidSessionId();
      const session2 = generateValidSessionId();

      vi.mocked(mockCache.get).mockResolvedValueOnce(JSON.stringify([session1, session2]));
      vi.mocked(mockCache.has).mockResolvedValue(true);
      vi.mocked(mockCache.delete).mockResolvedValue(true);

      const session = new SessionService(mockCache);
      const destroyed = await session.destroyUserSessions(123);

      expect(destroyed).toBe(2);
      expect(mockCache.delete).toHaveBeenCalledWith(`session:${session1}`);
      expect(mockCache.delete).toHaveBeenCalledWith(`session:${session2}`);
      expect(mockCache.delete).toHaveBeenCalledWith('user:123:sessions');
    });

    it('should return 0 when no sessions', async () => {
      vi.mocked(mockCache.get).mockResolvedValueOnce(null);

      const session = new SessionService(mockCache);
      const destroyed = await session.destroyUserSessions(123);

      expect(destroyed).toBe(0);
    });
  });

  describe('generateId', () => {
    it('should return 64 hex characters', () => {
      const session = new SessionService(mockCache);
      const id = session.generateId();

      expect(id).toHaveLength(64);
      expect(id).toMatch(/^[a-f0-9]{64}$/);
    });

    it('should generate unique IDs', () => {
      const session = new SessionService(mockCache);
      const ids = new Set<string>();

      for (let i = 0; i < 100; i++) {
        ids.add(session.generateId());
      }

      expect(ids.size).toBe(100);
    });
  });

  describe('custom TTL', () => {
    it('should use custom default TTL', async () => {
      const sessionId = generateValidSessionId();
      const customTtl = 3600;

      const session = new SessionService(mockCache, customTtl);
      await session.set(sessionId, { data: 'value' });

      expect(mockCache.set).toHaveBeenCalledWith(`session:${sessionId}`, expect.any(String), 3600);
    });
  });

  describe('interface compliance', () => {
    it('should implement SessionServiceInterface', () => {
      const session: SessionServiceInterface = new SessionService(mockCache);

      // Type check - these should all exist
      expect(typeof session.get).toBe('function');
      expect(typeof session.set).toBe('function');
      expect(typeof session.update).toBe('function');
      expect(typeof session.destroy).toBe('function');
      expect(typeof session.regenerate).toBe('function');
      expect(typeof session.touch).toBe('function');
      expect(typeof session.getUserSessions).toBe('function');
      expect(typeof session.destroyUserSessions).toBe('function');
      expect(typeof session.generateId).toBe('function');
    });
  });
});
