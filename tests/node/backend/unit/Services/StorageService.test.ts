/**
 * Unit tests for StorageService
 *
 * Tests the S3-compatible storage service logic without a real connection.
 * Uses mocked fetch to simulate API responses.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { StorageService } from '@backend/App/Services/StorageService';

// Mock fetch globally
const mockFetch = vi.fn();
global.fetch = mockFetch as typeof fetch;

describe('StorageService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('constructor', () => {
    it('should accept custom parameters', () => {
      const service = new StorageService({
        endpoint: 'https://custom:9000',
        accessKey: 'custom-access',
        secretKey: 'custom-secret',
        region: 'eu-west-1',
        bucket: 'custom-bucket',
      });

      expect(service.getEndpoint()).toBe('https://custom:9000');
    });

    it('should use defaults when not provided', () => {
      const service = new StorageService();
      expect(service.getEndpoint()).toContain('seaweedfs');
    });

    it('should strip trailing slash from endpoint', () => {
      const service = new StorageService({ endpoint: 'http://localhost:8333/' });
      expect(service.getEndpoint()).toBe('http://localhost:8333');
    });
  });

  describe('connect/disconnect', () => {
    it('should mark as connected when bucket exists', async () => {
      mockFetch.mockResolvedValueOnce({ ok: true });

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      await service.connect();

      expect(service.isConnected()).toBe(true);
    });

    it('should mark as not connected when bucket check fails', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Connection refused'));

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      await service.connect();

      expect(service.isConnected()).toBe(false);
    });

    it('should disconnect successfully', async () => {
      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      await service.disconnect();

      expect(service.isConnected()).toBe(false);
    });
  });

  describe('getPublicUrl', () => {
    it('should return path-style URL', () => {
      const service = new StorageService({
        endpoint: 'http://seaweedfs:8333',
        bucket: 'mybucket',
      });

      const url = service.getPublicUrl('avatars/user-123.jpg');

      expect(url).toBe('http://seaweedfs:8333/mybucket/avatars/user-123.jpg');
    });

    it('should strip leading slash from key', () => {
      const service = new StorageService({
        endpoint: 'http://seaweedfs:8333',
        bucket: 'mybucket',
      });

      const url = service.getPublicUrl('/avatars/user-123.jpg');

      expect(url).toBe('http://seaweedfs:8333/mybucket/avatars/user-123.jpg');
    });
  });

  describe('upload', () => {
    it('should return URL on success', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        arrayBuffer: () => Promise.resolve(new ArrayBuffer(0)),
      });

      const service = new StorageService({
        endpoint: 'http://localhost:8333',
        bucket: 'mybucket',
      });
      const result = await service.upload('test.txt', 'Hello World', 'text/plain');

      expect(result).toBe('http://localhost:8333/mybucket/test.txt');
    });

    it('should return null on error', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Network error'));

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.upload('test.txt', 'Hello', 'text/plain');

      expect(result).toBeNull();
    });

    it('should handle Buffer content', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        arrayBuffer: () => Promise.resolve(new ArrayBuffer(0)),
      });

      const service = new StorageService({
        endpoint: 'http://localhost:8333',
        bucket: 'mybucket',
      });
      const result = await service.upload(
        'test.bin',
        Buffer.from([1, 2, 3]),
        'application/octet-stream'
      );

      expect(result).not.toBeNull();
    });
  });

  describe('download', () => {
    it('should return buffer on success', async () => {
      const testData = new Uint8Array([72, 101, 108, 108, 111]); // "Hello"

      mockFetch.mockResolvedValueOnce({
        ok: true,
        arrayBuffer: () => Promise.resolve(testData.buffer),
      });

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.download('test.txt');

      expect(result).not.toBeNull();
      expect(result?.toString()).toBe('Hello');
    });

    it('should return null on error', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Network error'));

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.download('test.txt');

      expect(result).toBeNull();
    });
  });

  describe('exists', () => {
    it('should return true when object exists', async () => {
      mockFetch.mockResolvedValueOnce({ ok: true });

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.exists('test.txt');

      expect(result).toBe(true);
    });

    it('should return false when object does not exist', async () => {
      mockFetch.mockResolvedValueOnce({ ok: false });

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.exists('non-existent.txt');

      expect(result).toBe(false);
    });
  });

  describe('delete', () => {
    it('should return true on successful deletion', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 204,
        arrayBuffer: () => Promise.resolve(new ArrayBuffer(0)),
      });

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.delete('test.txt');

      expect(result).toBe(true);
    });

    it('should return false on error', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Network error'));

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.delete('test.txt');

      expect(result).toBe(false);
    });
  });

  describe('list', () => {
    it('should return list of objects', async () => {
      const xmlResponse = `<?xml version="1.0" encoding="UTF-8"?>
        <ListBucketResult>
          <Contents>
            <Key>file1.txt</Key>
            <Size>100</Size>
            <LastModified>2024-01-01T00:00:00.000Z</LastModified>
          </Contents>
          <Contents>
            <Key>file2.txt</Key>
            <Size>200</Size>
            <LastModified>2024-01-02T00:00:00.000Z</LastModified>
          </Contents>
        </ListBucketResult>`;

      mockFetch.mockResolvedValueOnce({
        ok: true,
        arrayBuffer: () => Promise.resolve(Buffer.from(xmlResponse).buffer),
      });

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.list();

      expect(result).toHaveLength(2);
      expect(result[0]!.key).toBe('file1.txt');
      expect(result[0]!.size).toBe(100);
      expect(result[1]!.key).toBe('file2.txt');
    });

    it('should return empty array on error', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Network error'));

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.list();

      expect(result).toEqual([]);
    });
  });

  describe('copy', () => {
    it('should return true on success', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        arrayBuffer: () => Promise.resolve(new ArrayBuffer(0)),
      });

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.copy('source.txt', 'dest.txt');

      expect(result).toBe(true);
    });

    it('should include x-amz-copy-source header', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        arrayBuffer: () => Promise.resolve(new ArrayBuffer(0)),
      });

      const service = new StorageService({
        endpoint: 'http://localhost:8333',
        bucket: 'mybucket',
      });
      await service.copy('source.txt', 'dest.txt');

      const calls = mockFetch.mock.calls as Array<[string, RequestInit]>;
      const [url, options] = calls[0] as [string, RequestInit];

      expect(typeof url).toBe('string');
      expect(url).toContain('dest.txt');
      expect(options.method).toBe('PUT');

      const headers = options.headers as Record<string, string>;
      expect(headers['x-amz-copy-source']).toBe('/mybucket/source.txt');
    });
  });

  describe('move', () => {
    it('should copy and delete on success', async () => {
      // Mock copy
      mockFetch.mockResolvedValueOnce({
        ok: true,
        arrayBuffer: () => Promise.resolve(new ArrayBuffer(0)),
      });
      // Mock delete
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 204,
        arrayBuffer: () => Promise.resolve(new ArrayBuffer(0)),
      });

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.move('source.txt', 'dest.txt');

      expect(result).toBe(true);
      expect(mockFetch).toHaveBeenCalledTimes(2);
    });

    it('should return false if copy fails', async () => {
      mockFetch.mockResolvedValueOnce({ ok: false });

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.move('source.txt', 'dest.txt');

      expect(result).toBe(false);
    });
  });

  describe('getMetadata', () => {
    it('should return metadata on success', async () => {
      const headers = new Map([
        ['content-length', '1024'],
        ['content-type', 'image/jpeg'],
        ['last-modified', 'Mon, 01 Jan 2024 00:00:00 GMT'],
        ['x-amz-meta-custom', 'value'],
      ]);

      mockFetch.mockResolvedValueOnce({
        ok: true,
        headers: {
          get: (name: string) => headers.get(name.toLowerCase()) ?? null,
          forEach: (cb: (value: string, name: string) => void) =>
            headers.forEach((v, k) => cb(v, k)),
        },
      });

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.getMetadata('image.jpg');

      expect(result).not.toBeNull();
      expect(result!.size).toBe(1024);
      expect(result!.contentType).toBe('image/jpeg');
      expect(result!.metadata['custom']).toBe('value');
    });

    it('should return null on error', async () => {
      mockFetch.mockResolvedValueOnce({ ok: false });

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.getMetadata('non-existent.txt');

      expect(result).toBeNull();
    });
  });

  describe('getPresignedUrl', () => {
    it('should generate valid pre-signed URL', async () => {
      const service = new StorageService({
        endpoint: 'http://seaweedfs:8333',
        accessKey: 'test-access-key-id-12345',
        secretKey: 'test-secret-access-key-67890abcdef',
        region: 'us-east-1',
        bucket: 'mybucket',
      });

      const url = await service.getPresignedUrl('test.txt', 3600);

      expect(url).not.toBeNull();
      expect(url).toContain('X-Amz-Algorithm=AWS4-HMAC-SHA256');
      expect(url).toContain('X-Amz-Credential=');
      expect(url).toContain('X-Amz-Signature=');
      expect(url).toContain('X-Amz-Expires=3600');
    });
  });

  describe('createBucket', () => {
    it('should return true on success', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        arrayBuffer: () => Promise.resolve(new ArrayBuffer(0)),
      });

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.createBucket('new-bucket');

      expect(result).toBe(true);
    });

    it('should return false on error', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Bucket creation failed'));

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.createBucket('new-bucket');

      expect(result).toBe(false);
    });
  });

  describe('bucketExists', () => {
    it('should return true when bucket exists', async () => {
      mockFetch.mockResolvedValueOnce({ ok: true });

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.bucketExists('existing-bucket');

      expect(result).toBe(true);
    });

    it('should return false when bucket does not exist', async () => {
      mockFetch.mockResolvedValueOnce({ ok: false });

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.bucketExists('non-existent');

      expect(result).toBe(false);
    });
  });

  describe('isAvailable', () => {
    it('should return true when bucket is accessible', async () => {
      mockFetch.mockResolvedValueOnce({ ok: true });

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.isAvailable();

      expect(result).toBe(true);
    });

    it('should return false when bucket is not accessible', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Connection refused'));

      const service = new StorageService({ endpoint: 'http://localhost:8333' });
      const result = await service.isAvailable();

      expect(result).toBe(false);
    });
  });
});
