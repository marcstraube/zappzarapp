/**
 * Tests for CacheService
 */

import { describe, it, expect, vi, beforeEach, afterEach, type Mock } from 'vitest';
import { CacheService, type CacheServiceInterface } from '@backend/App/Services/CacheService';

// Mock Redis client interface
interface MockRedisClient {
  connect: Mock;
  quit: Mock;
  get: Mock;
  setEx: Mock;
  exists: Mock;
  del: Mock;
  keys: Mock;
  ttl: Mock;
  ping: Mock;
  on: Mock;
}

// Create mock Redis client factory
function createMockRedisClient(): MockRedisClient {
  return {
    connect: vi.fn().mockResolvedValue(undefined),
    quit: vi.fn().mockResolvedValue(undefined),
    get: vi.fn().mockResolvedValue(null),
    setEx: vi.fn().mockResolvedValue('OK'),
    exists: vi.fn().mockResolvedValue(0),
    del: vi.fn().mockResolvedValue(0),
    keys: vi.fn().mockResolvedValue([]),
    ttl: vi.fn().mockResolvedValue(-2),
    ping: vi.fn().mockResolvedValue('PONG'),
    on: vi.fn(),
  };
}

// Store mock for assertions
let mockRedisClient: MockRedisClient;

// Mock the redis module
vi.mock('redis', () => ({
  createClient: vi.fn(() => {
    mockRedisClient = createMockRedisClient();
    return mockRedisClient;
  }),
}));

