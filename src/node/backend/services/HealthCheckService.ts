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
 *   "uptime": 12345,
 *   "checks": {
 *     "database": { "status": "ok", "type": "postgres", "latency_ms": 5 },
 *     "redis": { "status": "disabled" }
 *   }
 * }
 * ```
 */

import { Pool } from 'pg';
import { createClient, RedisClientType } from 'redis';
import * as http from 'http';
import * as https from 'https';
import { getHttpsTlsOptions, getTlsSocketOptions } from '../utils/tls';

// Service check result
export interface ServiceCheckResult {
  status: 'ok' | 'degraded' | 'unhealthy' | 'disabled';
  latency_ms?: number;
  type?: string;
  message?: string;
  version?: string;
}

// Liveness response
export interface LivenessResponse {
  status: 'ok';
  service: string;
  timestamp: string;
}

// Readiness response
export interface ReadinessResponse {
  status: 'ok' | 'degraded' | 'unhealthy';
  timestamp: string;
  service: string;
  environment: string;
  uptime: number;
  checks: Record<string, ServiceCheckResult>;
}

// Status response (all services including disabled)
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
  enableNodeFrontend: boolean;
  nodeMode: string;
  environment: string;
  redisUrl: string;
  databaseType: string;
  meilisearchUrl: string;
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
 * Load health check configuration from environment
 */
function loadConfig(): HealthCheckConfig {
  const nodeMode = getEnv('NODE_MODE', 'backend');

  // Node frontend is enabled if NODE_MODE includes 'framework'
  const enableNodeFrontend = nodeMode.includes('framework');

  return {
    enableDatabase: parseBool(process.env.ENABLE_DATABASE, false),
    enableRedis: parseBool(process.env.ENABLE_REDIS, false),
    enableMeilisearch: parseBool(process.env.ENABLE_MEILISEARCH, false),
    enableNodeFrontend,
    nodeMode,
    environment: getEnv('NODE_ENV', 'production'),
    redisUrl: getEnv('REDIS_URL', 'redis://redis:6379'),
    databaseType: getEnv('DB_TYPE', 'postgres'),
    meilisearchUrl: getEnv('MEILISEARCH_URL', 'https://meilisearch:7700'),
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
 * Can be used with or without a database pool.
 */
export class HealthCheckService {
  private readonly config: HealthCheckConfig;
  private readonly pool: Pool | null;
  private readonly serviceName = 'node-backend';

  /**
   * Create a new HealthCheckService
   *
   * @param pool Optional PostgreSQL connection pool for database checks
   */
  constructor(pool: Pool | null = null) {
    this.config = loadConfig();
    this.pool = pool;
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
   */
  private async checkDatabase(): Promise<ServiceCheckResult> {
    if (!this.config.enableDatabase) {
      return { status: 'disabled' };
    }

    if (!this.pool) {
      return {
        status: 'unhealthy',
        type: this.config.databaseType,
        message: 'Database pool not configured',
      };
    }

    try {
      const { latency_ms } = await measureLatency(async () => {
        const client = await Promise.race([
          this.pool!.connect(),
          new Promise<never>((_, reject) =>
            setTimeout(() => reject(new Error('Connection timeout')), CHECK_TIMEOUT_MS)
          ),
        ]);
        try {
          await client.query('SELECT 1');
        } finally {
          client.release();
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
        message: error instanceof Error ? error.message : 'Unknown error',
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
        message: error instanceof Error ? error.message : 'Unknown error',
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
   * Check Meilisearch connection via health endpoint
   */
  private async checkMeilisearch(): Promise<ServiceCheckResult> {
    if (!this.config.enableMeilisearch) {
      return { status: 'disabled' };
    }

    try {
      const { latency_ms } = await measureLatency(async () => {
        return new Promise<void>((resolve, reject) => {
          const url = new URL(this.config.meilisearchUrl);
          const isHttps = url.protocol === 'https:';
          const options = {
            hostname: url.hostname,
            port: url.port || (isHttps ? 443 : 80),
            path: '/health',
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
            res.on('end', () => {
              try {
                const json = JSON.parse(data) as { status?: string };
                if (json.status === 'available') {
                  resolve();
                } else {
                  reject(new Error(`Meilisearch status: ${json.status ?? 'unknown'}`));
                }
              } catch {
                reject(new Error('Invalid JSON response'));
              }
            });
          });

          req.on('error', (err) => reject(err));
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
        message: error instanceof Error ? error.message : 'Unknown error',
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
            hostname: 'node',
            port: 3001,
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
        message: error instanceof Error ? error.message : 'Unknown error',
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
}
