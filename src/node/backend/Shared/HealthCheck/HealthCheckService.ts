/**
 * Health Check Service
 *
 * Provides health check functionality for the Node.js backend.
 * Checks database, Redis, and other service dependencies.
 *
 * Response Formats:
 *
 * Liveness (/health):
 * ```json
 * { "status": "ok", "service": "node-backend", "timestamp": "..." }
 * ```
 *
 * Readiness (/ready):
 * ```json
 * {
 *   "status": "ok",
 *   "timestamp": "...",
 *   "service": "node-backend",
 *   "environment": "development",
 *   "node_version": "v24.13.0",
 *   "uptime": 12345,
 *   "checks": {
 *     "database": { "status": "ok", "type": "postgres", "latency_ms": 5 },
 *     "redis": { "status": "disabled" }
 *   }
 * }
 * ```
 */

import { createClient, RedisClientType } from 'redis';
import * as http from 'http';
import * as https from 'https';
import * as net from 'net';
import { getHttpsTlsOptions, getTlsSocketOptions } from '../Config/TlsConfig.js';
import type { ConnectionFactory } from '../Database/ConnectionFactory.js';

/**
 * Service check result
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export interface ServiceCheckResult {
  status: 'ok' | 'degraded' | 'unhealthy' | 'disabled';
  latency_ms?: number;
  type?: string;
  message?: string;
  version?: string;
}

/**
 * Liveness response
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export interface LivenessResponse {
  status: 'ok';
  service: string;
  timestamp: string;
}

/**
 * Readiness response
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export interface ReadinessResponse {
  status: 'ok' | 'degraded' | 'unhealthy';
  timestamp: string;
  service: string;
  environment: string;
  /** Node.js runtime version — consumed by the PHP health aggregator as node_version */
  node_version: string;
  uptime: number;
  checks: Record<string, ServiceCheckResult>;
}

/**
 * Status response (all services including disabled)
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export interface StatusResponse {
  timestamp: string;
  environment: string;
  services: Record<string, ServiceCheckResult>;
}

// Environment configuration
interface HealthCheckConfig {
  enableDatabase: boolean;
  enableRedis: boolean;
  enableMeilisearch: boolean;
  enableElasticsearch: boolean;
  enableRabbitmq: boolean;
  enableSeaweedfs: boolean;
  enableNodeFrontend: boolean;
  nodeFrontendHost: string;
  nodeFrontendPort: number;
  nodeMode: string;
  environment: string;
  redisUrl: string;
  databaseType: string;
  meilisearchUrl: string;
  elasticsearchUrl: string;
  rabbitmqHost: string;
  rabbitmqPort: number;
  seaweedfsMasterUrl: string;
}

// Timeouts
const CHECK_TIMEOUT_MS = 2000;
const HTTP_TIMEOUT_MS = 2000;

/**
 * Parse boolean from environment variable
 */
function parseBool(value: string | undefined, defaultValue: boolean): boolean {
  if (value === undefined || value === '') {
    return defaultValue;
  }
  return value.toLowerCase() === 'true';
}

/**
 * Get environment variable with fallback
 */
function getEnv(name: string, fallback: string): string {
  const value = process.env[name];
  return value !== undefined && value !== '' ? value : fallback;
}

/**
 * Resolve the RabbitMQ host/port for a plain TCP reachability probe.
 *
 * Prefers RABBITMQ_URL (amqp:// or amqps://) and falls back to the discrete
 * RABBITMQ_HOST / RABBITMQ_PORT variables, mirroring QueueService.
 */
function resolveRabbitmqTarget(): { host: string; port: number } {
  const url = process.env.RABBITMQ_URL;

  if (url !== undefined && url !== '') {
    try {
      const parsed = new URL(url);
      return {
        host: parsed.hostname,
        port: parsed.port !== '' ? Number(parsed.port) : 5672,
      };
    } catch {
      // Fall through to discrete host/port variables
    }
  }

  return {
    host: getEnv('RABBITMQ_HOST', 'rabbitmq'),
    port: Number(getEnv('RABBITMQ_PORT', '5672')),
  };
}

