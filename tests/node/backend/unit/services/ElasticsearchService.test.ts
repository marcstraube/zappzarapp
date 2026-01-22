/**
 * Unit tests for ElasticsearchService
 *
 * Tests the Elasticsearch service logic without a real connection.
 * Uses mocked fetch to simulate API responses.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { ElasticsearchService } from '@backend/services/ElasticsearchService';

// Mock fetch globally
const mockFetch = vi.fn();
global.fetch = mockFetch as typeof fetch;

describe('ElasticsearchService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('constructor', () => {
    it('should accept custom URL', () => {
      const service = new ElasticsearchService({ url: 'https://custom:9200' });
      expect(service.getUrl()).toBe('https://custom:9200');
    });

    it('should use default URL when not provided', () => {
      const service = new ElasticsearchService();
      expect(service.getUrl()).toContain('elasticsearch');
    });

    it('should strip trailing slash from URL', () => {
      const service = new ElasticsearchService({ url: 'https://localhost:9200/' });
      expect(service.getUrl()).toBe('https://localhost:9200');
    });
  });

  describe('connect/disconnect', () => {
    it('should mark as connected when health check passes', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify({ status: 'green' })),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      await service.connect();

      expect(service.isConnected()).toBe(true);
    });

    it('should mark as not connected when health check fails', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Connection refused'));

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      await service.connect();

      expect(service.isConnected()).toBe(false);
    });

    it('should disconnect successfully', async () => {
      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      await service.disconnect();

      expect(service.isConnected()).toBe(false);
    });
  });

  describe('search', () => {
    it('should return search results on success', async () => {
      const mockResponse = {
        hits: {
          total: { value: 2, relation: 'eq' },
          hits: [
            { _id: '1', _source: { name: 'Test 1' } },
            { _id: '2', _source: { name: 'Test 2' } },
          ],
        },
        took: 5,
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify(mockResponse)),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.search('test-index', { match_all: {} });

      expect(result).not.toBeNull();
      expect(result?.hits.total.value).toBe(2);
      expect(result?.hits.hits).toHaveLength(2);
      expect(result?.took).toBe(5);
    });

    it('should return null on error', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Network error'));

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.search('test-index', { match_all: {} });

      expect(result).toBeNull();
    });

    it('should pass search options correctly', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () =>
          Promise.resolve(JSON.stringify({ hits: { total: { value: 0 }, hits: [] }, took: 1 })),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      await service.search('test-index', { match_all: {} }, { size: 10, from: 5 });

      expect(mockFetch).toHaveBeenCalledWith(
        expect.stringContaining('/_search'),
        expect.objectContaining({
          method: 'GET',
          // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
          body: expect.stringContaining('"size":10'),
        })
      );
    });
  });

  describe('get', () => {
    it('should return document on success', async () => {
      const mockResponse = {
        found: true,
        _source: { name: 'Test Document' },
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify(mockResponse)),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.get('test-index', 'doc-1');

      expect(result).toEqual({ name: 'Test Document' });
    });

    it('should return null when document not found', async () => {
      const mockResponse = { found: false };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify(mockResponse)),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.get('test-index', 'non-existent');

      expect(result).toBeNull();
    });
  });

  describe('index', () => {
    it('should return document ID on success', async () => {
      const mockResponse = { _id: 'new-doc-id' };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify(mockResponse)),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.index('test-index', null, { name: 'New Document' });

      expect(result).toBe('new-doc-id');
    });

    it('should use PUT for specified ID', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify({ _id: 'specified-id' })),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      await service.index('test-index', 'specified-id', { name: 'Document' });

      expect(mockFetch).toHaveBeenCalledWith(
        expect.stringContaining('/test-index/_doc/specified-id'),
        expect.objectContaining({ method: 'PUT' })
      );
    });

    it('should return null on error', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Network error'));

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.index('test-index', null, { name: 'Document' });

      expect(result).toBeNull();
    });
  });

  describe('bulkIndex', () => {
    it('should return indexed count on success', async () => {
      const mockResponse = {
        items: [{ index: { status: 201 } }, { index: { status: 200 } }],
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify(mockResponse)),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.bulkIndex('test-index', [
        { id: '1', name: 'Doc 1' },
        { id: '2', name: 'Doc 2' },
      ]);

      expect(result.indexed).toBe(2);
      expect(result.errors).toBe(0);
    });

    it('should count errors correctly', async () => {
      const mockResponse = {
        items: [{ index: { status: 201 } }, { index: { status: 500 } }],
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify(mockResponse)),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.bulkIndex('test-index', [
        { id: '1', name: 'Doc 1' },
        { id: '2', name: 'Doc 2' },
      ]);

      expect(result.indexed).toBe(1);
      expect(result.errors).toBe(1);
    });

    it('should return zero for empty documents', async () => {
      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.bulkIndex('test-index', []);

      expect(result).toEqual({ indexed: 0, errors: 0 });
    });
  });

  describe('delete', () => {
    it('should return true on successful deletion', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify({ result: 'deleted' })),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.delete('test-index', 'doc-1');

      expect(result).toBe(true);
    });

    it('should return false when not deleted', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify({ result: 'not_found' })),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.delete('test-index', 'doc-1');

      expect(result).toBe(false);
    });
  });

  describe('aggregate', () => {
    it('should return aggregation results', async () => {
      const mockResponse = {
        aggregations: {
          avg_price: { value: 99.5 },
        },
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify(mockResponse)),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.aggregate('test-index', {
        avg_price: { avg: { field: 'price' } },
      });

      expect(result).toEqual({ avg_price: { value: 99.5 } });
    });
  });

  describe('createIndex', () => {
    it('should return true on success', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify({ acknowledged: true })),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.createIndex('new-index');

      expect(result).toBe(true);
    });

    it('should pass mappings and settings', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify({ acknowledged: true })),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      await service.createIndex(
        'new-index',
        { properties: { name: { type: 'text' } } },
        { number_of_shards: 1 }
      );

      expect(mockFetch).toHaveBeenCalledWith(
        expect.stringContaining('/new-index'),
        expect.objectContaining({
          // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
          body: expect.stringContaining('"mappings"'),
        })
      );
    });
  });

  describe('deleteIndex', () => {
    it('should return true on success', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify({ acknowledged: true })),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.deleteIndex('test-index');

      expect(result).toBe(true);
    });
  });

  describe('indexExists', () => {
    it('should return true when index exists', async () => {
      mockFetch.mockResolvedValueOnce({ ok: true });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.indexExists('test-index');

      expect(result).toBe(true);
    });

    it('should return false when index does not exist', async () => {
      mockFetch.mockResolvedValueOnce({ ok: false });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.indexExists('non-existent');

      expect(result).toBe(false);
    });
  });

  describe('getClusterHealth', () => {
    it('should return cluster health info', async () => {
      const mockResponse = {
        status: 'green',
        number_of_nodes: 3,
        active_primary_shards: 10,
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify(mockResponse)),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.getClusterHealth();

      expect(result).toEqual({
        status: 'green',
        numberOfNodes: 3,
        activePrimaryShards: 10,
      });
    });

    it('should return null on error', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Connection refused'));

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.getClusterHealth();

      expect(result).toBeNull();
    });
  });

  describe('isAvailable', () => {
    it('should return true for green status', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify({ status: 'green' })),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.isAvailable();

      expect(result).toBe(true);
    });

    it('should return true for yellow status', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify({ status: 'yellow' })),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.isAvailable();

      expect(result).toBe(true);
    });

    it('should return false for red status', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        text: () => Promise.resolve(JSON.stringify({ status: 'red' })),
      });

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.isAvailable();

      expect(result).toBe(false);
    });

    it('should return false on connection error', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Connection refused'));

      const service = new ElasticsearchService({ url: 'http://localhost:9200' });
      const result = await service.isAvailable();

      expect(result).toBe(false);
    });
  });
});
