/**
 * Coverage Service
 *
 * Handles Node.js test coverage generation and status checks.
 */

import { spawn } from 'child_process';
import { existsSync, statSync, readdirSync, type Dirent } from 'fs';
import { join } from 'path';

export interface CoverageResult {
  success: boolean;
  message: string;
  reportPath?: string;
  output?: string;
}

export interface CoverageStatus {
  available: boolean;
  outdated: boolean;
  reportPath: string;
  message: string;
}

const PROJECT_ROOT = '/app';
const COVERAGE_DIR = join(PROJECT_ROOT, 'build/coverage/node');
const COVERAGE_REPORT = join(COVERAGE_DIR, 'index.html');
const SRC_DIR = join(PROJECT_ROOT, 'src/node');
const TESTS_DIR = join(PROJECT_ROOT, 'tests/node');

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
      const entries: Dirent[] = readdirSync(dir, { withFileTypes: true });
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

export class CoverageService {
  /**
   * Run Node.js test coverage
   */
  async runCoverage(): Promise<CoverageResult> {
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
      const child = spawn(nodePath, [pnpmPath, 'vitest', 'run', '--coverage'], {
        cwd: PROJECT_ROOT,
        env: {
          ...process.env,
          NODE_ENV: 'test',
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
          message: `Failed to start coverage: ${error.message}`,
          output,
        });
      });

      child.on('close', (code) => {
        // Check if coverage report was generated
        const hasReport = existsSync(COVERAGE_REPORT);

        // Report generated successfully
        if (hasReport) {
          // Check if exit code 1 is due to coverage threshold failures (not test failures)
          const thresholdFailure = output.includes('does not meet global threshold');
          // Look for actual test failure summary line: "Tests  N failed" (vitest format)
          const testFailureMatch = /Tests\s+\d+\s+failed/.test(output);

          if (code === 0 || (code === 1 && thresholdFailure && !testFailureMatch)) {
            resolve({
              success: true,
              message: thresholdFailure
                ? 'Node.js coverage report generated (coverage thresholds not met)'
                : 'Node.js coverage report generated',
              reportPath: '/build/coverage/node/index.html',
              output,
            });
            return;
          }
        }

        // Tests actually failed or report not generated
        if (code !== 0) {
          resolve({
            success: false,
            message: `Tests failed with exit code ${code}. Coverage report may be incomplete.`,
            output,
          });
          return;
        }

        // Exit code 0 but no report - unusual but possible
        resolve({
          success: false,
          message: 'Coverage generation completed but no report was generated',
          output,
        });
      });

      // Timeout after 5 minutes
      setTimeout(() => {
        child.kill('SIGTERM');
        resolve({
          success: false,
          message: 'Coverage generation timed out after 5 minutes',
          output,
        });
      }, 300000);
    });
  }

  /**
   * Get coverage status
   */
  getCoverageStatus(): CoverageStatus {
    if (!existsSync(COVERAGE_REPORT)) {
      return {
        available: false,
        outdated: false,
        reportPath: '/build/coverage/node/index.html',
        message: 'Run coverage to generate report',
      };
    }

    const reportStat = statSync(COVERAGE_REPORT);
    const reportMtime = reportStat.mtimeMs;

    // Check if source or test files are newer than coverage
    const srcMtime = getNewestMtime(SRC_DIR, ['ts', 'tsx', 'js', 'jsx']);
    const testsMtime = getNewestMtime(TESTS_DIR, ['ts', 'js']);

    const newestCode = Math.max(srcMtime ?? 0, testsMtime ?? 0);
    const outdated = newestCode > reportMtime;

    return {
      available: true,
      outdated,
      reportPath: '/build/coverage/node/index.html',
      message: outdated ? 'Coverage report outdated' : 'Coverage report available',
    };
  }
}