/**
 * Load health check configuration from environment
 */
function loadConfig(): HealthCheckConfig {
  const nodeMode = getEnv('NODE_MODE', 'backend');

  // Node frontend is enabled if NODE_MODE includes 'framework'
  const enableNodeFrontend = nodeMode.includes('framework');

  const rabbitmq = resolveRabbitmqTarget();

  return {
    enableDatabase: parseBool(process.env.ENABLE_DATABASE, false),
    enableRedis: parseBool(process.env.ENABLE_REDIS, false),
    enableMeilisearch: parseBool(process.env.ENABLE_MEILISEARCH, false),
    enableElasticsearch: parseBool(process.env.ENABLE_ELASTICSEARCH, false),
    enableRabbitmq: parseBool(process.env.ENABLE_RABBITMQ, false),
    enableSeaweedfs: parseBool(process.env.ENABLE_SEAWEEDFS, false),
    enableNodeFrontend,
    // The frontend hostname differs per orchestrator (Compose service name
    // vs. the release-prefixed Kubernetes Service), so it must be injectable
    nodeFrontendHost: getEnv('NODE_FRONTEND_HOST', 'node'),
    nodeFrontendPort: Number(getEnv('NODE_FRONTEND_PORT', '3001')),
    nodeMode,
    environment: getEnv('NODE_ENV', 'production'),
    redisUrl: getEnv('REDIS_URL', 'redis://redis:6379'),
    databaseType: getEnv('DB_TYPE', 'postgres'),
    meilisearchUrl: getEnv('MEILISEARCH_URL', 'https://meilisearch:7700'),
    elasticsearchUrl: getEnv('ELASTICSEARCH_URL', 'https://elasticsearch:9200'),
    rabbitmqHost: rabbitmq.host,
    rabbitmqPort: rabbitmq.port,
    seaweedfsMasterUrl: getEnv('SEAWEEDFS_MASTER_URL', 'http://seaweedfs:9333'),
  };
}

/**
 * Measure execution time of an async function
 */
async function measureLatency<T>(fn: () => Promise<T>): Promise<{ result: T; latency_ms: number }> {
  const start = performance.now();
  const result = await fn();
  const latency_ms = Math.round(performance.now() - start);
  return { result, latency_ms };
}

/**
 * Health Check Service
 *
 * Provides methods for liveness and readiness checks.
 * Can be used with or without a database connection factory.
 */
export class HealthCheckService {
  private readonly config: HealthCheckConfig;
  private readonly connectionFactory: ConnectionFactory | null;
  private readonly serviceName = 'node-backend';

  /**
   * Create a new HealthCheckService
   *
   * @param connectionFactory Optional connection factory for database checks
   *   (serves both the postgres and mariadb backends)
   */
  constructor(connectionFactory: ConnectionFactory | null = null) {
    this.config = loadConfig();
    this.connectionFactory = connectionFactory;
  }

  /**
   * Liveness check - simple, fast check that the process is running
   */
  checkLiveness(): LivenessResponse {
    return {
      status: 'ok',
      service: this.serviceName,
      timestamp: new Date().toISOString(),
    };
  }

