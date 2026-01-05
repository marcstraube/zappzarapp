/**
 * Node.js Backend Server
 *
 * This is an example Express.js server with structured logging (Pino).
 * Only used when NODE_TARGET=app-server in docker-compose.
 *
 * Build output: dist/server.js
 * Runtime: Node.js container on port 3000
 */

import { createServer, Server } from 'http';
import { createApp, logger } from './App/app';
import { fileURLToPath } from 'url';

export const PORT = process.env.PORT ?? '3000';
export const NODE_ENV = process.env.NODE_ENV ?? 'production';
export const LOG_LEVEL = process.env.LOG_LEVEL ?? 'info';
export const LOG_FORMAT = process.env.LOG_FORMAT ?? 'json';

/**
 * Create and start the server
 */
export function startServer(): Server {
  const app = createApp();
  const server: Server = createServer(app);

  server.listen(PORT, (): void => {
    logger.info(
      {
        port: PORT,
        environment: NODE_ENV,
        logLevel: LOG_LEVEL,
        logFormat: LOG_FORMAT,
        healthUrl: `http://localhost:${PORT}/health`,
      },
      '🚀 Node.js server started'
    );
  });

  // Graceful Shutdown
  const shutdown: () => void = (): void => {
    logger.info('Received shutdown signal, closing server gracefully...');

    // Remove signal handlers to prevent multiple shutdown calls
    process.off('SIGTERM', shutdown);
    process.off('SIGINT', shutdown);

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

  return server;
}

// Only start server if this file is run directly (not imported in tests)
// Check if this module is the main entry point
const isMainModule =
  process.argv[1] !== undefined && fileURLToPath(import.meta.url) === process.argv[1];
if (isMainModule === true) {
  startServer();
}
