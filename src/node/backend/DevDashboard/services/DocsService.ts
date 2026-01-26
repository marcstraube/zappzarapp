/**
 * Docs Service
 *
 * Handles Node.js API documentation generation and status checks.
 */

import { spawn } from 'child_process';
import { existsSync, statSync, readdirSync } from 'fs';
import { join } from 'path';

export interface DocsResult {
  success: boolean;
  message: string;
  reportPath?: string;
  output?: string;
}

export interface DocsStatus {
  available: boolean;
  outdated: boolean;
  reportPath: string;
  message: string;
}

const PROJECT_ROOT = '/app';
const DOCS_DIR = join(PROJECT_ROOT, 'docs/api/node-backend');
const DOCS_INDEX = join(DOCS_DIR, 'index.html');
const SRC_DIR = join(PROJECT_ROOT, 'src/node/backend');

/**
 * Get the newest modification time of files in a directory
 */
function getNewestMtime(directory: string, extensions: string[]): number | null {
  if (!existsSync(directory)) {
    return null;
  }

  let newestMtime: number | null = null;

  function walkDir(dir: string): void {
    try {
      const entries = readdirSync(dir, { withFileTypes: true });
      for (const entry of entries) {
        const fullPath = join(dir, entry.name);
        if (entry.isDirectory()) {
          // Skip node_modules and hidden directories
          if (entry.name !== 'node_modules' && !entry.name.startsWith('.')) {
            walkDir(fullPath);
          }
        } else if (entry.isFile()) {
          const ext = entry.name.split('.').pop() ?? '';
          if (extensions.includes(ext)) {
            const stat = statSync(fullPath);
            const mtime = stat.mtimeMs;
            if (newestMtime === null || mtime > newestMtime) {
              newestMtime = mtime;
            }
          }
        }
      }
    } catch {
      // Ignore permission errors
    }
  }

  walkDir(directory);
  return newestMtime;
}

export class DocsService {
  /**
   * Generate Node.js API documentation using TypeDoc
   */
  async generateDocs(): Promise<DocsResult> {
    return new Promise((resolve) => {
      const pnpmPath = '/usr/local/bin/pnpm';

      // Check if pnpm exists
      if (!existsSync(pnpmPath)) {
        resolve({
          success: false,
          message: 'pnpm not found at /usr/local/bin/pnpm',
        });
        return;
      }

      let output = '';

      // Use node to execute pnpm directly (avoids symlink/shebang issues)
      const nodePath = process.execPath;
      const child = spawn(nodePath, [pnpmPath, 'docs:backend'], {
        cwd: PROJECT_ROOT,
        env: {
          ...process.env,
          NODE_ENV: 'development',
        },
      });

      child.stdout.on('data', (data: Buffer) => {
        output += data.toString();
      });

      child.stderr.on('data', (data: Buffer) => {
        output += data.toString();
      });

      child.on('error', (error) => {
        resolve({
          success: false,
          message: `Failed to start docs generation: ${error.message}`,
          output,
        });
      });

      child.on('close', (code) => {
        // Check if docs were generated
        const hasReport = existsSync(DOCS_INDEX);

        if (code !== 0 && !hasReport) {
          resolve({
            success: false,
            message: `Documentation generation failed with exit code ${code}`,
            output,
          });
          return;
        }

        if (hasReport) {
          resolve({
            success: true,
            message: 'Node.js API documentation generated',
            reportPath: '/docs/api/node-backend/index.html',
            output,
          });
          return;
        }

        resolve({
          success: false,
          message: 'Documentation generation completed but no output found',
          output,
        });
      });

      // Timeout after 2 minutes
      setTimeout(() => {
        child.kill('SIGTERM');
        resolve({
          success: false,
          message: 'Documentation generation timed out after 2 minutes',
          output,
        });
      }, 120000);
    });
  }

  /**
   * Get documentation status
   */
  getDocsStatus(): DocsStatus {
    if (!existsSync(DOCS_INDEX)) {
      return {
        available: false,
        outdated: false,
        reportPath: '/docs/api/node-backend/index.html',
        message: 'Run typedoc to generate documentation',
      };
    }

    const docsStat = statSync(DOCS_INDEX);
    const docsMtime = docsStat.mtimeMs;

    // Check if source files are newer than docs
    const srcMtime = getNewestMtime(SRC_DIR, ['ts', 'tsx']);

    const outdated = srcMtime !== null && srcMtime > docsMtime;

    return {
      available: true,
      outdated,
      reportPath: '/docs/api/node-backend/index.html',
      message: outdated ? 'Documentation outdated' : 'Documentation available',
    };
  }
}
