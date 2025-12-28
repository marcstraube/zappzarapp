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
import { createApp, logger } from './app';

const PORT = process.env.PORT || 3000;
const NODE_ENV = process.env.NODE_ENV || 'production';
const LOG_LEVEL = process.env.LOG_LEVEL || 'info';
const LOG_FORMAT = process.env.LOG_FORMAT || 'json';

// Create Express app
const app = createApp();

// Start Server
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
