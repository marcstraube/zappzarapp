/**
 * Tests for SearchService
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { SearchService, type SearchServiceInterface } from '@backend/App/Services/SearchService';

// Use vi.hoisted to define mocks before vi.mock hoisting
const {
  MockMeilisearch,
  mockHealthFn,
  mockSearchFn,
  mockAddDocumentsFn,
  mockUpdateDocumentsFn,
  mockDeleteDocumentsFn,
  mockDeleteAllDocumentsFn,
  mockCreateIndexFn,
  mockDeleteIndexFn,
  mockGetIndexesFn,
  mockWaitForTaskFn,
  mockIndexFn,
} = vi.hoisted(() => {
  const mockHealthFn = vi.fn();
  const mockSearchFn = vi.fn();
  const mockAddDocumentsFn = vi.fn();
  const mockUpdateDocumentsFn = vi.fn();
  const mockDeleteDocumentsFn = vi.fn();
  const mockDeleteAllDocumentsFn = vi.fn();
  const mockCreateIndexFn = vi.fn();
  const mockDeleteIndexFn = vi.fn();
  const mockGetIndexesFn = vi.fn();
  const mockWaitForTaskFn = vi.fn();
  const mockIndexFn = vi.fn();

  // Mock Meilisearch class - properties accessed by SearchService during operations
  class MockMeilisearch {
    // noinspection JSUnusedGlobalSymbols - Used by SearchService.index(), updateDocuments(), etc.
    tasks = {
      waitForTask: mockWaitForTaskFn,
    };

    // noinspection JSUnusedGlobalSymbols - Used by SearchService.isAvailable() and connect()
    health(): unknown {
      return mockHealthFn() as unknown;
    }

    index(): object {
      mockIndexFn();
      return {
        search: mockSearchFn,
        addDocuments: mockAddDocumentsFn,
        updateDocuments: mockUpdateDocumentsFn,
        deleteDocuments: mockDeleteDocumentsFn,
        deleteAllDocuments: mockDeleteAllDocumentsFn,
      };
    }

    createIndex(...args: unknown[]): unknown {
      return mockCreateIndexFn(...args) as unknown;
    }

    deleteIndex(...args: unknown[]): unknown {
      return mockDeleteIndexFn(...args) as unknown;
    }

    getIndexes(): unknown {
      return mockGetIndexesFn() as unknown;
    }
  }

  return {
    MockMeilisearch,
    mockHealthFn,
    mockSearchFn,
    mockAddDocumentsFn,
    mockUpdateDocumentsFn,
    mockDeleteDocumentsFn,
    mockDeleteAllDocumentsFn,
    mockCreateIndexFn,
    mockDeleteIndexFn,
    mockGetIndexesFn,
    mockWaitForTaskFn,
    mockIndexFn,
  };
});

// Mock the meilisearch module
vi.mock('meilisearch', () => ({
  Meilisearch: MockMeilisearch,
}));

describe('SearchService', () => {
  beforeEach(() => {
    // Reset all mocks with default implementations
    mockHealthFn.mockReset().mockResolvedValue({ status: 'available' });
    mockSearchFn.mockReset().mockResolvedValue({
      hits: [],
      estimatedTotalHits: 0,
      offset: 0,
      limit: 20,
      processingTimeMs: 1,
      query: '',
    });
    mockAddDocumentsFn.mockReset().mockResolvedValue({ taskUid: 1 });
    mockUpdateDocumentsFn.mockReset().mockResolvedValue({ taskUid: 2 });
    mockDeleteDocumentsFn.mockReset().mockResolvedValue({ taskUid: 3 });
    mockDeleteAllDocumentsFn.mockReset().mockResolvedValue({ taskUid: 4 });
    mockCreateIndexFn.mockReset().mockResolvedValue({ taskUid: 5 });
    mockDeleteIndexFn.mockReset().mockResolvedValue({ taskUid: 6 });
    mockGetIndexesFn.mockReset().mockResolvedValue({ results: [] });
    mockWaitForTaskFn.mockReset().mockResolvedValue({ status: 'succeeded' });
    mockIndexFn.mockReset();
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  describe('constructor', () => {
    it('should use default values when no options provided', () => {
      const search = new SearchService();

      expect(search.getUrl()).toBe('https://meilisearch:7700');
      expect(search.isConnected()).toBe(false);
    });

    it('should accept custom options', () => {
      const search = new SearchService({
        url: 'https://custom:7777',
        masterKey: 'custom-key',
        timeout: 10000,
      });

      expect(search.getUrl()).toBe('https://custom:7777');
    });

    it('should use MEILISEARCH_URL from environment', () => {
      const originalEnv = process.env.MEILISEARCH_URL;
      process.env.MEILISEARCH_URL = 'https://env:8080';

      const search = new SearchService();
      expect(search.getUrl()).toBe('https://env:8080');

      process.env.MEILISEARCH_URL = originalEnv;
    });
  });

  describe('connect', () => {
    it('should connect to Meilisearch', async () => {
      const search = new SearchService();

      await search.connect();

      expect(mockHealthFn).toHaveBeenCalledTimes(1);
      expect(search.isConnected()).toBe(true);
    });

    it('should not reconnect if already connected', async () => {
      const search = new SearchService();

      await search.connect();
      const firstHealthCallCount = mockHealthFn.mock.calls.length;

      await search.connect();

      expect(mockHealthFn.mock.calls.length).toBe(firstHealthCallCount);
    });

    it('should handle connection errors', async () => {
      mockHealthFn.mockRejectedValueOnce(new Error('Connection failed'));
      const search = new SearchService();

      await search.connect();

      expect(search.isConnected()).toBe(false);
    });

    it('should handle unhealthy status', async () => {
      mockHealthFn.mockResolvedValueOnce({ status: 'unavailable' });
      const search = new SearchService();

      await search.connect();

      expect(search.isConnected()).toBe(false);
    });
  });

  describe('disconnect', () => {
    it('should disconnect from Meilisearch', async () => {
      const search = new SearchService();
      await search.connect();

      await search.disconnect();

      expect(search.isConnected()).toBe(false);
    });

    it('should handle disconnect when not connected', async () => {
      const search = new SearchService();

      await expect(search.disconnect()).resolves.toBeUndefined();
    });
  });

  describe('search', () => {
    it('should search documents', async () => {
      const search = new SearchService();
      await search.connect();

      const mockResults = {
        hits: [
          { id: 1, name: 'Product A' },
          { id: 2, name: 'Product B' },
        ],
        estimatedTotalHits: 2,
        offset: 0,
        limit: 20,
        processingTimeMs: 5,
        query: 'Product',
      };

      mockSearchFn.mockResolvedValueOnce(mockResults);

      const result = await search.search('products', 'Product');

      expect(result).toEqual(mockResults);
      expect(mockIndexFn).toHaveBeenCalled();
      expect(mockSearchFn).toHaveBeenCalledWith('Product', {});
    });

    it('should search with options', async () => {
      const search = new SearchService();
      await search.connect();

      const options = {
        limit: 10,
        offset: 5,
        filter: 'category = electronics',
      };

      await search.search('products', 'laptop', options);

      expect(mockSearchFn).toHaveBeenCalledWith('laptop', options);
    });

    it('should return null when not connected', async () => {
      const search = new SearchService();

      const result = await search.search('products', 'query');

      expect(result).toBeNull();
    });

    it('should return null on search error', async () => {
      const search = new SearchService();
      await search.connect();

      mockSearchFn.mockRejectedValueOnce(new Error('Search failed'));

      const result = await search.search('products', 'query');

      expect(result).toBeNull();
    });
  });

  describe('index', () => {
    it('should index documents', async () => {
      const search = new SearchService();
      await search.connect();

      const documents = [
        { id: 1, name: 'Product A' },
        { id: 2, name: 'Product B' },
      ];

      const result = await search.index('products', documents, 'id');

      expect(result).toBe(true);
      expect(mockAddDocumentsFn).toHaveBeenCalledWith(documents, { primaryKey: 'id' });
      expect(mockWaitForTaskFn).toHaveBeenCalledWith(1);
    });

    it('should index documents without primary key', async () => {
      const search = new SearchService();
      await search.connect();

      const documents = [{ id: 1, name: 'Product A' }];

      await search.index('products', documents);

      expect(mockAddDocumentsFn).toHaveBeenCalledWith(documents, { primaryKey: undefined });
    });

    it('should return false when not connected', async () => {
      const search = new SearchService();

      const result = await search.index('products', []);

      expect(result).toBe(false);
    });

    it('should return false on indexing error', async () => {
      const search = new SearchService();
      await search.connect();

      mockAddDocumentsFn.mockRejectedValueOnce(new Error('Index failed'));

      const result = await search.index('products', [{ id: 1 }]);

      expect(result).toBe(false);
    });
  });

  describe('updateDocuments', () => {
    it('should update documents', async () => {
      const search = new SearchService();
      await search.connect();

      const documents = [{ id: 1, name: 'Updated Product' }];

      const result = await search.updateDocuments('products', documents);

      expect(result).toBe(true);
      expect(mockUpdateDocumentsFn).toHaveBeenCalledWith(documents);
      expect(mockWaitForTaskFn).toHaveBeenCalledWith(2);
    });

    it('should return false when not connected', async () => {
      const search = new SearchService();

      const result = await search.updateDocuments('products', []);

      expect(result).toBe(false);
    });

    it('should return false on update error', async () => {
      const search = new SearchService();
      await search.connect();

      mockUpdateDocumentsFn.mockRejectedValueOnce(new Error('Update failed'));

      const result = await search.updateDocuments('products', [{ id: 1 }]);

      expect(result).toBe(false);
    });
  });

  describe('deleteDocuments', () => {
    it('should delete documents by IDs', async () => {
      const search = new SearchService();
      await search.connect();

      const ids: number[] = [1, 2, 3];

      const result = await search.deleteDocuments('products', ids);

      expect(result).toBe(true);
      expect(mockDeleteDocumentsFn).toHaveBeenCalledWith(ids);
      expect(mockWaitForTaskFn).toHaveBeenCalledWith(3);
    });

    it('should return false when not connected', async () => {
      const search = new SearchService();

      const result = await search.deleteDocuments('products', [1]);

      expect(result).toBe(false);
    });

    it('should return false on delete error', async () => {
      const search = new SearchService();
      await search.connect();

      mockDeleteDocumentsFn.mockRejectedValueOnce(new Error('Delete failed'));

      const result = await search.deleteDocuments('products', [1]);

      expect(result).toBe(false);
    });
  });

  describe('deleteAllDocuments', () => {
    it('should delete all documents', async () => {
      const search = new SearchService();
      await search.connect();

      const result = await search.deleteAllDocuments('products');

      expect(result).toBe(true);
      expect(mockDeleteAllDocumentsFn).toHaveBeenCalled();
      expect(mockWaitForTaskFn).toHaveBeenCalledWith(4);
    });

    it('should return false when not connected', async () => {
      const search = new SearchService();

      const result = await search.deleteAllDocuments('products');

      expect(result).toBe(false);
    });

    it('should return false on delete all error', async () => {
      const search = new SearchService();
      await search.connect();

      mockDeleteAllDocumentsFn.mockRejectedValueOnce(new Error('Delete all failed'));

      const result = await search.deleteAllDocuments('products');

      expect(result).toBe(false);
    });
  });

  describe('createIndex', () => {
    it('should create index with primary key', async () => {
      const search = new SearchService();
      await search.connect();

      const result = await search.createIndex('products', 'id');

      expect(result).toBe(true);
      expect(mockCreateIndexFn).toHaveBeenCalledWith('products', { primaryKey: 'id' });
      expect(mockWaitForTaskFn).toHaveBeenCalledWith(5);
    });

    it('should create index without primary key', async () => {
      const search = new SearchService();
      await search.connect();

      const result = await search.createIndex('products');

      expect(result).toBe(true);
      expect(mockCreateIndexFn).toHaveBeenCalledWith('products', { primaryKey: undefined });
    });

    it('should return false when not connected', async () => {
      const search = new SearchService();

      const result = await search.createIndex('products');

      expect(result).toBe(false);
    });

    it('should return false on create error', async () => {
      const search = new SearchService();
      await search.connect();

      mockCreateIndexFn.mockRejectedValueOnce(new Error('Create failed'));

      const result = await search.createIndex('products');

      expect(result).toBe(false);
    });
  });

  describe('deleteIndex', () => {
    it('should delete index', async () => {
      const search = new SearchService();
      await search.connect();

      const result = await search.deleteIndex('products');

      expect(result).toBe(true);
      expect(mockDeleteIndexFn).toHaveBeenCalledWith('products');
      expect(mockWaitForTaskFn).toHaveBeenCalledWith(6);
    });

    it('should return false when not connected', async () => {
      const search = new SearchService();

      const result = await search.deleteIndex('products');

      expect(result).toBe(false);
    });

    it('should return false on delete error', async () => {
      const search = new SearchService();
      await search.connect();

      mockDeleteIndexFn.mockRejectedValueOnce(new Error('Delete failed'));

      const result = await search.deleteIndex('products');

      expect(result).toBe(false);
    });
  });

  describe('getIndexes', () => {
    it('should return list of indexes', async () => {
      const search = new SearchService();
      await search.connect();

      const mockIndexes = {
        results: [
          {
            uid: 'products',
            primaryKey: 'id',
            createdAt: '2024-01-01T00:00:00Z',
            updatedAt: '2024-01-02T00:00:00Z',
          },
          {
            uid: 'users',
            primaryKey: null,
            createdAt: '2024-01-03T00:00:00Z',
            updatedAt: '2024-01-04T00:00:00Z',
          },
        ],
      };

      mockGetIndexesFn.mockResolvedValueOnce(mockIndexes);

      const result = await search.getIndexes();

      expect(result).toEqual([
        {
          uid: 'products',
          primaryKey: 'id',
          createdAt: new Date('2024-01-01T00:00:00Z'),
          updatedAt: new Date('2024-01-02T00:00:00Z'),
        },
        {
          uid: 'users',
          primaryKey: null,
          createdAt: new Date('2024-01-03T00:00:00Z'),
          updatedAt: new Date('2024-01-04T00:00:00Z'),
        },
      ]);
      expect(mockGetIndexesFn).toHaveBeenCalled();
    });

    it('should return null when not connected', async () => {
      const search = new SearchService();

      const result = await search.getIndexes();

      expect(result).toBeNull();
    });

    it('should return null on error', async () => {
      const search = new SearchService();
      await search.connect();

      mockGetIndexesFn.mockRejectedValueOnce(new Error('Get indexes failed'));

      const result = await search.getIndexes();

      expect(result).toBeNull();
    });
  });

  describe('isAvailable', () => {
    it('should return true when health check succeeds', async () => {
      const search = new SearchService();
      await search.connect();

      // The connect() call already uses health(), so for the next call:
      mockHealthFn.mockResolvedValueOnce({ status: 'available' });

      const result = await search.isAvailable();

      expect(result).toBe(true);
    });

    it('should return false when not connected', async () => {
      const search = new SearchService();

      const result = await search.isAvailable();

      expect(result).toBe(false);
    });

    it('should return false when health check fails', async () => {
      const search = new SearchService();
      await search.connect();

      mockHealthFn.mockRejectedValueOnce(new Error('Health check failed'));

      const result = await search.isAvailable();

      expect(result).toBe(false);
    });

    it('should return false when status is not available', async () => {
      const search = new SearchService();
      await search.connect();

      mockHealthFn.mockResolvedValueOnce({ status: 'unavailable' });

      const result = await search.isAvailable();

      expect(result).toBe(false);
    });
  });

  describe('interface compliance', () => {
    it('should implement SearchServiceInterface', () => {
      const search: SearchServiceInterface = new SearchService();

      expect(typeof search.connect).toBe('function');
      expect(typeof search.disconnect).toBe('function');
      expect(typeof search.search).toBe('function');
      expect(typeof search.index).toBe('function');
      expect(typeof search.updateDocuments).toBe('function');
      expect(typeof search.deleteDocuments).toBe('function');
      expect(typeof search.deleteAllDocuments).toBe('function');
      expect(typeof search.createIndex).toBe('function');
      expect(typeof search.deleteIndex).toBe('function');
      expect(typeof search.getIndexes).toBe('function');
      expect(typeof search.isAvailable).toBe('function');
    });
  });
});
