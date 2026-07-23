/**
 * Search Service for full-text search operations using Meilisearch
 *
 * Provides a simple, type-safe interface for indexing and searching documents.
 * Supports both TLS (HTTPS) and plain (HTTP) connections.
 *
 * Features:
 * - Lazy connection (connects on first use)
 * - Automatic health checks
 * - Index management (create, delete, list)
 * - Document operations (index, update, delete)
 * - Full-text search with filters and options
 * - Graceful error handling (returns null/false, no throws)
 *
 * Configuration:
 * - MEILISEARCH_URL: Connection URL (default: https://meilisearch:7700)
 * - Master key is loaded from Docker secrets or environment:
 *   - /run/secrets/meilisearch_master_key.txt
 *   - /tmp/secrets/meilisearch_master_key.txt
 *   - MEILISEARCH_MASTER_KEY environment variable
 *
 * Usage:
 * ```typescript
 * const search = new SearchService();
 * await search.connect();
 *
 * // Create an index
 * await search.createIndex('products', 'id');
 *
 * // Index documents
 * await search.index('products', [
 *   { id: 1, name: 'Product A', category: 'electronics' },
 *   { id: 2, name: 'Product B', category: 'books' }
 * ]);
 *
 * // Search
 * const results = await search.search('products', 'Product A');
 * if (results !== null) {
 *   console.log(`Found ${results.estimatedTotalHits} results`);
 *   results.hits.forEach(hit => console.log(hit));
 * }
 *
 * // Cleanup
 * await search.disconnect();
 * ```
 */

import { MeiliSearch } from 'meilisearch';
import { loadCredential } from '../../Shared/Config/credentials';

/**
 * Search result structure
 */
export interface SearchResult<T = Record<string, unknown>> {
  hits: T[];
  estimatedTotalHits: number;
  offset: number;
  limit: number;
  processingTimeMs: number;
  query: string;
}

/**
 * Index information
 */
export interface IndexInfo {
  uid: string;
  primaryKey: string | null;
  createdAt: Date;
  updatedAt: Date;
}

/**
 * Search options
 */
export interface SearchOptions {
  /** Maximum number of results to return (default: 20) */
  limit?: number;
  /** Number of results to skip (default: 0) */
  offset?: number;
  /** Attributes to retrieve (default: all) */
  attributesToRetrieve?: string[];
  /** Attributes to crop */
  attributesToCrop?: string[];
  /** Attributes to highlight */
  attributesToHighlight?: string[];
  /** Filter expression */
  filter?: string | string[];
  /** Sort order */
  sort?: string[];
}

/**
 * Search Service Interface
 */
export interface SearchServiceInterface {
  /**
   * Connect to search backend
   */
  connect(): Promise<void>;

  /**
   * Disconnect from search backend
   */
  disconnect(): Promise<void>;

  /**
   * Search documents in an index
   * @returns Search results or null on error
   */
  search<T = Record<string, unknown>>(
    index: string,
    query: string,
    options?: SearchOptions
  ): Promise<SearchResult<T> | null>;

  /**
   * Index documents
   * @returns true on success, false on error
   */
  index(index: string, documents: Record<string, unknown>[], primaryKey?: string): Promise<boolean>;

  /**
   * Update documents
   * @returns true on success, false on error
   */
  updateDocuments(index: string, documents: Record<string, unknown>[]): Promise<boolean>;

  /**
   * Delete documents by IDs
   * @returns true on success, false on error
   */
  deleteDocuments(index: string, ids: string[] | number[]): Promise<boolean>;

  /**
   * Delete all documents from an index
   * @returns true on success, false on error
   */
  deleteAllDocuments(index: string): Promise<boolean>;

  /**
   * Create a new index
   * @returns true on success, false on error
   */
  createIndex(index: string, primaryKey?: string): Promise<boolean>;

  /**
   * Delete an index
   * @returns true on success, false on error
   */
  deleteIndex(index: string): Promise<boolean>;

  /**
   * Get list of indexes
   * @returns Array of index info or null on error
   */
  getIndexes(): Promise<IndexInfo[] | null>;

  /**
   * Check if search backend is available
   */
  isAvailable(): Promise<boolean>;
}

/**
 * Search Service Configuration
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export interface SearchServiceOptions {
  /** Meilisearch URL (default: from MEILISEARCH_URL env or https://meilisearch:7700) */
  url?: string;
  /** Master key (default: from Docker secrets or MEILISEARCH_MASTER_KEY env) */
  masterKey?: string;
  /** Request timeout in milliseconds (default: 5000) */
  timeout?: number;
}

/**
 * Default configuration values
 */
const DEFAULT_URL = 'https://meilisearch:7700';
const DEFAULT_TIMEOUT = 5000;

/**
 * Meilisearch Search Service Implementation
 */
export class SearchService implements SearchServiceInterface {
  private client: MeiliSearch | null = null;
  private readonly url: string;
  private readonly masterKey: string;
  private readonly timeout: number;

