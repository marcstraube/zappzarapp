/**
 * Node.js Backend Server
 *
 * This is an example Express.js server with structured logging (Pino).
 * Only used when NODE_TARGET=app-server in docker-compose.
 *
 * Build output: dist/server.js
 * Runtime: Node.js container on port 3000
 */

import express, { Express, Request, Response, NextFunction } from 'express';
import { createServer, Server } from 'http';
import pino from 'pino';
import pinoHttpImport from 'pino-http';

// TypeScript workaround for pino-http CommonJS module
const pinoHttp = pinoHttpImport as unknown as typeof pinoHttpImport.default;

const app: Express = express();
const PORT = process.env.PORT || 3000;
const NODE_ENV = process.env.NODE_ENV || 'production';
const LOG_LEVEL = process.env.LOG_LEVEL || 'info';
const LOG_FORMAT = process.env.LOG_FORMAT || 'json';

// Configure Pino logger (structured logging)
const logger = pino({
    level: LOG_LEVEL,
    transport: LOG_FORMAT === 'pretty' ? {
        target: 'pino-pretty',
        options: {
            colorize: true,
            translateTime: 'HH:MM:ss Z',
            ignore: 'pid,hostname',
        },
    } : undefined,
    formatters: {
        level: (label: string) => {
            return { level: label.toUpperCase() };
        },
    },
    timestamp: pino.stdTimeFunctions.isoTime,
});

// HTTP request logging middleware
app.use(pinoHttp({
    logger,
    customLogLevel: (_req: Request, res: Response, err?: Error) => {
        if (res.statusCode >= 500 || err) return 'error';
        if (res.statusCode >= 400) return 'warn';
        return 'info';
    },
    customSuccessMessage: (req: Request, res: Response) => {
        return `${req.method} ${req.url} ${res.statusCode}`;
    },
    customErrorMessage: (_req: Request, _res: Response, err: Error) => {
        return `Request error: ${err.message}`;
    },
}));

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
    const name = req.query.name || 'World';
    res.json({
        message: `Hello, ${name}!`,
        timestamp: new Date().toISOString(),
        server: 'Node.js + Express',
    });
});

// Example POST endpoint
app.post('/api/echo', (req: Request, res: Response): void => {
    res.json({
        echo: req.body,
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
    logger.error({
        err,
        url: req.url,
        method: req.method,
    }, 'Request error');

    res.status(500).json({
        error: NODE_ENV === 'development' ? err.message : 'Internal Server Error',
        timestamp: new Date().toISOString(),
    });
});

// Start Server
const server: Server = createServer(app);

server.listen(PORT, (): void => {
    logger.info({
        port: PORT,
        environment: NODE_ENV,
        logLevel: LOG_LEVEL,
        logFormat: LOG_FORMAT,
        healthUrl: `http://localhost:${PORT}/health`,
    }, '🚀 Node.js server started');
});

// Graceful Shutdown
const shutdown: () => void = (): void => {
    logger.info('Received shutdown signal, closing server gracefully...');

    server.close(() => {
        logger.info('Server closed successfully');
        process.exit(0);
    });

    // Force shutdown after 10s
    setTimeout((): void => {
        logger.error('Forced shutdown after timeout');
        process.exit(1);
    }, 10000);
};

process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
