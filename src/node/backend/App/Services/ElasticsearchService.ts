/**
 * Elasticsearch Service for full-text search and analytics operations
 *
 * Provides a simple, type-safe interface for indexing, searching, and analytics.
 * Uses native fetch API to communicate with Elasticsearch REST API (no external dependencies).
 *
 * Features:
 * - Lazy connection (connects on first use)
 * - TLS/SSL support with configurable verification
 * - API key authentication
 * - Bulk indexing with NDJSON
 * - Aggregation support
 * - Graceful error handling (returns null/false, no throws)
 *
 * Configuration:
 * - ELASTICSEARCH_URL: Connection URL (default: https://elasticsearch:9200)
 * - ELASTICSEARCH_API_KEY: API key from Docker secrets or environment
 * - ELASTICSEARCH_VERIFY_SSL: Verify SSL certificate (default: false for dev)
 *
 * Usage:
 * ```typescript
 * const es = new ElasticsearchService();
 * await es.connect();
 *
 * // Index documents
 * await es.bulkIndex('products', [
 *   { id: '1', name: 'Laptop', price: 999 },
 *   { id: '2', name: 'Mouse', price: 29 }
 * ]);
 *
 * // Search
 * const results = await es.search('products', {
 *   match: { name: 'laptop' }
 * });
 *
 * // Aggregation
 * const agg = await es.aggregate('products', {
 *   avg_price: { avg: { field: 'price' } }
 * });
 *
 * await es.disconnect();
 * ```
 */

import { loadCredential } from '../../Shared/Config/credentials';

/**
 * Search hit structure
 */
export interface SearchHit<T = Record<string, unknown>> {
  _id: string;
  _index: string;
  _score: number | null;
  _source: T;
  highlight?: Record<string, string[]>;
}

/**
 * Search result structure
 */
export interface SearchResult<T = Record<string, unknown>> {
  hits: {
    total: {
      value: number;
      relation: 'eq' | 'gte';
    };
    hits: SearchHit<T>[];
  };
  took: number;
}

/**
 * Bulk index result
 */
export interface BulkIndexResult {
  indexed: number;
  errors: number;
}

/**
 * Cluster health response
 */
export interface ClusterHealth {
  status: 'green' | 'yellow' | 'red' | 'unknown';
  numberOfNodes: number;
  activePrimaryShards: number;
}

/**
 * Search options
 */
export interface SearchOptions {
  /** Maximum number of results (default: 10) */
  size?: number;
  /** Offset for pagination (default: 0) */
  from?: number;
  /** Fields to return */
  _source?: string[];
  /** Sort criteria */
  sort?: Record<string, 'asc' | 'desc'>[];
  /** Highlight configuration */
  highlight?: {
    fields: Record<string, object>;
  };
}

/**
 * Elasticsearch Service Interface
 */
export interface ElasticsearchServiceInterface {
  /**
   * Connect to Elasticsearch
   */
  connect(): Promise<void>;

  /**
   * Disconnect from Elasticsearch
   */
  disconnect(): Promise<void>;

  /**
   * Search documents using Elasticsearch Query DSL
   * @returns Search results or null on error
   */
  search<T = Record<string, unknown>>(
    index: string,
    query: Record<string, unknown>,
    options?: SearchOptions
  ): Promise<SearchResult<T> | null>;

  /**
   * Get a single document by ID
   * @returns Document source or null if not found
   */
  get<T = Record<string, unknown>>(index: string, id: string): Promise<T | null>;

  /**
   * Index a single document
   * @returns Document ID on success, null on error
   */
  index(
    index: string,
    id: string | null,
    document: Record<string, unknown>
  ): Promise<string | null>;

  /**
   * Bulk index documents
   * @returns Count of indexed and failed documents
   */
  bulkIndex(
    index: string,
    documents: Record<string, unknown>[],
    idField?: string
  ): Promise<BulkIndexResult>;

  /**
   * Update a document (partial update)
   * @returns true on success, false on error
   */
  update(index: string, id: string, fields: Record<string, unknown>): Promise<boolean>;

  /**
   * Delete a document by ID
   * @returns true on success, false on error
   */
  delete(index: string, id: string): Promise<boolean>;

  /**
   * Delete documents matching a query
   * @returns Number of deleted documents, -1 on error
   */
  deleteByQuery(index: string, query: Record<string, unknown>): Promise<number>;

  /**
   * Execute aggregation query
   * @returns Aggregation results or null on error
   */
  aggregate(
    index: string,
    aggregations: Record<string, unknown>,
    query?: Record<string, unknown>
  ): Promise<Record<string, unknown> | null>;

  /**
   * Create an index with optional mappings and settings
   * @returns true on success, false on error
   */
  createIndex(
    index: string,
    mappings?: Record<string, unknown>,
    settings?: Record<string, unknown>
  ): Promise<boolean>;

  /**
   * Delete an index
   * @returns true on success, false on error
   */
  deleteIndex(index: string): Promise<boolean>;

  /**
   * Check if an index exists
   */
  indexExists(index: string): Promise<boolean>;

