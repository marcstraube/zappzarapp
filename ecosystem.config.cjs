/**
 * PM2 Ecosystem Configuration
 *
 * Manages multiple Node.js processes in development and production.
 * Supports three runtime modes:
 * - vite-only: Frontend development server with HMR
 * - backend-only: Node.js API server
 * - full-stack: Both Vite + Backend running concurrently
 *
 * @see https://pm2.keymetrics.io/docs/usage/application-declaration/
 */

module.exports = {
  apps: [
    {
      name: 'vite',
      script: 'vite',
      args: '--host 0.0.0.0 --port 5173',
      cwd: '/app',
      interpreter: 'none',
      exec_mode: 'fork_mode',
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: '512M',
      env: {
        NODE_ENV: 'development',
        VITE_PORT: 5173,
      },
      env_production: {
        NODE_ENV: 'production',
      },
      // Logging configuration (JSON structured logs)
      log_type: 'json',
      merge_logs: true,
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      error_file: '/dev/stderr',
      out_file: '/dev/stdout',
      // Graceful shutdown
      kill_timeout: 5000,
      wait_ready: false,
      listen_timeout: 10000,
    },
    {
      name: 'backend',
      script: 'node',
      args: '--import tsx src/node/server.ts',
      cwd: '/app',
      interpreter: 'none',
      exec_mode: 'fork_mode',
      instances: 1,
      autorestart: true,
      watch: false,  // Disabled in Docker: use Docker Compose Watch instead (compose.override.yaml)
      // NOTE: PM2 watch with Docker bind mounts causes false-positive change detections
      // Docker Compose Watch provides better file change detection for containerized apps
      max_memory_restart: '512M',
      env: {
        NODE_ENV: 'development',
        PORT: 3000,
      },
      env_production: {
        NODE_ENV: 'production',
        PORT: 3000,
      },
      // Logging configuration (structured JSON logging)
      log_type: 'json',
      merge_logs: true,
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      error_file: '/dev/stderr',
      out_file: '/dev/stdout',
      // Graceful shutdown
      kill_timeout: 10000,
      wait_ready: false,  // Disabled: process.send('ready') not reliable with Docker + PM2
      listen_timeout: 15000,
    },
  ],

  /**
   * Deployment configuration (optional)
   * Uncomment and configure for remote deployments
   */
  // deploy: {
  //   production: {
  //     user: 'deploy',
  //     host: 'your-server.com',
  //     ref: 'origin/main',
  //     repo: 'git@github.com:your-org/your-repo.git',
  //     path: '/var/www/production',
  //     'post-deploy': 'pnpm install && pnpm run build && pm2 reload ecosystem.config.cjs --env production',
  //   },
  // },
};