describe('CacheService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  describe('constructor', () => {
    it('should use default values when no options provided', () => {
      const cache = new CacheService();

      expect(cache.getPrefix()).toBe('app:');
      expect(cache.isConnected()).toBe(false);
    });

    it('should accept custom options', () => {
      const cache = new CacheService({
        redisUrl: 'redis://custom:1234',
        prefix: 'custom:',
        timeout: 5000,
      });

      expect(cache.getPrefix()).toBe('custom:');
    });

    it('should use REDIS_URL from environment', () => {
      const originalEnv = process.env.REDIS_URL;
      process.env.REDIS_URL = 'redis://env:6380';

      const cache = new CacheService();
      // The URL is used internally, we just verify it doesn't throw
      expect(cache).toBeInstanceOf(CacheService);

      process.env.REDIS_URL = originalEnv;
    });
  });

  describe('connect', () => {
    it('should connect to Redis', async () => {
      const cache = new CacheService();

      await cache.connect();

      expect(mockRedisClient.connect).toHaveBeenCalled();
      expect(cache.isConnected()).toBe(true);
    });

    it('should not reconnect if already connected', async () => {
      const cache = new CacheService();

      await cache.connect();
      await cache.connect();

      expect(mockRedisClient.connect).toHaveBeenCalledTimes(1);
    });

    it('should detect TLS from rediss:// URL', async () => {
      const cache = new CacheService({ redisUrl: 'rediss://secure:6379' });

      await cache.connect();

      // Verify connect was called (TLS config is internal)
      expect(mockRedisClient.connect).toHaveBeenCalled();
    });
  });

  describe('disconnect', () => {
    it('should disconnect from Redis', async () => {
      const cache = new CacheService();
      await cache.connect();

      await cache.disconnect();

      expect(mockRedisClient.quit).toHaveBeenCalled();
      expect(cache.isConnected()).toBe(false);
    });

    it('should handle disconnect when not connected', async () => {
      const cache = new CacheService();

      // Should not throw
      await expect(cache.disconnect()).resolves.toBeUndefined();
    });

    it('should ignore disconnect errors', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.quit.mockRejectedValueOnce(new Error('Disconnect failed'));

      // Should not throw
      await expect(cache.disconnect()).resolves.toBeUndefined();
    });
  });

  describe('get', () => {
    it('should return cached value', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.get.mockResolvedValueOnce('myvalue');

      const result = await cache.get('mykey');

      expect(result).toBe('myvalue');
      expect(mockRedisClient.get).toHaveBeenCalledWith('app:mykey');
    });

    it('should return null for missing key', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.get.mockResolvedValueOnce(null);

      const result = await cache.get('missing');

      expect(result).toBeNull();
    });

    it('should return null when not connected', async () => {
      const cache = new CacheService();

      const result = await cache.get('anykey');

      expect(result).toBeNull();
    });

    it('should return null on Redis error', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.get.mockRejectedValueOnce(new Error('Connection lost'));

      const result = await cache.get('anykey');

      expect(result).toBeNull();
    });

    it('should apply key prefix', async () => {
      const cache = new CacheService({ prefix: 'custom:' });
      await cache.connect();

      await cache.get('mykey');

      expect(mockRedisClient.get).toHaveBeenCalledWith('custom:mykey');
    });
  });

  describe('set', () => {
    it('should store value with TTL', async () => {
      const cache = new CacheService();
      await cache.connect();

      const result = await cache.set('mykey', 'myvalue', 7200);

      expect(result).toBe(true);
      expect(mockRedisClient.setEx).toHaveBeenCalledWith('app:mykey', 7200, 'myvalue');
    });

    it('should use default TTL when not specified', async () => {
      const cache = new CacheService();
      await cache.connect();

      await cache.set('mykey', 'myvalue');

      expect(mockRedisClient.setEx).toHaveBeenCalledWith('app:mykey', 3600, 'myvalue');
    });

    it('should return false when not connected', async () => {
      const cache = new CacheService();

      const result = await cache.set('mykey', 'myvalue');

      expect(result).toBe(false);
    });

    it('should return false on Redis error', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.setEx.mockRejectedValueOnce(new Error('Connection lost'));

      const result = await cache.set('mykey', 'myvalue');

      expect(result).toBe(false);
    });
  });

  describe('has', () => {
    it('should return true when key exists', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.exists.mockResolvedValueOnce(1);

      const result = await cache.has('mykey');

      expect(result).toBe(true);
      expect(mockRedisClient.exists).toHaveBeenCalledWith('app:mykey');
    });

    it('should return false when key does not exist', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.exists.mockResolvedValueOnce(0);

      const result = await cache.has('missing');

      expect(result).toBe(false);
    });

    it('should return false when not connected', async () => {
      const cache = new CacheService();

      const result = await cache.has('anykey');

      expect(result).toBe(false);
    });

    it('should return false on Redis error', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.exists.mockRejectedValueOnce(new Error('Connection lost'));

      const result = await cache.has('anykey');

      expect(result).toBe(false);
    });
  });

  describe('delete', () => {
    it('should delete key', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.del.mockResolvedValueOnce(1);

      const result = await cache.delete('mykey');

      expect(result).toBe(true);
      expect(mockRedisClient.del).toHaveBeenCalledWith('app:mykey');
    });

    it('should return true even if key did not exist', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.del.mockResolvedValueOnce(0);

      const result = await cache.delete('missing');

      expect(result).toBe(true);
    });

    it('should return false when not connected', async () => {
      const cache = new CacheService();

      const result = await cache.delete('anykey');

      expect(result).toBe(false);
    });

    it('should return false on Redis error', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.del.mockRejectedValueOnce(new Error('Connection lost'));

      const result = await cache.delete('anykey');

      expect(result).toBe(false);
    });
  });

  describe('deletePattern', () => {
    it('should delete matching keys', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.keys.mockResolvedValueOnce(['app:user:123:a', 'app:user:123:b']);
      mockRedisClient.del.mockResolvedValueOnce(2);

      const result = await cache.deletePattern('user:123:*');

      expect(result).toBe(2);
      expect(mockRedisClient.keys).toHaveBeenCalledWith('app:user:123:*');
      expect(mockRedisClient.del).toHaveBeenCalledWith(['app:user:123:a', 'app:user:123:b']);
    });

    it('should return 0 when no keys match', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.keys.mockResolvedValueOnce([]);

      const result = await cache.deletePattern('nonexistent:*');

      expect(result).toBe(0);
      expect(mockRedisClient.del).not.toHaveBeenCalled();
    });

    it('should return 0 when not connected', async () => {
      const cache = new CacheService();

      const result = await cache.deletePattern('any:*');

      expect(result).toBe(0);
    });

    it('should return 0 on Redis error', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.keys.mockRejectedValueOnce(new Error('Connection lost'));

      const result = await cache.deletePattern('any:*');

      expect(result).toBe(0);
    });
  });

  describe('ttl', () => {
    it('should return remaining TTL', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.ttl.mockResolvedValueOnce(3500);

      const result = await cache.ttl('mykey');

      expect(result).toBe(3500);
      expect(mockRedisClient.ttl).toHaveBeenCalledWith('app:mykey');
    });

    it('should return null when key does not exist', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.ttl.mockResolvedValueOnce(-2);

      const result = await cache.ttl('missing');

      expect(result).toBeNull();
    });

    it('should return -1 for key without expiry', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.ttl.mockResolvedValueOnce(-1);

      const result = await cache.ttl('persistent');

      expect(result).toBe(-1);
    });

    it('should return null when not connected', async () => {
      const cache = new CacheService();

      const result = await cache.ttl('anykey');

      expect(result).toBeNull();
    });

    it('should return null on Redis error', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.ttl.mockRejectedValueOnce(new Error('Connection lost'));

      const result = await cache.ttl('anykey');

      expect(result).toBeNull();
    });
  });

  describe('isAvailable', () => {
    it('should return true when ping succeeds', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.ping.mockResolvedValueOnce('PONG');

      const result = await cache.isAvailable();

      expect(result).toBe(true);
    });

    it('should return false when not connected', async () => {
      const cache = new CacheService();

      const result = await cache.isAvailable();

      expect(result).toBe(false);
    });

    it('should return false on ping error', async () => {
      const cache = new CacheService();
      await cache.connect();
      mockRedisClient.ping.mockRejectedValueOnce(new Error('Connection lost'));

      const result = await cache.isAvailable();

      expect(result).toBe(false);
    });
  });

  describe('interface compliance', () => {
    it('should implement CacheServiceInterface', () => {
      const cache: CacheServiceInterface = new CacheService();

      // Type check - these should all exist
      expect(typeof cache.connect).toBe('function');
      expect(typeof cache.disconnect).toBe('function');
      expect(typeof cache.get).toBe('function');
      expect(typeof cache.set).toBe('function');
      expect(typeof cache.has).toBe('function');
      expect(typeof cache.delete).toBe('function');
      expect(typeof cache.deletePattern).toBe('function');
      expect(typeof cache.ttl).toBe('function');
      expect(typeof cache.isAvailable).toBe('function');
    });
  });
});