  /**
   * Readiness check - full check of all dependencies
   */
  async checkReadiness(): Promise<ReadinessResponse> {
    const checks: Record<string, ServiceCheckResult> = {};
    let overallStatus: 'ok' | 'degraded' | 'unhealthy' = 'ok';

    // Check database
    const dbCheck = await this.checkDatabase();
    checks.database = dbCheck;
    if (dbCheck.status === 'unhealthy') {
      overallStatus = 'degraded';
    }

    // Check Redis
    const redisCheck = await this.checkRedis();
    checks.redis = redisCheck;
    if (redisCheck.status === 'unhealthy') {
      overallStatus = 'degraded';
    }

    // Check Meilisearch
    const meilisearchCheck = await this.checkMeilisearch();
    checks.meilisearch = meilisearchCheck;
    if (meilisearchCheck.status === 'unhealthy') {
      overallStatus = 'degraded';
    }

    // Check Elasticsearch
    const elasticsearchCheck = await this.checkElasticsearch();
    checks.elasticsearch = elasticsearchCheck;
    if (elasticsearchCheck.status === 'unhealthy') {
      overallStatus = 'degraded';
    }

    // Check RabbitMQ
    const rabbitmqCheck = await this.checkRabbitmq();
    checks.rabbitmq = rabbitmqCheck;
    if (rabbitmqCheck.status === 'unhealthy') {
      overallStatus = 'degraded';
    }

    // Check SeaweedFS
    const seaweedfsCheck = await this.checkSeaweedfs();
    checks.seaweedfs = seaweedfsCheck;
    if (seaweedfsCheck.status === 'unhealthy') {
      overallStatus = 'degraded';
    }

    // Check Node Frontend (if applicable)
    if (this.config.enableNodeFrontend) {
      const frontendCheck = await this.checkNodeFrontend();
      checks['node-frontend'] = frontendCheck;
      if (frontendCheck.status === 'unhealthy') {
        overallStatus = 'degraded';
      }
    }

    return {
      status: overallStatus,
      timestamp: new Date().toISOString(),
      service: this.serviceName,
      environment: this.config.environment,
      node_version: process.version,
      uptime: Math.round(process.uptime()),
      checks,
    };
  }

  /**
   * Status check - all services including disabled ones
   */
  async checkStatus(): Promise<StatusResponse> {
    const services: Record<string, ServiceCheckResult> = {};

    // Database
    services.database = await this.checkDatabase();

    // Redis
    services.redis = await this.checkRedis();

    // Meilisearch
    services.meilisearch = await this.checkMeilisearch();

    // Elasticsearch
    services.elasticsearch = await this.checkElasticsearch();

    // RabbitMQ
    services.rabbitmq = await this.checkRabbitmq();

    // SeaweedFS
    services.seaweedfs = await this.checkSeaweedfs();

    // Node Frontend
    services['node-frontend'] = this.config.enableNodeFrontend
      ? await this.checkNodeFrontend()
      : { status: 'disabled' };

    return {
      timestamp: new Date().toISOString(),
      environment: this.config.environment,
      services,
    };
  }

  /**
   * Check database connection
   *
   * Probes through the connection factory's unified API, so the check works
   * against both the postgres and mariadb backends.
   */
  private async checkDatabase(): Promise<ServiceCheckResult> {
    if (!this.config.enableDatabase) {
      return { status: 'disabled' };
    }

    if (!this.connectionFactory) {
      return {
        status: 'unhealthy',
        type: this.config.databaseType,
        message: 'Database connection not configured',
      };
    }

    try {
      const { latency_ms } = await measureLatency(async () => {
        const connection = await Promise.race([
          this.connectionFactory!.create(),
          new Promise<never>((_, reject) =>
            setTimeout(() => reject(new Error('Connection timeout')), CHECK_TIMEOUT_MS)
          ),
        ]);
        try {
          await connection.query('SELECT 1');
        } finally {
          connection.release?.();
        }
      });

      return {
        status: 'ok',
        type: this.config.databaseType,
        latency_ms,
      };
    } catch (error) {
      return {
        status: 'unhealthy',
        type: this.config.databaseType,
        message: this.safeErrorMessage(error),
      };
    }
  }

  /**
   * Check Redis connection
   */
  private async checkRedis(): Promise<ServiceCheckResult> {
    if (!this.config.enableRedis) {
      return { status: 'disabled' };
    }

    let client: RedisClientType | null = null;

    try {
      const { latency_ms } = await measureLatency(async () => {
        // Determine if TLS is needed
        const useTls = this.config.redisUrl.startsWith('rediss://');

        client = createClient({
          url: this.config.redisUrl,
          socket: {
            connectTimeout: CHECK_TIMEOUT_MS,
            // A health probe must fail fast: without this, the client's
            // default strategy retries a dead endpoint indefinitely and
            // connect() never settles, so the readiness response hangs
            reconnectStrategy: false,
            ...(useTls && getTlsSocketOptions()),
          },
        });

        await client.connect();
        await client.ping();
      });

      return {
        status: 'ok',
        latency_ms,
      };
    } catch (error) {
      return {
        status: 'unhealthy',
        message: this.safeErrorMessage(error),
      };
    } finally {
      // eslint-disable-next-line @typescript-eslint/no-unnecessary-condition -- client is assigned in measureLatency callback
      if (client !== null) {
        try {
          await (client as RedisClientType).quit();
        } catch {
          // Ignore cleanup errors
        }
      }
    }
  }

