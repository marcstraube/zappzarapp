/**
 * Express Application Configuration
 *
 * Separated from server.ts to make it testable.
 * This file exports the Express app without starting the server.
 */

import express, { Express, Request, Response, NextFunction } from 'express';
import pino from 'pino';
import pinoHttp from 'pino-http';
import { HealthCheckService } from './Shared/HealthCheck/HealthCheckService.js';
import type { ConnectionFactory } from './Shared/Database/ConnectionFactory.js';
import { createAppRouter } from './App/index.js';
import { createDevDashboardRouter } from './DevDashboard/index.js';

const LOG_LEVEL = process.env.LOG_LEVEL ?? 'info';
const LOG_FORMAT = process.env.LOG_FORMAT ?? 'json';

// Configure Pino logger (structured logging)
export const logger = pino({
  level: LOG_LEVEL,
  transport:
    LOG_FORMAT === 'pretty'
      ? {
          target: 'pino-pretty',
          options: {
            colorize: true,
            translateTime: 'HH:MM:ss Z',
            ignore: 'pid,hostname',
          },
        }
      : undefined,
  formatters: {
    level: (label: string) => {
      return { level: label.toUpperCase() };
    },
  },
  timestamp: pino.stdTimeFunctions.isoTime,
});

/**
 * Application options for dependency injection
 *
 * @public Boilerplate API — shipped for consumers, no in-tree importer expected.
 */
export interface AppOptions {
  /** Connection factory for the readiness database probe (both engines) */
  connectionFactory?: ConnectionFactory | null;
}

export function createApp(options: AppOptions = {}): Express {
  // Read environment at call time to support test overrides
  const NODE_ENV = process.env.NODE_ENV ?? 'production';
  // Default is same-origin only - cross-origin access is an explicit opt-in
  const CORS_ORIGINS = process.env.CORS_ORIGINS ?? '';

  const app: Express = express();
  // Framework fingerprinting: never advertise Express in response headers
  app.disable('x-powered-by');
  const healthCheckService = new HealthCheckService(options.connectionFactory ?? null);

  // CORS Configuration Warnings
  if (CORS_ORIGINS === '*') {
    logger.warn(
      {
        corsOrigins: CORS_ORIGINS,
        credentials: 'disabled-for-wildcard',
        environment: NODE_ENV,
      },
      'CORS configured with wildcard (*) - credentials disabled for browser compatibility'
    );

    if (NODE_ENV === 'production') {
      logger.error(
        {
          corsOrigins: CORS_ORIGINS,
          environment: NODE_ENV,
          severity: 'CRITICAL',
        },
        '⚠️  SECURITY RISK: CORS_ORIGINS=* in production! Set specific origins immediately.'
      );
    }
  }

  // HTTP request logging middleware
  app.use(
    pinoHttp({
      logger,
      customLogLevel: (_req: Request, res: Response, err?: Error) => {
        if (res.statusCode >= 500 || err) {
          return 'error';
        }
        if (res.statusCode >= 400) {
          return 'warn';
        }
        return 'info';
      },
      customSuccessMessage: (req: Request, res: Response) => {
        return `${req.method} ${req.url} ${res.statusCode}`;
      },
      customErrorMessage: (_req: Request, _res: Response, err: Error) => {
        return `Request error: ${err.message}`;
      },
    })
  );

  // Middleware
  app.use(express.json());
  app.use(express.urlencoded({ extended: true }));

  // CORS - configurable via CORS_ORIGINS environment variable
  // In production, set CORS_ORIGINS to your allowed domains (comma-separated)
  // Example: CORS_ORIGINS=https://example.com,https://app.example.com
  // Empty/unset: no CORS headers at all (same-origin only)
  app.use((req: Request, res: Response, next: NextFunction): void => {
    if (CORS_ORIGINS === '') {
      next();
      return;
    }

    const origin = req.headers.origin ?? '';
    const allowedOrigins = CORS_ORIGINS.split(',').map((o) => o.trim());

    // Responses differ per Origin in allowlist mode - shared caches must
    // never serve one origin's CORS response to another
    if (CORS_ORIGINS !== '*') {
      res.header('Vary', 'Origin');
    }

    // Check if origin is allowed (or if wildcard is used)
    if (CORS_ORIGINS === '*' || (origin !== '' && allowedOrigins.includes(origin))) {
      // The origin is reflected only after it passed the allowlist check above;
      // the wildcard ('*') is an explicit opt-in via CORS_ORIGINS.
      // nosemgrep: cors-misconfiguration
      res.header('Access-Control-Allow-Origin', CORS_ORIGINS === '*' ? '*' : origin);
    }

    res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

    // Credentials never combine with the wildcard: browsers reject the pair,
    // and a permissive origin with credentials would be a misconfiguration in
    // any environment
    if (CORS_ORIGINS !== '*') {
      res.header('Access-Control-Allow-Credentials', 'true');
    }

    if (req.method === 'OPTIONS') {
      res.sendStatus(200);
      return;
    }

    next();
  });

  // Security Headers
  app.use((_req: Request, res: Response, next: NextFunction): void => {
    res.setHeader('X-Content-Type-Options', 'nosniff');
    res.setHeader('X-Frame-Options', 'SAMEORIGIN');
    // "0" per OWASP: the legacy XSS auditor enables XS-Leaks in old browsers
    res.setHeader('X-XSS-Protection', '0');
    res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    // Cross-Origin Isolation Headers (Spectre mitigation)
    res.setHeader('Cross-Origin-Opener-Policy', 'same-origin');
    res.setHeader('Cross-Origin-Resource-Policy', 'same-origin');
    next();
  });

  // Health Check Routes

  // Liveness probe - simple, fast check that the process is running
  app.get('/health', (_req: Request, res: Response): void => {
    res.json(healthCheckService.checkLiveness());
  });

  // Readiness probe - full check of all dependencies
  app.get('/ready', async (_req: Request, res: Response): Promise<void> => {
    const result = await healthCheckService.checkReadiness();
    const statusCode = result.status === 'ok' ? 200 : 503;
    res.status(statusCode).json(result);
  });

  // Status overview - development/staging only.
  // Enumerates every backing service by name, which aids topology recon;
  // orchestrators only need /health and /ready in production.
  if (NODE_ENV !== 'production') {
    app.get('/status', async (_req: Request, res: Response): Promise<void> => {
      const result = await healthCheckService.checkStatus();
      res.json(result);
    });
  }

  // App API routes
  app.use('/api', createAppRouter());

  // DevDashboard routes (development only)
  if (NODE_ENV === 'development') {
    app.use('/dev-dashboard/node', createDevDashboardRouter());
    logger.info('DevDashboard routes loaded at /dev-dashboard/node');
  }

  // Test endpoint for error handling (only for testing, not available in production)
  if (NODE_ENV !== 'production') {
    app.get('/test-error', (_req: Request, _res: Response, next: NextFunction): void => {
      next(new Error('Test error message'));
    });
  }

  // 404 Handler
  app.use((req: Request, res: Response): void => {
    res.status(404).json({
      error: 'Not Found',
      path: req.path,
      method: req.method,
    });
  });

  // Error Handler
  app.use((err: Error, req: Request, res: Response, _next: NextFunction): void => {
    logger.error(
      {
        err,
        url: req.url,
        method: req.method,
      },
      'Request error'
    );

    res.status(500).json({
      error: NODE_ENV === 'development' ? err.message : 'Internal Server Error',
      timestamp: new Date().toISOString(),
    });
  });

  return app;
}
