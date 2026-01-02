import { coverageConfigDefaults, defineConfig } from 'vitest/config';
import * as path from 'path';

export default defineConfig({
  test: {
    // Test environment
    environment: 'node',

    // Test file patterns (Node.js tests only in tests/node/App/)
    include: ['tests/node/App/**/*.{test,spec}.{ts,js}', 'src/node/App/**/*.{test,spec}.{ts,js}'],
    exclude: ['node_modules', 'dist', 'build', 'vendor', 'tests/php', 'src/node/server.ts'],

    // Coverage configuration (targeting 80% parity with PHPUnit)
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'lcov'],
      reportsDirectory: './build/coverage',
      // Default: only files covered by tests are included
      exclude: [
        ...coverageConfigDefaults.exclude,
        '**/*.test.{ts,js}',
        '**/*.spec.{ts,js}',
      ],
      // Coverage thresholds: 80% parity with PHP
      thresholds: {
        lines: 80,
        functions: 80,
        branches: 80,
        statements: 80,
      },
    },

    // Test output
    reporters: ['verbose', 'html'],
    outputFile: {
      html: './build/vitest-report.html',
    },

    // Performance
    globals: true,
    mockReset: true,
    restoreMocks: true,
    clearMocks: true,

    // Timeouts
    testTimeout: 10000,
    hookTimeout: 10000,
  },

  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
      '@node': path.resolve(__dirname, './src/node/App'),
      '@tests': path.resolve(__dirname, './tests/node/App'),
    },
  },
});
