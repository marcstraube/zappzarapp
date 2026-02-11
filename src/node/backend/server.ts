/**
 * Node.js Backend Server
 *
 * This is an example Express.js server with structured logging (Pino).
 * Only used when NODE_TARGET=app-server in docker-compose.
 *
 * Build output: dist/server.js
 * Runtime: Node.js container on port 3000 (HTTPS)
 */

import { createServer as createHttpsServer, Server } from 'https';
import { readFileSync, statSync } from 'fs';
import { createApp, logger } from './app.js';
import { fileURLToPath } from 'url';

export const PORT = process.env.PORT ?? '3000';
export const NODE_ENV = process.env.NODE_ENV ?? 'production';
export const LOG_LEVEL = process.env.LOG_LEVEL ?? 'info';
export const LOG_FORMAT = process.env.LOG_FORMAT ?? 'json';

// Certificate paths (mounted from docker/certs/)
const CERT_PATH = '/etc/ssl/certs/cert.crt';
const KEY_PATH = '/etc/ssl/private/cert.key';

// TLS verification: auto-detect from environment
// Development (self-signed) = no verify, Production (Let's Encrypt) = verify
const tlsRejectUnauthorized = NODE_ENV === 'production';
process.env.NODE_TLS_REJECT_UNAUTHORIZED = tlsRejectUnauthorized ? '1' : '0';

/**
 * Create and start the HTTPS server
 */
export function startServer(): Server {
  const app = createApp();

  // Verify certificates exist and are files (not directories from Docker bind mount bug)
  const isFile = (path: string): boolean => {
    try {
      return statSync(path).isFile();
    } catch {
      return false;
    }
  };

  if (!isFile(CERT_PATH) || !isFile(KEY_PATH)) {
    const hint =
      NODE_ENV === 'production'
        ? 'Ensure TLS certificates are properly mounted, then restart the container.'
        : 'Run "make ssl-internal", then restart with "make up".';
    logger.error(
      { certPath: CERT_PATH, keyPath: KEY_PATH },
      `TLS certificates not found or invalid. ${hint}`
    );
    process.exit(1);
  }

  const server: Server = createHttpsServer(
    {
      key: readFileSync(KEY_PATH),
      cert: readFileSync(CERT_PATH),
    },
    app
  );

  server.listen(PORT, (): void => {
    logger.info(
      {
        port: PORT,
        protocol: 'https',
        environment: NODE_ENV,
        tlsVerify: tlsRejectUnauthorized,
        logLevel: LOG_LEVEL,
        logFormat: LOG_FORMAT,
        healthUrl: `https://localhost:${PORT}/health`,
      },
      '🚀 Node.js server started (TLS enabled)'
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
if (isMainModule) {
  startServer();
}
