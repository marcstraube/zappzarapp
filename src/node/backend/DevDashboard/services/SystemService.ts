/**
 * System Service
 *
 * Provides Node.js system information for the DevDashboard.
 */

import { execSync } from 'child_process';
import { existsSync, readFileSync } from 'fs';
import { join } from 'path';

export interface NodeInfo {
  nodeVersion: string;
  npmVersion: string;
  pnpmVersion: string;
  environment: string;
  uptime: number;
  memoryUsage: {
    heapUsed: number;
    heapTotal: number;
    external: number;
    rss: number;
  };
  packageInfo: {
    dependencies: number;
    devDependencies: number;
    name?: string;
    version?: string;
  };
}

const PROJECT_ROOT = '/app';

export class SystemService {
  /**
   * Get Node.js system information
   */
  getNodeInfo(): NodeInfo {
    const memoryUsage = process.memoryUsage();
    const packageInfo = this.getPackageInfo();

    return {
      nodeVersion: process.version,
      npmVersion: this.getNpmVersion(),
      pnpmVersion: this.getPnpmVersion(),
      environment: process.env.NODE_ENV ?? 'production',
      uptime: Math.round(process.uptime()),
      memoryUsage: {
        heapUsed: Math.round(memoryUsage.heapUsed / 1024 / 1024),
        heapTotal: Math.round(memoryUsage.heapTotal / 1024 / 1024),
        external: Math.round(memoryUsage.external / 1024 / 1024),
        rss: Math.round(memoryUsage.rss / 1024 / 1024),
      },
      packageInfo,
    };
  }

  /**
   * Get npm version
   */
  private getNpmVersion(): string {
    try {
      return execSync('npm --version', { encoding: 'utf-8' }).trim();
    } catch {
      return 'unknown';
    }
  }

  /**
   * Get pnpm version
   */
  private getPnpmVersion(): string {
    try {
      return execSync('pnpm --version', { encoding: 'utf-8' }).trim();
    } catch {
      return 'unknown';
    }
  }

  /**
   * Get package.json information
   */
  private getPackageInfo(): NodeInfo['packageInfo'] {
    const packageJsonPath = join(PROJECT_ROOT, 'package.json');

    if (!existsSync(packageJsonPath)) {
      return {
        dependencies: 0,
        devDependencies: 0,
      };
    }

    try {
      const content = readFileSync(packageJsonPath, 'utf-8');
      const packageJson = JSON.parse(content) as {
        name?: string;
        version?: string;
        dependencies?: Record<string, string>;
        devDependencies?: Record<string, string>;
      };

      return {
        name: packageJson.name,
        version: packageJson.version,
        dependencies: Object.keys(packageJson.dependencies ?? {}).length,
        devDependencies: Object.keys(packageJson.devDependencies ?? {}).length,
      };
    } catch {
      return {
        dependencies: 0,
        devDependencies: 0,
      };
    }
  }
}
