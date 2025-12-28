import { defineConfig } from 'vitest/config';
import path from 'path';

export default defineConfig({
  test: {
    // Test environment
    environment: 'node',

    // Test file patterns (Node.js tests only in tests/node/)
    include: ['tests/node/**/*.{test,spec}.{ts,js}', 'src/node/**/*.{test,spec}.{ts,js}'],
    exclude: ['node_modules', 'dist', 'build', 'vendor', 'tests/php'],

    // Coverage configuration (similar to PHPUnit)
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'lcov'],
      reportsDirectory: './build/coverage',
      exclude: [
        'node_modules/**',
        'dist/**',
        'build/**',
        'tests/**',
        '**/*.config.{ts,js}',
        '**/*.spec.{ts,js}',
        '**/*.test.{ts,js}',
      ],
      all: true,
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
      '@node': path.resolve(__dirname, './src/node'),
      '@tests': path.resolve(__dirname, './tests/node'),
    },
  },
});
