/**
 * Express Application Configuration
 *
 * Separated from server.ts to make it testable.
 * This file exports the Express app without starting the server.
 */

import express, { Express, Request, Response, NextFunction } from 'express';
import pino from 'pino';
import pinoHttp from 'pino-http';
import { Pool } from 'pg';
import { HealthCheckService } from './Shared/HealthCheck/HealthCheckService.js';
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
 */
export interface AppOptions {
  pool?: Pool | null;
}

export function createApp(options: AppOptions = {}): Express {
  // Read environment at call time to support test overrides
  const NODE_ENV = process.env.NODE_ENV ?? 'production';
  const CORS_ORIGINS = process.env.CORS_ORIGINS ?? '*';

  const app: Express = express();
  const healthCheckService = new HealthCheckService(options.pool ?? null);

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
  app.use((req: Request, res: Response, next: NextFunction): void => {
    const origin = req.headers.origin ?? '';
    const allowedOrigins = CORS_ORIGINS.split(',').map((o) => o.trim());

    // Check if origin is allowed (or if wildcard is used)
    if (CORS_ORIGINS === '*' || allowedOrigins.includes(origin)) {
      res.header('Access-Control-Allow-Origin', CORS_ORIGINS === '*' ? '*' : origin);
    }

    res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

    // Credentials: Always in production, conditional in development
    // Wildcard (*) + credentials = browser rejection, so we disable credentials for wildcard
    if (NODE_ENV === 'production' || CORS_ORIGINS !== '*') {
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
    res.setHeader('X-XSS-Protection', '1; mode=block');
    res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    // Cross-Origin Isolation Headers (Spectre mitigation)
    res.setHeader('Cross-Origin-Opener-Policy', 'same-origin');
    res.setHeader('Cross-Origin-Resource-Policy', 'same-site');
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

  // Status overview - all services including disabled ones
  app.get('/status', async (_req: Request, res: Response): Promise<void> => {
    const result = await healthCheckService.checkStatus();
    res.json(result);
  });

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
