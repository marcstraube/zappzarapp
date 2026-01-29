/**
 * Conditional Debug Logger for DevToolbar
 *
 * In development: All logs are shown
 * In production: Only warnings and errors are shown
 */

const isDev = import.meta.env.DEV;

/**
 * Debug log - only shown in development
 */
export function debug(...args: unknown[]): void {
  if (isDev) {
    // eslint-disable-next-line no-console -- Development logging
    console.log(...args);
  }
}

/**
 * Warning - always shown
 */
export function warn(...args: unknown[]): void {
  console.warn(...args);
}

/**
 * Error - always shown
 */
export function error(...args: unknown[]): void {
  console.error(...args);
}

/**
 * Info - only shown in development
 */
export function info(...args: unknown[]): void {
  if (isDev) {
    // eslint-disable-next-line no-console -- Development logging
    console.log(...args);
  }
}