  constructor(options: SearchServiceOptions = {}) {
    this.url = options.url ?? process.env.MEILISEARCH_URL ?? DEFAULT_URL;
    this.masterKey =
      options.masterKey ?? loadCredential('meilisearch_master_key', 'MEILISEARCH_MASTER_KEY', '');
    this.timeout = options.timeout ?? DEFAULT_TIMEOUT;
  }

  async connect(): Promise<void> {
    if (this.client !== null) {
      return; // Already connected
    }

    try {
      this.client = new MeiliSearch({
        host: this.url,
        apiKey: this.masterKey,
        timeout: this.timeout,
      });

      // Verify connection by checking health
      const isHealthy = await this.isAvailable();
      if (!isHealthy) {
        this.client = null;
      }
    } catch {
      this.client = null;
    }
  }

  async disconnect(): Promise<void> {
    // Meilisearch client doesn't maintain persistent connections
    // Just clear the client reference
    this.client = null;
    await Promise.resolve(); // Ensure async consistency
  }

  async search<T = Record<string, unknown>>(
    index: string,
    query: string,
    options: SearchOptions = {}
  ): Promise<SearchResult<T> | null> {
    const client = this.getClient();
    if (client === null) {
      return null;
    }

    try {
      const indexObj = client.index(index);
      const result = await indexObj.search(query, options);

      return {
        hits: result.hits as T[],
        estimatedTotalHits: result.estimatedTotalHits,
        offset: result.offset,
        limit: result.limit,
        processingTimeMs: result.processingTimeMs,
        query: result.query,
      };
    } catch {
      return null;
    }
  }

  async index(
    index: string,
    documents: Record<string, unknown>[],
    primaryKey?: string
  ): Promise<boolean> {
    const client = this.getClient();
    if (client === null) {
      return false;
    }

    try {
      const indexObj = client.index(index);
      const task = await indexObj.addDocuments(documents, { primaryKey });

      // Wait for task to complete
      await client.tasks.waitForTask(task.taskUid);
      return true;
    } catch {
      return false;
    }
  }

  async updateDocuments(index: string, documents: Record<string, unknown>[]): Promise<boolean> {
    const client = this.getClient();
    if (client === null) {
      return false;
    }

    try {
      const indexObj = client.index(index);
      const task = await indexObj.updateDocuments(documents);

      // Wait for task to complete
      await client.tasks.waitForTask(task.taskUid);
      return true;
    } catch {
      return false;
    }
  }

  async deleteDocuments(index: string, ids: string[] | number[]): Promise<boolean> {
    const client = this.getClient();
    if (client === null) {
      return false;
    }

    try {
      const indexObj = client.index(index);
      const task = await indexObj.deleteDocuments(ids);

      // Wait for task to complete
      await client.tasks.waitForTask(task.taskUid);
      return true;
    } catch {
      return false;
    }
  }

  async deleteAllDocuments(index: string): Promise<boolean> {
    const client = this.getClient();
    if (client === null) {
      return false;
    }

    try {
      const indexObj = client.index(index);
      const task = await indexObj.deleteAllDocuments();

      // Wait for task to complete
      await client.tasks.waitForTask(task.taskUid);
      return true;
    } catch {
      return false;
    }
  }

  async createIndex(index: string, primaryKey?: string): Promise<boolean> {
    const client = this.getClient();
    if (client === null) {
      return false;
    }

    try {
      const task = await client.createIndex(index, { primaryKey });

      // Wait for task to complete
      await client.tasks.waitForTask(task.taskUid);
      return true;
    } catch {
      return false;
    }
  }

  async deleteIndex(index: string): Promise<boolean> {
    const client = this.getClient();
    if (client === null) {
      return false;
    }

    try {
      const task = await client.deleteIndex(index);

      // Wait for task to complete
      await client.tasks.waitForTask(task.taskUid);
      return true;
    } catch {
      return false;
    }
  }

  async getIndexes(): Promise<IndexInfo[] | null> {
    const client = this.getClient();
    if (client === null) {
      return null;
    }

    try {
      const indexes = await client.getIndexes();

      return indexes.results.map((idx) => ({
        uid: idx.uid,
        primaryKey: idx.primaryKey ?? null,
        createdAt: idx.createdAt ? new Date(idx.createdAt) : new Date(),
        updatedAt: idx.updatedAt ? new Date(idx.updatedAt) : new Date(),
      }));
    } catch {
      return null;
    }
  }

  async isAvailable(): Promise<boolean> {
    const client = this.getClient();
    if (client === null) {
      return false;
    }

    try {
      const health = await client.health();
      // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition -- SDK types status as literal, but check for future-proofing
      return health.status === 'available';
    } catch {
      return false;
    }
  }

  /**
   * Get the Meilisearch client instance
   * @returns Client or null if not connected
   */
  private getClient(): MeiliSearch | null {
    return this.client;
  }

  /**
   * Check if client is initialized (for testing)
   */
  isConnected(): boolean {
    return this.client !== null;
  }

  /**
   * Get the configured URL (for testing)
   */
  getUrl(): string {
    return this.url;
  }
}