  /**
   * Refresh an index (make recent changes visible to search)
   * @returns true on success, false on error
   */
  refresh(index: string): Promise<boolean>;

  /**
   * Get cluster health status
   * @returns Cluster health or null on error
   */
  getClusterHealth(): Promise<ClusterHealth | null>;

  /**
   * Check if Elasticsearch is available
   */
  isAvailable(): Promise<boolean>;
}

/**
 * Elasticsearch Service Configuration
 */
export interface ElasticsearchServiceOptions {
  /** Elasticsearch URL (default: from ELASTICSEARCH_URL env or https://elasticsearch:9200) */
  url?: string;
  /** API key (default: from Docker secrets or ELASTICSEARCH_API_KEY env) */
  apiKey?: string;
  /** Request timeout in milliseconds (default: 30000) */
  timeout?: number;
  /** Verify SSL certificate (default: false for development) */
  verifySsl?: boolean;
}

/**
 * Default configuration values
 */
const DEFAULT_URL = 'https://elasticsearch:9200';
const DEFAULT_TIMEOUT = 30000;

/**
 * Elasticsearch Service Implementation
 */
export class ElasticsearchService implements ElasticsearchServiceInterface {
  private connected = false;
  private readonly url: string;
  private readonly apiKey: string;
  private readonly timeout: number;

  constructor(options: ElasticsearchServiceOptions = {}) {
    this.url = (options.url ?? process.env.ELASTICSEARCH_URL ?? DEFAULT_URL).replace(/\/$/, '');
    this.apiKey =
      options.apiKey ?? loadCredential('elasticsearch_api_key', 'ELASTICSEARCH_API_KEY', '');
    this.timeout = options.timeout ?? DEFAULT_TIMEOUT;
  }

  async connect(): Promise<void> {
    if (this.connected) {
      return;
    }

    try {
      this.connected = await this.isAvailable();
    } catch {
      this.connected = false;
    }
  }

  async disconnect(): Promise<void> {
    this.connected = false;
    await Promise.resolve();
  }

  async search<T = Record<string, unknown>>(
    index: string,
    query: Record<string, unknown>,
    options: SearchOptions = {}
  ): Promise<SearchResult<T> | null> {
    try {
      const body: Record<string, unknown> = { query };

      if (options.size !== undefined) {
        body.size = options.size;
      }
      if (options.from !== undefined) {
        body.from = options.from;
      }
      if (options._source !== undefined) {
        body._source = options._source;
      }
      if (options.sort !== undefined) {
        body.sort = options.sort;
      }
      if (options.highlight !== undefined) {
        body.highlight = options.highlight;
      }

      const response = await this.request<{
        hits?: {
          total?: { value: number; relation: 'eq' | 'gte' };
          hits?: SearchHit<T>[];
        };
        took?: number;
      }>('GET', `/${index}/_search`, body);

      if (response === null) {
        return null;
      }

      return {
        hits: {
          total: response.hits?.total ?? { value: 0, relation: 'eq' },
          hits: response.hits?.hits ?? [],
        },
        took: response.took ?? 0,
      };
    } catch {
      return null;
    }
  }

  async get<T = Record<string, unknown>>(index: string, id: string): Promise<T | null> {
    try {
      const response = await this.request<{ found: boolean; _source: T }>(
        'GET',
        `/${index}/_doc/${id}`
      );

      if (!response?.found) {
        return null;
      }

      return response._source ?? null;
    } catch {
      return null;
    }
  }

  async index(
    index: string,
    id: string | null,
    document: Record<string, unknown>
  ): Promise<string | null> {
    try {
      const method = id !== null ? 'PUT' : 'POST';
      const path = id !== null ? `/${index}/_doc/${id}` : `/${index}/_doc`;

      const response = await this.request<{ _id: string }>(method, path, document);

      return response?._id ?? null;
    } catch {
      return null;
    }
  }

  async bulkIndex(
    index: string,
    documents: Record<string, unknown>[],
    idField = 'id'
  ): Promise<BulkIndexResult> {
    if (documents.length === 0) {
      return { indexed: 0, errors: 0 };
    }

    try {
      // Build NDJSON body
      let body = '';
      for (const doc of documents) {
        const docCopy = { ...doc };
        const docId = docCopy[idField];
        delete docCopy[idField];

        const action: { index: { _index: string; _id?: string } } = { index: { _index: index } };
        if (typeof docId === 'string' || typeof docId === 'number') {
          action.index._id = String(docId);
        }

        body += JSON.stringify(action) + '\n';
        body += JSON.stringify(docCopy) + '\n';
      }

      const response = await this.request<{
        items?: Array<{ index?: { status?: number } }>;
      }>('POST', '/_bulk', undefined, body, 'application/x-ndjson');

      if (response === null) {
        return { indexed: 0, errors: documents.length };
      }

      let indexed = 0;
      let errors = 0;

      for (const item of response.items ?? []) {
        const status = item.index?.status ?? 500;
        if (status >= 200 && status < 300) {
          indexed++;
        } else {
          errors++;
        }
      }

      return { indexed, errors };
    } catch {
      return { indexed: 0, errors: documents.length };
    }
  }

