/**
 * Credential loading utilities
 *
 * Provides functions to load credentials from Docker secrets or environment variables.
 * Priority order:
 * 1. Docker secret with .txt extension (/tmp/secrets/{name}.txt)
 * 2. Docker secret without extension (/run/secrets/{name})
 * 3. Environment variable
 * 4. Default value
 */

import { existsSync, readFileSync } from 'fs';

/**
 * Load credential from Docker secret or environment variable
 *
 * @param secretName - Name of the Docker secret file (without path)
 * @param envName - Name of the environment variable
 * @param defaultValue - Default value if neither secret nor env var is found
 * @returns The credential value
 */
export function loadCredential(secretName: string, envName: string, defaultValue: string): string {
  // Try Docker secret first (with .txt extension)
  const secretPathTxt = `/tmp/secrets/${secretName}.txt`;
  if (existsSync(secretPathTxt)) {
    try {
      return readFileSync(secretPathTxt, 'utf-8').trim();
    } catch {
      // Fall through to next option
    }
  }

  // Try Docker secret without extension
  const secretPath = `/run/secrets/${secretName}`;
  if (existsSync(secretPath)) {
    try {
      return readFileSync(secretPath, 'utf-8').trim();
    } catch {
      // Fall through to next option
    }
  }

  // Try environment variable
  const envValue = process.env[envName];
  if (envValue !== undefined && envValue !== '') {
    return envValue;
  }

  return defaultValue;
}
