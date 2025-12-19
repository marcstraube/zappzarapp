/**
 * Node.js Backend Server
 *
 * This is an example Express.js server.
 * Only used when NODE_TARGET=app-server in docker-compose.
 *
 * Build output: dist/server.js
 * Runtime: Node.js container on port 3000
 */

import express, { Express, Request, Response, NextFunction } from 'express';
import { createServer } from 'http';

const app: Express = express();
const PORT = process.env.PORT || 3000;
const NODE_ENV = process.env.NODE_ENV || 'production';

// Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Request logging
app.use((req: Request, _res: Response, next: NextFunction): void => {
    const timestamp = new Date().toISOString();
    console.log(`[${timestamp}] ${req.method} ${req.path}`);
    next();
});

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
app.use((err: Error, _req: Request, res: Response, _next: NextFunction): void => {
    console.error('Error:', err);

    res.status(500).json({
        error: NODE_ENV === 'development' ? err.message : 'Internal Server Error',
        timestamp: new Date().toISOString(),
    });
});

// Start Server
const server: any = createServer(app);

server.listen(PORT, (): void => {
    console.log('==========================================');
    console.log(`🚀 Node.js server running`);
    console.log(`   Port: ${PORT}`);
    console.log(`   Environment: ${NODE_ENV}`);
    console.log(`   Health: http://localhost:${PORT}/health`);
    console.log('==========================================');
});

// Graceful Shutdown
const shutdown: () => void = (): void => {
    console.log('\nShutting down gracefully...');
    server.close(() => {
        console.log('Server closed');
        process.exit(0);
    });

    // Force shutdown after 10s
    setTimeout((): void => {
        console.error('Forced shutdown');
        process.exit(1);
    }, 10000);
};

process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);

export default app;