  /**
   * Perform a simple HTTP(S) GET and resolve with status code and body.
   *
   * Honors internal TLS options and the shared request timeout. Used by the
   * HTTP-based service probes (Meilisearch, Elasticsearch, SeaweedFS).
   */
  private httpGet(rawUrl: string): Promise<{ statusCode: number; body: string }> {
    return new Promise((resolve, reject) => {
      const url = new URL(rawUrl);
      const isHttps = url.protocol === 'https:';
      const options = {
        hostname: url.hostname,
        port: url.port || (isHttps ? 443 : 80),
        path: `${url.pathname}${url.search}`,
        method: 'GET',
        timeout: HTTP_TIMEOUT_MS,
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

      req.end();
    });
  }

  /**
   * Probe a plain TCP connection to a host/port.
   *
   * Verifies the port is accepting connections without speaking the wire
   * protocol — the same reachability signal the PHP health check uses for
   * RabbitMQ.
   */
  private checkTcp(host: string, port: number): Promise<void> {
    return new Promise((resolve, reject) => {
      const socket = new net.Socket();

      const fail = (err: Error): void => {
        socket.destroy();
        reject(err);
      };

      socket.setTimeout(CHECK_TIMEOUT_MS);
      socket.once('error', fail);
      socket.once('timeout', () => fail(new Error('Connection timeout')));
      socket.connect(port, host, () => {
        socket.end();
        resolve();
      });
    });
  }

  /**
   * Check Meilisearch connection via health endpoint
   */
  private async checkMeilisearch(): Promise<ServiceCheckResult> {
    if (!this.config.enableMeilisearch) {
      return { status: 'disabled' };
    }

    try {
      const { result, latency_ms } = await measureLatency(() =>
        this.httpGet(`${this.config.meilisearchUrl}/health`)
      );

      const json = JSON.parse(result.body) as { status?: string };
      if (json.status !== 'available') {
        return {
          status: 'unhealthy',
          message:
            this.config.environment === 'production'
              ? 'Service unavailable'
              : `Meilisearch status: ${json.status ?? 'unknown'}`,
        };
      }

      return {
        status: 'ok',
        latency_ms,
      };
    } catch (error) {
      return {
        status: 'unhealthy',
        message: this.safeErrorMessage(error),
      };
    }
  }

  /**
   * Check Elasticsearch connection via cluster health endpoint.
   *
   * A green or yellow cluster status is treated as healthy (yellow is normal
   * for a single-node dev cluster with unassigned replica shards).
   */
  private async checkElasticsearch(): Promise<ServiceCheckResult> {
    if (!this.config.enableElasticsearch) {
      return { status: 'disabled' };
    }

    try {
      const { result, latency_ms } = await measureLatency(() =>
        this.httpGet(`${this.config.elasticsearchUrl}/_cluster/health`)
      );

      const json = JSON.parse(result.body) as { status?: string };
      const clusterStatus = json.status ?? 'unknown';
      if (clusterStatus !== 'green' && clusterStatus !== 'yellow') {
        return {
          status: 'unhealthy',
          message:
            this.config.environment === 'production'
              ? 'Service unavailable'
              : `Elasticsearch cluster status: ${clusterStatus}`,
        };
      }

      return {
        status: 'ok',
        latency_ms,
      };
    } catch (error) {
      return {
        status: 'unhealthy',
        message: this.safeErrorMessage(error),
      };
    }
  }

  /**
   * Check RabbitMQ reachability via a plain TCP connect to the AMQP port.
   */
  private async checkRabbitmq(): Promise<ServiceCheckResult> {
    if (!this.config.enableRabbitmq) {
      return { status: 'disabled' };
    }

    try {
      const { latency_ms } = await measureLatency(() =>
        this.checkTcp(this.config.rabbitmqHost, this.config.rabbitmqPort)
      );

      return {
        status: 'ok',
        latency_ms,
      };
    } catch (error) {
      return {
        status: 'unhealthy',
        message: this.safeErrorMessage(error),
      };
    }
  }

  /**
   * Check SeaweedFS connection via the master cluster status endpoint.
   *
   * The master reports the elected leader via `IsLeader`; its presence
   * signals a functioning cluster.
   */
  private async checkSeaweedfs(): Promise<ServiceCheckResult> {
    if (!this.config.enableSeaweedfs) {
      return { status: 'disabled' };
    }

    try {
      const { result, latency_ms } = await measureLatency(() =>
        this.httpGet(`${this.config.seaweedfsMasterUrl}/cluster/status`)
      );

      const json = JSON.parse(result.body) as { IsLeader?: boolean };
      if (json.IsLeader === undefined) {
        return {
          status: 'unhealthy',
          message: 'SeaweedFS cluster status unavailable',
        };
      }

      return {
        status: 'ok',
        latency_ms,
      };
    } catch (error) {
      return {
        status: 'unhealthy',
        message: this.safeErrorMessage(error),
      };
    }
  }

  /**
   * Check Node Frontend (Nuxt/Next.js on port 3001)
   */
  private async checkNodeFrontend(): Promise<ServiceCheckResult> {
    try {
      const { latency_ms } = await measureLatency(async () => {
        return new Promise<void>((resolve, reject) => {
          // Use HTTPS for internal TLS
          const options = {
            hostname: this.config.nodeFrontendHost,
            port: this.config.nodeFrontendPort,
            path: '/',
            method: 'HEAD',
            timeout: HTTP_TIMEOUT_MS,
            ...getHttpsTlsOptions(),
          };

          const req = https.request(options, (res) => {
            // Any response means the server is running
            if (res.statusCode !== undefined && res.statusCode < 500) {
              resolve();
            } else {
              reject(new Error(`HTTP ${res.statusCode}`));
            }
          });

          req.on('error', (err) => {
            // Try HTTP as fallback (dev mode might not have TLS)
            const httpOptions = { ...options };
            const httpReq = http.request(httpOptions, (res) => {
              if (res.statusCode !== undefined && res.statusCode < 500) {
                resolve();
              } else {
                reject(new Error(`HTTP ${res.statusCode}`));
              }
            });

            httpReq.on('error', () => reject(err));
            httpReq.on('timeout', () => {
              httpReq.destroy();
              reject(new Error('Request timeout'));
            });
            httpReq.end();
          });

          req.on('timeout', () => {
            req.destroy();
            reject(new Error('Request timeout'));
          });

          req.end();
        });
      });

      return {
        status: 'ok',
        latency_ms,
      };
    } catch (error) {
      return {
        status: 'unhealthy',
        message: this.safeErrorMessage(error),
      };
    }
  }

  /**
   * Get service name
   */
  getServiceName(): string {
    return this.serviceName;
  }

  /**
   * Get current configuration
   */
  getConfig(): HealthCheckConfig {
    return { ...this.config };
  }

  /**
   * Sanitize an error message for the API response: the real message in
   * development, a generic string in production (the real error is logged
   * server-side for diagnostics). Prevents leaking internal IPs/DNS/TLS
   * detail through the unauthenticated readiness/status responses.
   */
  private safeErrorMessage(error: unknown): string {
    const realMessage = error instanceof Error ? error.message : 'Unknown error';
    if (this.config.environment !== 'production') {
      return realMessage;
    }
    console.error('[HealthCheck] probe failed:', realMessage);
    return 'Connection failed';
  }
}
