/**
 * S3-Compatible Storage Service for file upload and download
 *
 * Native implementation using fetch API - no external dependencies.
 * Implements AWS Signature Version 4 for authentication.
 *
 * Compatible with:
 * - SeaweedFS (recommended for this boilerplate)
 * - MinIO
 * - AWS S3
 * - Other S3-compatible services
 *
 * Features:
 * - File upload and download
 * - Pre-signed URL generation
 * - Bucket operations (create, exists)
 * - Object metadata
 * - TLS support with configurable verification
 *
 * Configuration:
 * - S3_ENDPOINT_URL or SEAWEEDFS_ENDPOINT: Storage endpoint (default: http://seaweedfs:8333)
 * - S3_ACCESS_KEY or SEAWEEDFS_S3_ACCESS_KEY: Access key (default: admin)
 * - S3_SECRET_KEY or SEAWEEDFS_S3_SECRET_KEY: Secret key (default: admin)
 * - S3_REGION: Region (default: us-east-1)
 * - S3_BUCKET: Default bucket (default: default)
 *
 * Usage:
 * ```typescript
 * const storage = new StorageService();
 * await storage.connect();
 *
 * // Upload
 * const url = await storage.upload('avatars/user-123.jpg', imageBuffer, 'image/jpeg');
 *
 * // Download
 * const content = await storage.download('avatars/user-123.jpg');
 *
 * // Pre-signed URL
 * const signedUrl = await storage.getPresignedUrl('documents/report.pdf', 3600);
 *
 * await storage.disconnect();
 * ```
 */

import { createHmac } from 'node:crypto';
import { loadCredential } from '../../Shared/Config/credentials';

/**
 * Object metadata structure
 */
export interface ObjectMetadata {
  size: number;
  contentType: string;
  lastModified: string;
  metadata: Record<string, string>;
}

/**
 * List object result
 */
export interface ListObject {
  key: string;
  size: number;
  lastModified: string;
}

/**
 * Storage Service Interface
 */
export interface StorageServiceInterface {
  /**
   * Connect to storage backend
   */
  connect(): Promise<void>;

  /**
   * Disconnect from storage backend
   */
  disconnect(): Promise<void>;

  /**
   * Upload content as a file
   * @returns Public URL on success, null on error
   */
  upload(
    key: string,
    content: Buffer | string,
    contentType: string,
    metadata?: Record<string, string>
  ): Promise<string | null>;

  /**
   * Download file content
   * @returns File content or null if not found
   */
  download(key: string): Promise<Buffer | null>;

  /**
   * Check if an object exists
   */
  exists(key: string): Promise<boolean>;

  /**
   * Delete an object
   * @returns true on success
   */
  delete(key: string): Promise<boolean>;

  /**
   * List objects with optional prefix
   */
  list(prefix?: string, maxKeys?: number): Promise<ListObject[]>;

  /**
   * Copy an object
   * @returns true on success
   */
  copy(sourceKey: string, destKey: string): Promise<boolean>;

  /**
   * Move an object (copy + delete)
   * @returns true on success
   */
  move(sourceKey: string, destKey: string): Promise<boolean>;

  /**
   * Get object metadata
   * @returns Metadata or null if not found
   */
  getMetadata(key: string): Promise<ObjectMetadata | null>;

  /**
   * Generate a pre-signed URL
   * @returns Pre-signed URL or null if not supported
   */
  getPresignedUrl(key: string, expiresIn?: number, method?: string): Promise<string | null>;

  /**
   * Get the public URL for an object
   */
  getPublicUrl(key: string): string;

  /**
   * Create a bucket if it doesn't exist
   * @returns true on success
   */
  createBucket(bucket?: string): Promise<boolean>;

  /**
   * Check if a bucket exists
   */
  bucketExists(bucket?: string): Promise<boolean>;

  /**
   * Check if storage is available
   */
  isAvailable(): Promise<boolean>;
}

/**
 * Storage Service Configuration
 */
