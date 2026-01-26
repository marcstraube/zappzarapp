/**
 * Test configuration helpers
 */

/**
 * Set environment variables for testing
 */
export function setTestEnv(vars: Record<string, string>): void {
  Object.entries(vars).forEach(([key, value]) => {
    process.env[key] = value;
  });
}

/**
 * Clear environment variables after testing
 */
export function clearTestEnv(keys: string[]): void {
  keys.forEach((key) => {
    delete process.env[key];
  });
}

/**
 * Temporarily set environment and restore after callback
 */
export async function withTestEnv<T>(
  vars: Record<string, string>,
  callback: () => T | Promise<T>
): Promise<T> {
  const originalEnv = { ...process.env };
  setTestEnv(vars);
  try {
    return await callback();
  } finally {
    process.env = originalEnv;
  }
}

/**
 * Get test database config
 */
export function getTestDatabaseConfig() {
  return {
    type: 'postgres' as const,
    host: 'localhost',
    port: 5432,
    name: 'test_db',
    user: 'test_user',
    password: 'test_password',
  };
}
