import { coverageConfigDefaults, defineConfig } from 'vitest/config';
import * as path from 'path';

export default defineConfig({
  test: {
    // Test environment
    environment: 'node',

    // Test file patterns (Node.js backend + browser resources)
    include: [
      'tests/node/backend/**/*.{test,spec}.{ts,js}',
      'tests/node/resources/**/*.{test,spec}.{ts,js}',
    ],
    exclude: ['**/node_modules/**', 'dist', 'build', 'vendor', 'tests/php', 'src/node/frontend'],

    // Coverage configuration (targeting 80% parity with PHPUnit)
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'lcov'],
      reportsDirectory: './build/coverage/node',
      // Default: only files covered by tests are included
      exclude: [
        ...coverageConfigDefaults.exclude,
        '**/*.test.{ts,js}',
        '**/*.spec.{ts,js}',
        'src/node/frontend/**',
      ],
      // Coverage thresholds. Lines/statements/functions at 80%; branches at
      // 75% — branch coverage is harder to reach and is conventionally held a
      // few points below line coverage, and 75% sits above the PHP gate (70%,
      // PHP_COVERAGE_MIN).
      thresholds: {
        lines: 80,
        functions: 80,
        branches: 75,
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
      '@backend': path.resolve(__dirname, './src/node/backend'),
      '@resources': path.resolve(__dirname, './resources'),
      '@tests': path.resolve(__dirname, './tests/node/backend'),
    },
  },
});
