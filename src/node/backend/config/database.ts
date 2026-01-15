/**
 * Database Configuration (12-Factor App compliant)
 *
 * Provides database connection configuration following 12-Factor App principles.
 * DATABASE_URL takes precedence over individual variables for PaaS compatibility.
 *
 * Supports Docker Secrets via _FILE environment variables:
 * - DB_PASSWORD_FILE: Path to file containing database password
 */

import { existsSync, readFileSync } from 'fs';

export interface DatabaseConfig {
  type: 'postgres' | 'mysql';
  host: string;
  port: number;
  name: string;
  user: string;
  password: string;
}

export interface SslConfig {
  ca: string;
  verify: boolean;
}

const DEFAULT_POSTGRES_PORT = 5432;
const DEFAULT_MYSQL_PORT = 3306;
const DEFAULT_INTERNAL_CERT_PATH = '/etc/ssl/db-certs/cert.crt';
const SYSTEM_CA_BUNDLE_PATH = '/etc/ssl/certs/ca-certificates.crt';

/**
 * Get environment variable with fallback (handles undefined and empty string).
 */
function getEnv(name: string, fallback: string): string {
  const value = process.env[name];
  return value !== undefined && value !== '' ? value : fallback;
}

/**
 * Get environment variable with _FILE support for Docker Secrets.
 * Checks for {NAME}_FILE first, reads file content if exists,
 * otherwise falls back to regular environment variable.
 */
function getEnvOrFile(name: string, fallback: string): string {
  // Check for _FILE variant first (Docker Secrets pattern)
  const fileVar = `${name}_FILE`;
  const filePath = process.env[fileVar];

  if (filePath !== undefined && filePath !== '' && existsSync(filePath)) {
    try {
      return readFileSync(filePath, 'utf-8').trim();
    } catch {
      // Fall through to regular env var
    }
  }

  return getEnv(name, fallback);
}

/**
 * Parse DATABASE_URL into config object.
 */
function parseUrl(url: string): DatabaseConfig {
  const parsed = new URL(url);

  const type =
    parsed.protocol === 'postgresql:' || parsed.protocol === 'postgres:' ? 'postgres' : 'mysql';

  return {
    type,
    host: parsed.hostname,
    port: parsed.port ? parseInt(parsed.port, 10) : getDefaultPort(type),
    name: parsed.pathname.slice(1), // Remove leading slash
    user: parsed.username || 'app',
    password: decodeURIComponent(parsed.password || ''),
  };
}

/**
 * Load config from individual environment variables.
 * Uses _FILE variant for password to support Docker Secrets.
 */
function loadFromIndividualVars(): DatabaseConfig {
  const type = getEnv('DB_TYPE', 'postgres') as 'postgres' | 'mysql';
  const defaultHost = type === 'postgres' ? 'postgres' : 'mariadb';

  return {
    type,
    host: getEnv('DB_HOST', defaultHost),
    port: parseInt(getEnv('DB_PORT', String(getDefaultPort(type))), 10),
    name: getEnv('DB_NAME', 'app'),
    user: getEnv('DB_USER', 'app'),
    password: getEnvOrFile('DB_PASSWORD', 'secret'),
  };
}

function getDefaultPort(type: 'postgres' | 'mysql'): number {
  return type === 'postgres' ? DEFAULT_POSTGRES_PORT : DEFAULT_MYSQL_PORT;
}

/**
 * Get database configuration.
 * DATABASE_URL takes precedence over individual variables.
 */
export function getDatabaseConfig(): DatabaseConfig {
  const databaseUrl = process.env.DATABASE_URL;

  if (databaseUrl !== undefined && databaseUrl !== '') {
    return parseUrl(databaseUrl);
  }

  return loadFromIndividualVars();
}

/**
 * Get DATABASE_URL format (for libraries that expect it).
 */
export function getDatabaseUrl(config?: DatabaseConfig): string {
  const cfg = config || getDatabaseConfig();
  const driver = cfg.type === 'postgres' ? 'postgresql' : 'mysql';
  const encodedPassword = encodeURIComponent(cfg.password);

  let url = `${driver}://${cfg.user}:${encodedPassword}@${cfg.host}:${cfg.port}/${cfg.name}`;

  // Add sslmode for PostgreSQL when SSL is configured
  if (cfg.type === 'postgres') {
    const sslmode = getPostgresSslMode(cfg);
    if (sslmode) {
      url += `?sslmode=${sslmode}`;
    }
  }

  return url;
}

/**
 * Check if using PostgreSQL.
 */
export function isPostgres(config?: DatabaseConfig): boolean {
  const cfg = config || getDatabaseConfig();
  return cfg.type === 'postgres';
}

/**
 * Check if using MySQL/MariaDB.
 */
export function isMySQL(config?: DatabaseConfig): boolean {
  const cfg = config || getDatabaseConfig();
  return cfg.type === 'mysql';
}

/**
 * Get SSL configuration.
 * Falls back to internal certificate for MySQL/MariaDB if no explicit config is set.
 */
export function getSslConfig(dbConfig?: DatabaseConfig): SslConfig {
  const cfg = dbConfig ?? getDatabaseConfig();
  let ca = getEnv('DB_SSL_CA', '');

  // Handle special "system" value - use system CA bundle for cloud databases
  if (ca.toLowerCase() === 'system') {
    ca = existsSync(SYSTEM_CA_BUNDLE_PATH) ? SYSTEM_CA_BUNDLE_PATH : '';
  }
  // For MySQL/MariaDB: fall back to internal cert if no explicit CA is configured
  else if (!ca && cfg.type === 'mysql' && existsSync(DEFAULT_INTERNAL_CERT_PATH)) {
    ca = DEFAULT_INTERNAL_CERT_PATH;
  }

  return {
    ca,
    verify: process.env.DB_SSL_VERIFY === 'true',
  };
}

/**
 * Check if SSL is configured and available.
 */
export function hasSsl(dbConfig?: DatabaseConfig): boolean {
  const ssl = getSslConfig(dbConfig);
  return ssl.ca !== '' && existsSync(ssl.ca);
}

/**
 * Get PostgreSQL sslmode based on SSL configuration.
 *
 * @returns Empty string for default (prefer), or explicit mode
 */
export function getPostgresSslMode(dbConfig?: DatabaseConfig): string {
  const ssl = getSslConfig(dbConfig);

  // No SSL CA configured - use default behavior (prefer)
  if (ssl.ca === '' || !hasSsl(dbConfig)) {
    return '';
  }

  // SSL CA configured - enforce SSL with appropriate verification
  return ssl.verify ? 'verify-full' : 'require';
}

// Export singleton config for convenience
export const databaseConfig = getDatabaseConfig();
export const sslConfig = getSslConfig();
