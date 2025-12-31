/**
 * Express Application Configuration
 *
 * Separated from server.ts to make it testable.
 * This file exports the Express app without starting the server.
 */

import express, { Express, Request, Response, NextFunction } from 'express';
import pino from 'pino';
import pinoHttp from 'pino-http';

const NODE_ENV = process.env.NODE_ENV ?? 'production';
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

export function createApp(): Express {
  const app: Express = express();

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

  // CORS (if needed)
  app.use((req: Request, res: Response, next: NextFunction): void => {
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    res.header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

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
    next();
  });

  // Routes
  app.get('/health', (_req: Request, res: Response): void => {
    res.json({
      status: 'ok',
      service: 'node-backend',
      timestamp: new Date().toISOString(),
      uptime: process.uptime(),
      node_version: process.version,
      environment: NODE_ENV,
    });
  });

  app.get('/api/hello', (req: Request, res: Response): void => {
    const nameParam = req.query.name;
    const name = typeof nameParam === 'string' && nameParam.length > 0 ? nameParam : 'World';
    res.json({
      message: `Hello, ${name}!`,
      timestamp: new Date().toISOString(),
      server: 'Node.js + Express',
    });
  });

  // Echo endpoint (POST only - REST-compliant)
  // Test with: curl -X POST http://localhost:8080/api/node/echo -H "Content-Type: application/json" -d '{"test": "data"}'
  app.post('/api/echo', (req: Request, res: Response): void => {
    const body: unknown = req.body;
    res.json({
      echo: body,
      timestamp: new Date().toISOString(),
    });
  });

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