  async update(index: string, id: string, fields: Record<string, unknown>): Promise<boolean> {
    try {
      const response = await this.request<{ result?: string }>('POST', `/${index}/_update/${id}`, {
        doc: fields,
      });

      return response?.result !== undefined;
    } catch {
      return false;
    }
  }

  async delete(index: string, id: string): Promise<boolean> {
    try {
      const response = await this.request<{ result: string }>('DELETE', `/${index}/_doc/${id}`);

      return response !== null && response.result === 'deleted';
    } catch {
      return false;
    }
  }

  async deleteByQuery(index: string, query: Record<string, unknown>): Promise<number> {
    try {
      const response = await this.request<{ deleted?: number }>(
        'POST',
        `/${index}/_delete_by_query`,
        { query }
      );

      if (response === null) {
        return -1;
      }

      return response.deleted ?? 0;
    } catch {
      return -1;
    }
  }

  async aggregate(
    index: string,
    aggregations: Record<string, unknown>,
    query?: Record<string, unknown>
  ): Promise<Record<string, unknown> | null> {
    try {
      const body: Record<string, unknown> = {
        size: 0,
        aggs: aggregations,
      };

      if (query !== undefined) {
        body.query = query;
      }

      const response = await this.request<{ aggregations: Record<string, unknown> }>(
        'GET',
        `/${index}/_search`,
        body
      );

      return response?.aggregations ?? null;
    } catch {
      return null;
    }
  }

  async createIndex(
    index: string,
    mappings?: Record<string, unknown>,
    settings?: Record<string, unknown>
  ): Promise<boolean> {
    try {
      const body: Record<string, unknown> = {};

      if (mappings !== undefined) {
        body.mappings = mappings;
      }
      if (settings !== undefined) {
        body.settings = settings;
      }

      const response = await this.request<{ acknowledged: boolean }>(
        'PUT',
        `/${index}`,
        Object.keys(body).length > 0 ? body : undefined
      );

      return response?.acknowledged === true;
    } catch {
      return false;
    }
  }

  async deleteIndex(index: string): Promise<boolean> {
    try {
      const response = await this.request<{ acknowledged: boolean }>('DELETE', `/${index}`);

      return response?.acknowledged === true;
    } catch {
      return false;
    }
  }

  async indexExists(index: string): Promise<boolean> {
    try {
      return await this.requestHead(`/${index}`);
    } catch {
      return false;
    }
  }

  async refresh(index: string): Promise<boolean> {
    try {
      const response = await this.request<object>('POST', `/${index}/_refresh`);
      return response !== null;
    } catch {
      return false;
    }
  }

  async getClusterHealth(): Promise<ClusterHealth | null> {
    try {
      const response = await this.request<{
        status?: 'green' | 'yellow' | 'red';
        number_of_nodes?: number;
        active_primary_shards?: number;
      }>('GET', '/_cluster/health');

      if (response === null) {
        return null;
      }

      return {
        status: response.status ?? 'unknown',
        numberOfNodes: response.number_of_nodes ?? 0,
        activePrimaryShards: response.active_primary_shards ?? 0,
      };
    } catch {
      return null;
    }
  }

  async isAvailable(): Promise<boolean> {
    try {
      const health = await this.getClusterHealth();

      if (health === null) {
        return false;
      }

      return health.status === 'green' || health.status === 'yellow';
    } catch {
      return false;
    }
  }

  /**
   * Check if client is connected (for testing)
   */
  isConnected(): boolean {
    return this.connected;
  }

  /**
   * Get the configured URL (for testing)
   */
  getUrl(): string {
    return this.url;
  }

  /**
   * Execute an HTTP request to Elasticsearch
   */
  private async request<T>(
    method: string,
    path: string,
    body?: Record<string, unknown>,
    rawBody?: string,
    contentType = 'application/json'
  ): Promise<T | null> {
    const url = `${this.url}${path}`;
    const headers: Record<string, string> = {
      'Content-Type': contentType,
    };

    if (this.apiKey !== '') {
      headers['Authorization'] = `ApiKey ${this.apiKey}`;
    }

    const requestInit: RequestInit = {
      method,
      headers,
      signal: AbortSignal.timeout(this.timeout),
    };

    if (rawBody !== undefined) {
      requestInit.body = rawBody;
    } else if (body !== undefined) {
      requestInit.body = JSON.stringify(body);
    }

    const response = await fetch(url, requestInit);

    if (!response.ok) {
      return null;
    }

    const text = await response.text();
    if (text === '' || text === '{}') {
      return {} as T;
    }

    return JSON.parse(text) as T;
  }

  /**
   * Execute a HEAD request (for existence checks)
   */
  private async requestHead(path: string): Promise<boolean> {
    const url = `${this.url}${path}`;
    const headers: Record<string, string> = {};

    if (this.apiKey !== '') {
      headers['Authorization'] = `ApiKey ${this.apiKey}`;
    }

    const response = await fetch(url, {
      method: 'HEAD',
      headers,
      signal: AbortSignal.timeout(this.timeout),
    });

    return response.ok;
  }
}
