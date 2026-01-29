/**
 * DevToolbar Browser Bundle Build Script
 *
 * Builds TypeScript modules into a single IIFE bundle for browser injection.
 * Output: src/php/DevToolbar/assets/devtoolbar.js
 *
 * Usage:
 *   tsx devtoolbar.build.ts
 *
 * Integrated into backend build:
 *   make node-server-build
 */

import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Paths relative to this file's location
const entryPoint = join(__dirname, 'index.ts');
const outputFile = join(__dirname, '../../../php/DevToolbar/assets/devtoolbar.js');

const isDev = process.env.NODE_ENV === 'development';

async function build(): Promise<void> {
  // eslint-disable-next-line no-console -- Build script requires console output
  console.log('[DevToolbar Build] Starting browser bundle build...');
  // eslint-disable-next-line no-console -- Build script requires console output
  console.log('[DevToolbar Build] Entry:', entryPoint);
  // eslint-disable-next-line no-console -- Build script requires console output
  console.log('[DevToolbar Build] Output:', outputFile);
  // eslint-disable-next-line no-console -- Build script requires console output
  console.log('[DevToolbar Build] Mode:', isDev ? 'development' : 'production');

  try {
    await esbuild.build({
      entryPoints: [entryPoint],
      bundle: true,
      format: 'iife',
      target: 'es2020',
      platform: 'browser',
      outfile: outputFile,
      minify: !isDev,
      sourcemap: isDev,
      logLevel: 'info',
      treeShaking: true,
      legalComments: 'none',
      banner: {
        js: '/* DevToolbar - Generated browser bundle - DO NOT EDIT MANUALLY */',
      },
    });

    // eslint-disable-next-line no-console -- Build script requires console output
    console.log('[DevToolbar Build] ✓ Bundle created successfully');
  } catch (error) {
    console.error('[DevToolbar Build] ✗ Build failed:', error);
    process.exit(1);
  }
}

void build();