export interface StorageServiceOptions {
  /** S3 endpoint URL (default: from env or http://seaweedfs:8333) */
  endpoint?: string;
  /** Access key (default: from secrets or env or admin) */
  accessKey?: string;
  /** Secret key (default: from secrets or env or admin) */
  secretKey?: string;
  /** Region (default: us-east-1) */
  region?: string;
  /** Default bucket (default: default) */
  bucket?: string;
  /** Use path-style URLs (default: true for SeaweedFS) */
  usePathStyle?: boolean;
  /** Request timeout in milliseconds (default: 60000) */
  timeout?: number;
}

/**
 * Default configuration values
 */
const DEFAULT_ENDPOINT = 'http://seaweedfs:8333';
const DEFAULT_REGION = 'us-east-1';
const DEFAULT_BUCKET = 'default';
const DEFAULT_TIMEOUT = 60000;

/**
 * Storage Service Implementation
 */
export class StorageService implements StorageServiceInterface {
  private connected = false;
  private readonly endpoint: string;
  private readonly accessKey: string;
  private readonly secretKey: string;
  private readonly region: string;
  private readonly bucket: string;
  private readonly usePathStyle: boolean;
  private readonly timeout: number;

  constructor(options: StorageServiceOptions = {}) {
    this.endpoint = (
      options.endpoint ??
      process.env.S3_ENDPOINT_URL ??
      process.env.SEAWEEDFS_ENDPOINT ??
      DEFAULT_ENDPOINT
    ).replace(/\/$/, '');

    this.accessKey =
      options.accessKey ??
      (loadCredential('seaweedfs_access_key', 'S3_ACCESS_KEY', '') ||
        loadCredential('seaweedfs_access_key', 'SEAWEEDFS_S3_ACCESS_KEY', 'admin'));

    this.secretKey =
      options.secretKey ??
      (loadCredential('seaweedfs_secret_key', 'S3_SECRET_KEY', '') ||
        loadCredential('seaweedfs_secret_key', 'SEAWEEDFS_S3_SECRET_KEY', 'admin'));

    this.region =
      options.region ?? process.env.S3_REGION ?? process.env.SEAWEEDFS_REGION ?? DEFAULT_REGION;
    this.bucket =
      options.bucket ?? process.env.S3_BUCKET ?? process.env.SEAWEEDFS_BUCKET ?? DEFAULT_BUCKET;
    this.usePathStyle = options.usePathStyle ?? true;
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

  async upload(
    key: string,
    content: Buffer | string,
    contentType: string,
    metadata: Record<string, string> = {}
  ): Promise<string | null> {
    try {
      const body = typeof content === 'string' ? Buffer.from(content) : content;

      const headers: Record<string, string> = {
        'Content-Type': contentType,
        'Content-Length': String(body.length),
      };

      // Add custom metadata
      for (const [name, value] of Object.entries(metadata)) {
        headers[`x-amz-meta-${name.toLowerCase()}`] = value;
      }

      const response = await this.request('PUT', key, body, headers);

      if (response === null) {
        return null;
      }

      return this.getPublicUrl(key);
    } catch {
      return null;
    }
  }

  async download(key: string): Promise<Buffer | null> {
    try {
      return await this.request('GET', key);
    } catch {
      return null;
    }
  }

  async exists(key: string): Promise<boolean> {
    try {
      return await this.requestHead(key);
    } catch {
      return false;
    }
  }

  async delete(key: string): Promise<boolean> {
    try {
      const response = await this.request('DELETE', key);
      return response !== null;
    } catch {
      return false;
    }
  }

  async list(prefix = '', maxKeys = 1000): Promise<ListObject[]> {
    try {
      const queryParams: Record<string, string> = {
        'list-type': '2',
        'max-keys': String(maxKeys),
      };

      if (prefix !== '') {
        queryParams.prefix = prefix;
      }

      const response = await this.request('GET', '', undefined, {}, queryParams);

      if (response === null) {
        return [];
      }

      // Parse XML response
      return this.parseListResponse(response.toString());
    } catch {
      return [];
    }
  }

  async copy(sourceKey: string, destKey: string): Promise<boolean> {
    try {
      const headers = {
        'x-amz-copy-source': `/${this.bucket}/${sourceKey.replace(/^\//, '')}`,
      };

      const response = await this.request('PUT', destKey, undefined, headers);
      return response !== null;
    } catch {
      return false;
    }
  }

  async move(sourceKey: string, destKey: string): Promise<boolean> {
    if (!(await this.copy(sourceKey, destKey))) {
      return false;
    }

    return this.delete(sourceKey);
  }

  async getMetadata(key: string): Promise<ObjectMetadata | null> {
    try {
      const url = this.buildUrl(key);
      const headers = this.signRequest('HEAD', key);

      const response = await fetch(url, {
        method: 'HEAD',
        headers,
        signal: AbortSignal.timeout(this.timeout),
      });

      if (!response.ok) {
        return null;
      }

      const metadata: Record<string, string> = {};
      response.headers.forEach((value, name) => {
        if (name.startsWith('x-amz-meta-')) {
          metadata[name.substring(11)] = value;
        }
      });

      return {
        size: parseInt(response.headers.get('content-length') ?? '0', 10),
        contentType: response.headers.get('content-type') ?? 'application/octet-stream',
        lastModified: response.headers.get('last-modified') ?? '',
        metadata,
      };
    } catch {
      return null;
    }
  }

  getPresignedUrl(key: string, expiresIn = 3600, method = 'GET'): Promise<string | null> {
    try {
      const now = new Date();
      const dateTime = this.formatDateTime(now);
      const date = this.formatDate(now);

      const parsedUrl = new URL(this.endpoint);
      const host = parsedUrl.host;
      const scheme = parsedUrl.protocol.replace(':', '');

      const path = this.usePathStyle
        ? `/${this.bucket}/${key.replace(/^\//, '')}`
        : `/${key.replace(/^\//, '')}`;

      const credential = `${this.accessKey}/${date}/${this.region}/s3/aws4_request`;

      const queryParams: Record<string, string> = {
        'X-Amz-Algorithm': 'AWS4-HMAC-SHA256',
        'X-Amz-Credential': credential,
        'X-Amz-Date': dateTime,
        'X-Amz-Expires': String(expiresIn),
        'X-Amz-SignedHeaders': 'host',
      };

      const sortedParams = Object.keys(queryParams)
        .sort()
        .map((k) => `${encodeURIComponent(k)}=${encodeURIComponent(queryParams[k] ?? '')}`)
        .join('&');

      const canonicalHeaders = `host:${host}\n`;
      const signedHeaders = 'host';

      const canonicalRequest = [
        method,
        path,
        sortedParams,
        canonicalHeaders,
        signedHeaders,
        'UNSIGNED-PAYLOAD',
      ].join('\n');

      const stringToSign = [
        'AWS4-HMAC-SHA256',
        dateTime,
        `${date}/${this.region}/s3/aws4_request`,
        this.hash(canonicalRequest),
      ].join('\n');

      const signature = this.calculateSignature(date, stringToSign);
      const finalParams = `${sortedParams}&X-Amz-Signature=${signature}`;

      return Promise.resolve(`${scheme}://${host}${path}?${finalParams}`);
    } catch {
      return Promise.resolve(null);
    }
  }

  getPublicUrl(key: string): string {
    if (this.usePathStyle) {
      return `${this.endpoint}/${this.bucket}/${key.replace(/^\//, '')}`;
    }

    const parsedUrl = new URL(this.endpoint);
    return `${parsedUrl.protocol}//${this.bucket}.${parsedUrl.host}/${key.replace(/^\//, '')}`;
  }

  async createBucket(bucket?: string): Promise<boolean> {
    try {
      const targetBucket = bucket ?? this.bucket;
      const response = await this.request('PUT', '', undefined, {}, {}, targetBucket);
      return response !== null;
    } catch {
      return false;
    }
  }

  async bucketExists(bucket?: string): Promise<boolean> {
    try {
      const targetBucket = bucket ?? this.bucket;
      return await this.requestHead('', targetBucket);
    } catch {
      return false;
    }
  }

  async isAvailable(): Promise<boolean> {
    return this.bucketExists();
  }

  /**
   * Check if client is connected (for testing)
   */
  isConnected(): boolean {
    return this.connected;
  }

  /**
   * Get the configured endpoint (for testing)
   */
  getEndpoint(): string {
    return this.endpoint;
  }

  /**
   * Execute an HTTP request to S3
   */
  private async request(
    method: string,
    key: string,
    body?: Buffer,
    extraHeaders: Record<string, string> = {},
    queryParams: Record<string, string> = {},
    bucket?: string
  ): Promise<Buffer | null> {
    const targetBucket = bucket ?? this.bucket;
    const url = this.buildUrl(key, queryParams, targetBucket);
    const headers = this.signRequest(method, key, body, extraHeaders, queryParams, targetBucket);

    const requestInit: RequestInit = {
      method,
      headers,
      signal: AbortSignal.timeout(this.timeout),
    };

    if (body !== undefined) {
      // Use Buffer directly (compatible with fetch in Node.js)
      requestInit.body = body as BodyInit;
    }

    const response = await fetch(url, requestInit);

    if (!response.ok && response.status !== 204) {
      return null;
    }

    if (response.status === 204) {
      return Buffer.alloc(0);
    }

    const arrayBuffer = await response.arrayBuffer();
    return Buffer.from(arrayBuffer);
  }

  /**
   * Execute a HEAD request
   */
  private async requestHead(key: string, bucket?: string): Promise<boolean> {
    const targetBucket = bucket ?? this.bucket;
    const url = this.buildUrl(key, {}, targetBucket);
    const headers = this.signRequest('HEAD', key, undefined, {}, {}, targetBucket);

    const response = await fetch(url, {
      method: 'HEAD',
      headers,
      signal: AbortSignal.timeout(this.timeout),
    });

    return response.ok;
  }

  /**
   * Sign a request using AWS Signature Version 4
   */
  private signRequest(
    method: string,
    key: string,
    body?: Buffer,
    extraHeaders: Record<string, string> = {},
    queryParams: Record<string, string> = {},
    bucket?: string
  ): Record<string, string> {
    const targetBucket = bucket ?? this.bucket;
    const now = new Date();
    const dateTime = this.formatDateTime(now);
    const date = this.formatDate(now);

    const parsedUrl = new URL(this.endpoint);
    const host = parsedUrl.host;

    const path = this.usePathStyle
      ? `/${targetBucket}${key !== '' ? '/' + key.replace(/^\//, '') : ''}`
      : `/${key.replace(/^\//, '')}`;

    const payloadHash = this.hash(body ?? Buffer.alloc(0));

    // Build headers
    const headers: Record<string, string> = {
      Host: host,
      'x-amz-date': dateTime,
      'x-amz-content-sha256': payloadHash,
      ...extraHeaders,
    };

    if (body !== undefined && !('Content-Length' in headers)) {
      headers['Content-Length'] = String(body.length);
    }

    // Build canonical request
    const sortedQueryParams = Object.keys(queryParams)
      .sort()
      .map((k) => `${encodeURIComponent(k)}=${encodeURIComponent(queryParams[k] ?? '')}`)
      .join('&');

    const sortedHeaders = Object.keys(headers)
      .map((k) => k.toLowerCase())
      .sort();
    const canonicalHeaders =
      sortedHeaders
        .map((k) => `${k}:${headers[this.findOriginalKey(headers, k)]?.trim()}`)
        .join('\n') + '\n';
    const signedHeadersStr = sortedHeaders.join(';');

    const canonicalRequest = [
      method,
      path,
      sortedQueryParams,
      canonicalHeaders,
      signedHeadersStr,
      payloadHash,
    ].join('\n');

    // Build string to sign
    const stringToSign = [
      'AWS4-HMAC-SHA256',
      dateTime,
      `${date}/${this.region}/s3/aws4_request`,
      this.hash(canonicalRequest),
    ].join('\n');

    // Calculate signature
    const signature = this.calculateSignature(date, stringToSign);

    // Build Authorization header
    headers['Authorization'] = [
      `AWS4-HMAC-SHA256 Credential=${this.accessKey}/${date}/${this.region}/s3/aws4_request`,
      `SignedHeaders=${signedHeadersStr}`,
      `Signature=${signature}`,
    ].join(', ');

    return headers;
  }

  /**
   * Find original key (case-sensitive) in headers object
   */
  private findOriginalKey(headers: Record<string, string>, lowerKey: string): string {
    for (const key of Object.keys(headers)) {
      if (key.toLowerCase() === lowerKey) {
        return key;
      }
    }
    return lowerKey;
  }

  /**
   * Calculate AWS Signature V4 signature
   */
  private calculateSignature(date: string, stringToSign: string): string {
    const kDate = createHmac('sha256', `AWS4${this.secretKey}`).update(date).digest();
    const kRegion = createHmac('sha256', kDate).update(this.region).digest();
    const kService = createHmac('sha256', kRegion).update('s3').digest();
    const kSigning = createHmac('sha256', kService).update('aws4_request').digest();

    return createHmac('sha256', kSigning).update(stringToSign).digest('hex');
  }

  /**
   * Hash content using SHA256
   */
  private hash(content: string | Buffer): string {
    return createHmac('sha256', '')
      .update(content)
      .digest('hex')
      .replace(/^[0-9a-f]{64}$/, (m) => m);
  }

  /**
   * Build the full URL for a request
   */
  private buildUrl(key: string, queryParams: Record<string, string> = {}, bucket?: string): string {
    const targetBucket = bucket ?? this.bucket;
    const path = this.usePathStyle
      ? `/${targetBucket}${key !== '' ? '/' + key.replace(/^\//, '') : ''}`
      : `/${key.replace(/^\//, '')}`;

    let url = `${this.endpoint}${path}`;

    const sortedParams = Object.keys(queryParams).sort();
    if (sortedParams.length > 0) {
      const qs = sortedParams
        .map((k) => `${encodeURIComponent(k)}=${encodeURIComponent(queryParams[k] ?? '')}`)
        .join('&');
      url += `?${qs}`;
    }

    return url;
  }

  /**
   * Format date for AWS signature
   */
  private formatDate(date: Date): string {
    return date.toISOString().slice(0, 10).replace(/-/g, '');
  }

  /**
   * Format datetime for AWS signature
   */
  private formatDateTime(date: Date): string {
    return date
      .toISOString()
      .replace(/[-:]/g, '')
      .replace(/\.\d{3}/, '');
  }

  /**
   * Parse S3 ListObjectsV2 XML response
   */
  private parseListResponse(xml: string): ListObject[] {
    const result: ListObject[] = [];

    // Simple XML parsing without dependencies
    const contentsRegex = /<Contents>([\s\S]*?)<\/Contents>/g;
    let match;

    while ((match = contentsRegex.exec(xml)) !== null) {
      const content = match[1] ?? '';
      const key = this.extractXmlValue(content, 'Key');
      const size = this.extractXmlValue(content, 'Size');
      const lastModified = this.extractXmlValue(content, 'LastModified');

      if (key !== null && key !== '') {
        result.push({
          key,
          size: parseInt(size ?? '0', 10),
          lastModified: lastModified ?? '',
        });
      }
    }

    return result;
  }

  /**
   * Extract value from XML element
   */
  private extractXmlValue(xml: string, tag: string): string | null {
    // eslint-disable-next-line security/detect-non-literal-regexp -- tag is internal, not user input
    const regex = new RegExp(`<${tag}>([\\s\\S]*?)<\\/${tag}>`);
    const match = regex.exec(xml);
    return match?.[1] !== undefined ? match[1] : null;
  }
}
