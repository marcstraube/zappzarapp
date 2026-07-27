/**
 * Mercure Service for publishing real-time updates
 *
 * Thin abstraction over the Mercure hub's publish endpoint. Updates are pushed
 * to subscribers over Server-Sent Events (SSE). Authenticates with a short JWT
 * (HS256) signed with the shared publisher key, granting the "publish all
 * topics" claim.
 *
 * Configuration:
 * - MERCURE_URL / MERCURE_PUBLISH_URL: Publish endpoint
 *   (default: https://mercure/.well-known/mercure)
 * - JWT key is loaded from Docker secrets or environment:
 *   - /run/secrets/mercure_jwt_secret.txt or /tmp/secrets/mercure_jwt_secret.txt
 *   - MERCURE_JWT_SECRET / MERCURE_PUBLISHER_JWT_KEY environment variable
 *
 * Usage:
 * ```typescript
 * const mercure = new MercureService();
 *
 * // Public update to a single topic
 * await mercure.publish('https://example.com/books/1', JSON.stringify({ status: 'sold' }));
 *
 * // Private update to several topics with SSE metadata
 * await mercure.publish(
 *   ['https://example.com/users/1', 'https://example.com/users/2'],
 *   JSON.stringify({ body: 'Hello' }),
 *   { private: true, type: 'chat', id: 'msg-42' }
 * );
 * ```
 *
 * Browser subscriber (SSE) example:
 * ```javascript
 * const url = new URL('https://example.com/.well-known/mercure');
 * url.searchParams.append('topic', 'https://example.com/books/1');
 * const es = new EventSource(url, { withCredentials: true });
 * es.onmessage = (event) => console.log(JSON.parse(event.data));
 * ```
 */

import * as http from 'http';
import * as https from 'https';
import { createHmac } from 'crypto';
import { loadCredential } from '../../Shared/Config/credentials';
import { getHttpsTlsOptions } from '../../Shared/Config/TlsConfig';

/** Default internal hub publish endpoint (Caddy on :443, zero-trust TLS) */
const DEFAULT_URL = 'https://mercure/.well-known/mercure';

/** Default request timeout in milliseconds */
const DEFAULT_TIMEOUT_MS = 5000;

/**
 * Options for a single published update
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export interface MercurePublishOptions {
  /** Dispatch as a private update (authorized subscribers only) */
  private?: boolean;
  /** SSE event id (the hub generates one when omitted) */
  id?: string;
  /** SSE event type */
  type?: string;
  /** SSE reconnection time in milliseconds */
  retry?: number;
}

/**
 * MercureService constructor options
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export interface MercureServiceOptions {
  /** Hub publish URL (default: from env or https://mercure/.well-known/mercure) */
  url?: string;
  /** Publisher JWT key (default: from Docker secret or env) */
  jwtKey?: string;
  /** Request timeout in milliseconds (default: 5000) */
  timeout?: number;
}

/**
 * Mercure publisher interface
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export interface MercureServiceInterface {
  publish(
    topics: string | string[],
    data: string,
    options?: MercurePublishOptions
  ): Promise<string>;
  isAvailable(): Promise<boolean>;
}

/**
 * Error thrown when publishing to the Mercure hub fails
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export class MercureError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'MercureError';
  }
}

/**
 * Mercure Service
 *
 * Publishes updates to the Mercure hub over HTTP.
 */
export class MercureService implements MercureServiceInterface {
  private readonly url: string;
  private readonly jwtKey: string;
  private readonly timeout: number;

  constructor(options: MercureServiceOptions = {}) {
    this.url =
      options.url ?? process.env.MERCURE_URL ?? process.env.MERCURE_PUBLISH_URL ?? DEFAULT_URL;
    this.jwtKey =
      options.jwtKey ??
      loadCredential(
        'mercure_jwt_secret',
        'MERCURE_JWT_SECRET',
        process.env.MERCURE_PUBLISHER_JWT_KEY ?? ''
      );
    this.timeout = options.timeout ?? DEFAULT_TIMEOUT_MS;
  }

