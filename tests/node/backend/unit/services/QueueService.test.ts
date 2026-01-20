/**
 * Tests for QueueService
 *
 * Note: These tests verify the QueueService's behavior without requiring
 * a real RabbitMQ connection. The core logic is tested through URL building,
 * interface compliance, and state management.
 */

import { describe, it, expect } from 'vitest';
import { QueueService, type QueueServiceInterface } from '@backend/services/QueueService';

describe('QueueService', () => {
  describe('constructor', () => {
    it('should use default values when no options provided', () => {
      const queue = new QueueService();

      expect(queue.isConnected()).toBe(false);
      expect(queue.getUrl()).toContain('rabbitmq');
      expect(queue.getUrl()).toContain('5672');
    });

    it('should accept custom URL with credentials', () => {
      const queue = new QueueService({ url: 'amqp://user:pass@custom:5672/vhost' });

      expect(queue.getUrl()).toContain('custom');
      expect(queue.getUrl()).toContain('user');
      expect(queue.getUrl()).toContain('pass');
    });

    it('should accept custom URL without credentials', () => {
      // URL without credentials should have credentials applied from env/secrets
      const queue = new QueueService({ url: 'amqp://customhost:5673' });

      expect(queue.getUrl()).toContain('customhost');
      expect(queue.getUrl()).toContain('5673');
    });

    it('should build URL from environment variables', () => {
      const originalHost = process.env.RABBITMQ_HOST;
      const originalPort = process.env.RABBITMQ_PORT;
      const originalVhost = process.env.RABBITMQ_VHOST;

      process.env.RABBITMQ_HOST = 'envhost';
      process.env.RABBITMQ_PORT = '5673';
      process.env.RABBITMQ_VHOST = 'testvhost';

      const queue = new QueueService();

      expect(queue.getUrl()).toContain('envhost');
      expect(queue.getUrl()).toContain('5673');
      expect(queue.getUrl()).toContain('testvhost');

      // Restore
      process.env.RABBITMQ_HOST = originalHost;
      process.env.RABBITMQ_PORT = originalPort;
      process.env.RABBITMQ_VHOST = originalVhost;
    });

    it('should use RABBITMQ_URL environment variable when set', () => {
      const originalUrl = process.env.RABBITMQ_URL;

      process.env.RABBITMQ_URL = 'amqp://envuser:envpass@envurl:5674/envvhost';

      const queue = new QueueService();

      expect(queue.getUrl()).toContain('envurl');
      expect(queue.getUrl()).toContain('5674');

      process.env.RABBITMQ_URL = originalUrl;
    });
  });

  describe('state management', () => {
    it('should report not connected initially', () => {
      const queue = new QueueService();

      expect(queue.isConnected()).toBe(false);
      expect(queue.isAvailable()).toBe(false);
    });

    it('should return false/null for operations when not connected', async () => {
      const queue = new QueueService();

      expect(await queue.publish('test', 'message')).toBe(false);
      expect(await queue.get('test')).toBeNull();
      expect(await queue.ack(1)).toBe(false);
      expect(await queue.nack(1)).toBe(false);
      expect(await queue.declareQueue('test')).toBe(false);
      expect(await queue.getMessageCount('test')).toBeNull();
      expect(await queue.purgeQueue('test')).toBe(0);
    });

    it('should handle disconnect when not connected', async () => {
      const queue = new QueueService();

      // Should not throw
      await expect(queue.disconnect()).resolves.toBeUndefined();
    });
  });

  describe('URL parsing', () => {
    it('should handle amqp:// URLs correctly', () => {
      const queue = new QueueService({ url: 'amqp://user:pass@host:5672/vhost' });
      const url = queue.getUrl();

      expect(url).toContain('amqp://');
      expect(url).toContain('host');
      expect(url).toContain('5672');
    });

    it('should handle amqps:// URLs for TLS', () => {
      const queue = new QueueService({ url: 'amqps://user:pass@host:5671/vhost' });
      const url = queue.getUrl();

      expect(url).toContain('amqps://');
      expect(url).toContain('5671');
    });

    it('should handle URL-encoded credentials', () => {
      // Special characters in credentials need URL encoding
      const queue = new QueueService({ url: 'amqp://user%40domain:pass%2Fword@host:5672/' });
      const url = queue.getUrl();

      // URL should preserve the encoded characters
      expect(url).toContain('user%40domain');
      expect(url).toContain('pass%2Fword');
    });

    it('should handle minimal URL without port', () => {
      const queue = new QueueService({ url: 'amqp://user:pass@host/' });
      // Should work without explicit port
      expect(queue.getUrl()).toContain('host');
    });
  });

  describe('interface compliance', () => {
    it('should implement QueueServiceInterface', () => {
      const queue: QueueServiceInterface = new QueueService();

      // Type check - these should all exist and be functions
      expect(typeof queue.connect).toBe('function');
      expect(typeof queue.disconnect).toBe('function');
      expect(typeof queue.publish).toBe('function');
      expect(typeof queue.consume).toBe('function');
      expect(typeof queue.get).toBe('function');
      expect(typeof queue.ack).toBe('function');
      expect(typeof queue.nack).toBe('function');
      expect(typeof queue.declareQueue).toBe('function');
      expect(typeof queue.getMessageCount).toBe('function');
      expect(typeof queue.purgeQueue).toBe('function');
      expect(typeof queue.isAvailable).toBe('function');
    });
  });

  describe('helper methods', () => {
    it('should expose isConnected for testing', () => {
      const queue = new QueueService();
      expect(typeof queue.isConnected).toBe('function');
      expect(queue.isConnected()).toBe(false);
    });

    it('should expose getUrl for testing', () => {
      const queue = new QueueService({ url: 'amqp://test:pass@testhost:5672/' });
      expect(typeof queue.getUrl).toBe('function');
      expect(queue.getUrl()).toContain('testhost');
    });
  });
});
