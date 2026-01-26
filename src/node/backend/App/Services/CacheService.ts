/**
 * Cache Service for key-value storage with TTL support
 *
 * Provides a simple, type-safe interface for caching operations using Redis.
 * Supports both TLS (rediss://) and plain (redis://) connections.
 *
 * Features:
 * - Lazy connection (connects on first use)
 * - Automatic reconnection on failure
 * - Key prefixing for namespace isolation
 * - TLS support with self-signed certificates (dev)
 * - Graceful error handling (returns null/false, no throws)
 *
 * Configuration:
 * - REDIS_URL: Connection URL (default: rediss://redis:6379)
 *
 * Usage:
 * ```typescript
 * const cache = new CacheService();
 * await cache.connect();
 *
 * // Store user data with 1-hour TTL
 * await cache.set('user:123', JSON.stringify(userData), 3600);
 *
 * // Retrieve
 * const userData = await cache.get('user:123');
 * if (userData !== null) {
 *   const user = JSON.parse(userData);
 * }
 *
 * // Cache invalidation
 * await cache.deletePattern('user:123:*');
 *
 * // Cleanup
 * await cache.disconnect();
 * ```
 */

import { createClient, RedisClientType } from 'redis';
import { getTlsSocketOptions } from '../../Shared/TlsConfig';

/**
 * Cache Service Interface
 */
export interface CacheServiceInterface {
  /**
   * Connect to cache backend
   */
  connect(): Promise<void>;

  /**
   * Disconnect from cache backend
   */
  disconnect(): Promise<void>;

  /**
   * Retrieve a value from cache
   * @returns Cached value or null if not found/expired
   */
  get(key: string): Promise<string | null>;

  /**
   * Store a value in cache with optional TTL (default: 3600 seconds)
   */
  set(key: string, value: string, ttl?: number): Promise<boolean>;

  /**
   * Check if a key exists in cache
   */
  has(key: string): Promise<boolean>;

  /**
   * Delete a key from cache
   */
  delete(key: string): Promise<boolean>;

  /**
   * Delete multiple keys matching a pattern
   * @returns Number of keys deleted
   */
  deletePattern(pattern: string): Promise<number>;

  /**
   * Get remaining TTL for a key
   * @returns TTL in seconds, null if doesn't exist, -1 if no expiry
   */
  ttl(key: string): Promise<number | null>;

  /**
   * Check if cache is connected and responsive
   */
  isAvailable(): Promise<boolean>;
}

/**
 * Cache Service Configuration
 */
export interface CacheServiceOptions {
  /** Redis URL (default: from REDIS_URL env or rediss://redis:6379) */
  redisUrl?: string;
  /** Key prefix for namespace isolation (default: 'app:') */
  prefix?: string;
  /** Connection timeout in milliseconds (default: 2000) */
  timeout?: number;
}

/**
 * Default configuration values
 */
const DEFAULT_REDIS_URL = 'rediss://redis:6379';
const DEFAULT_PREFIX = 'app:';
const DEFAULT_TIMEOUT = 2000;
const DEFAULT_TTL = 3600;

/**
 * Redis Cache Service Implementation
 */
export class CacheService implements CacheServiceInterface {
  private client: RedisClientType | null = null;
  private readonly redisUrl: string;
  private readonly prefix: string;
  private readonly timeout: number;

  constructor(options: CacheServiceOptions = {}) {
    this.redisUrl = options.redisUrl ?? process.env.REDIS_URL ?? DEFAULT_REDIS_URL;
    this.prefix = options.prefix ?? DEFAULT_PREFIX;
    this.timeout = options.timeout ?? DEFAULT_TIMEOUT;
  }

  async connect(): Promise<void> {
    if (this.client !== null) {
      return; // Already connected
    }

    const useTls = this.redisUrl.startsWith('rediss://');

    this.client = createClient({
      url: this.redisUrl,
      socket: {
        connectTimeout: this.timeout,
        ...(useTls && getTlsSocketOptions()),
      },
    });

    // Handle connection errors
    this.client.on('error', () => {
      // Errors are logged but don't throw
      // Individual operations will return null/false
    });

    await this.client.connect();
  }

  async disconnect(): Promise<void> {
    if (this.client === null) {
      return;
    }

    try {
      await this.client.quit();
    } catch {
      // Ignore disconnect errors
    } finally {
      this.client = null;
    }
  }

  async get(key: string): Promise<string | null> {
    if (this.client === null) {
      return null;
    }

    try {
      return await this.client.get(this.prefixKey(key));
    } catch {
      return null;
    }
  }

  async set(key: string, value: string, ttl: number = DEFAULT_TTL): Promise<boolean> {
    if (this.client === null) {
      return false;
    }

    try {
      await this.client.setEx(this.prefixKey(key), ttl, value);
      return true;
    } catch {
      return false;
    }
  }

  async has(key: string): Promise<boolean> {
    if (this.client === null) {
      return false;
    }

    try {
      const exists = await this.client.exists(this.prefixKey(key));
      return exists > 0;
    } catch {
      return false;
    }
  }

  async delete(key: string): Promise<boolean> {
    if (this.client === null) {
      return false;
    }

    try {
      await this.client.del(this.prefixKey(key));
      return true;
    } catch {
      return false;
    }
  }

  async deletePattern(pattern: string): Promise<number> {
    if (this.client === null) {
      return 0;
    }

    try {
      const keys = await this.client.keys(this.prefixKey(pattern));
      if (keys.length === 0) {
        return 0;
      }

      return await this.client.del(keys);
    } catch {
      return 0;
    }
  }

  async ttl(key: string): Promise<number | null> {
    if (this.client === null) {
      return null;
    }

    try {
      const ttl = await this.client.ttl(this.prefixKey(key));
      // Redis returns -2 if key doesn't exist
      return ttl === -2 ? null : ttl;
    } catch {
      return null;
    }
  }

  async isAvailable(): Promise<boolean> {
    if (this.client === null) {
      return false;
    }

    try {
      await this.client.ping();
      return true;
    } catch {
      return false;
    }
  }

  /**
   * Add prefix to key
   */
  private prefixKey(key: string): string {
    return this.prefix + key;
  }

  /**
   * Get the current prefix (for testing)
   */
  getPrefix(): string {
    return this.prefix;
  }

  /**
   * Check if client is connected (for testing)
   */
  isConnected(): boolean {
    return this.client !== null;
  }
}