  /**
   * Publish an update to one or more topics
   *
   * @param topics One topic or a list of topics (IRIs)
   * @param data The update payload (already serialized, e.g. JSON)
   * @param options Private flag and SSE metadata (id, type, retry)
   * @returns The event id assigned by the hub
   * @throws {MercureError} If the hub is unreachable or returns a non-2xx status
   */
  async publish(
    topics: string | string[],
    data: string,
    options: MercurePublishOptions = {}
  ): Promise<string> {
    if (this.jwtKey === '') {
      throw new MercureError('Mercure JWT key is not configured');
    }

    const body = this.buildBody(topics, data, options);

    let response: { statusCode: number; body: string };
    try {
      response = await this.request('POST', body, {
        Authorization: `Bearer ${this.generateToken()}`,
        'Content-Type': 'application/x-www-form-urlencoded',
      });
    } catch (error) {
      throw new MercureError(
        `Mercure hub not reachable: ${error instanceof Error ? error.message : 'unknown error'}`
      );
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw new MercureError(`Mercure hub returned HTTP ${response.statusCode}`);
    }

    return response.body.trim();
  }

  /**
   * Check whether the Mercure hub is reachable
   *
   * @returns True if the hub responds
   */
  async isAvailable(): Promise<boolean> {
    try {
      const response = await this.request('GET', '', {});
      return response.statusCode > 0;
    } catch {
      return false;
    }
  }

  /**
   * Build the application/x-www-form-urlencoded publish body
   */
  private buildBody(
    topics: string | string[],
    data: string,
    options: MercurePublishOptions
  ): string {
    const topicList = Array.isArray(topics) ? topics : [topics];

    if (topicList.length === 0) {
      throw new MercureError('At least one topic is required');
    }

    const params = new URLSearchParams();
    for (const topic of topicList) {
      params.append('topic', topic);
    }
    params.append('data', data);

    if (options.private === true) {
      params.append('private', 'on');
    }
    if (options.id !== undefined && options.id !== '') {
      params.append('id', options.id);
    }
    if (options.type !== undefined && options.type !== '') {
      params.append('type', options.type);
    }
    if (options.retry !== undefined) {
      params.append('retry', String(options.retry));
    }

    return params.toString();
  }

  /**
   * Generate a short-lived publisher JWT (HS256) granting publish on all topics
   */
  private generateToken(): string {
    const header = this.base64Url(JSON.stringify({ alg: 'HS256', typ: 'JWT' }));
    const payload = this.base64Url(JSON.stringify({ mercure: { publish: ['*'] } }));
    const signingInput = `${header}.${payload}`;
    const signature = createHmac('sha256', this.jwtKey).update(signingInput).digest('base64url');

    return `${signingInput}.${signature}`;
  }

  /**
   * URL-safe base64 without padding (RFC 7515 base64url)
   */
  private base64Url(input: string): string {
    return Buffer.from(input).toString('base64url');
  }

  /**
   * Send an HTTP request to the hub
   */
  private request(
    method: 'GET' | 'POST',
    body: string,
    headers: Record<string, string>
  ): Promise<{ statusCode: number; body: string }> {
    return new Promise((resolve, reject) => {
      const url = new URL(this.url);
      const isHttps = url.protocol === 'https:';
      const options = {
        hostname: url.hostname,
        port: url.port || (isHttps ? 443 : 80),
        path: `${url.pathname}${url.search}`,
        method,
        timeout: this.timeout,
        headers: {
          ...headers,
          'Content-Length': Buffer.byteLength(body),
        },
        ...getHttpsTlsOptions(),
      };

      const transport = isHttps ? https : http;
      const req = transport.request(options, (res) => {
        let data = '';
        res.on('data', (chunk: Buffer) => {
          data += chunk.toString();
        });
        res.on('end', () => resolve({ statusCode: res.statusCode ?? 0, body: data }));
      });

      req.on('error', (err) => reject(err));
      req.on('timeout', () => {
        req.destroy();
        reject(new Error('Request timeout'));
      });

      if (body !== '') {
        req.write(body);
      }
      req.end();
    });
  }
}
