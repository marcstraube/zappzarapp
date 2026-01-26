import { readFileSync, existsSync } from 'fs';

/**
 * Default CA certificate path (from Task 06 Certificate Architecture)
 * Mounted via compose.yaml from docker/certs/internal/ca.crt
 */
const DEFAULT_CA_PATH = '/etc/ssl/certs/internal-ca.crt';

/**
 * TLS verification configuration based on environment
 * Development: disabled (self-signed certs)
 * Production: enabled with internal CA (trusted certs)
 */
export function shouldVerifyTls(): boolean {
  // Explicit override takes precedence
  const override = process.env.TLS_VERIFY_INTERNAL;
  if (override !== undefined) {
    return override === 'true';
  }

  // Default: verify in production only
  // Note: Defaults to 'production' (secure by default) - matches server.ts behavior
  const env = process.env.NODE_ENV ?? 'production';
  return env === 'production';
}

/**
 * Get CA certificate path
 */
export function getCaPath(): string {
  return process.env.TLS_CA_PATH ?? DEFAULT_CA_PATH;
}

/**
 * Get CA certificate buffer (if exists and verification enabled)
 */
export function getCaCert(): Buffer | undefined {
  if (!shouldVerifyTls()) {
    return undefined;
  }

  const caPath = getCaPath();
  if (existsSync(caPath)) {
    return readFileSync(caPath);
  }

  return undefined;
}

/**
 * Get TLS socket options for Node.js clients (e.g., Redis)
 */
export function getTlsSocketOptions(): {
  tls: true;
  rejectUnauthorized: boolean;
  ca?: Buffer;
} {
  const verify = shouldVerifyTls();
  return {
    tls: true,
    rejectUnauthorized: verify,
    ...(verify && { ca: getCaCert() }),
  };
}

/**
 * Get TLS options for HTTPS requests
 */
export function getHttpsTlsOptions(): {
  rejectUnauthorized: boolean;
  ca?: Buffer;
} {
  const verify = shouldVerifyTls();
  return {
    rejectUnauthorized: verify,
    ...(verify && { ca: getCaCert() }),
  };
}
