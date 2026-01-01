import { defineConfig } from 'vitest/config';
import * as path from 'path';

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
      // Vitest 4.x: Fixed include/exclude handling (no longer needs workarounds)
      include: ['src/node/**/*.{ts,js}'],
      exclude: [
        '**/*.config.{js,ts,cjs,mjs}',
        '**/*.{spec,test}.{js,ts}',
      ],
      thresholds: {
        lines: 60,
        functions: 60,
        branches: 55,
        statements: 60,
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
