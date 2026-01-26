/**
 * Quality Service
 *
 * Provides Node.js code quality metrics and tool status.
 */

import { existsSync, readFileSync } from 'fs';
import { join } from 'path';

export interface ToolStatus {
  enabled: boolean;
  configFile: string;
  status: string;
  message: string;
}

export interface QualityMetrics {
  eslint: ToolStatus;
  prettier: ToolStatus;
  typescript: ToolStatus;
  vitest: ToolStatus;
}

const PROJECT_ROOT = '/app';

export class QualityService {
  /**
   * Get all Node.js quality metrics
   */
  getNodeQualityMetrics(): QualityMetrics {
    return {
      eslint: this.getEslintStatus(),
      prettier: this.getPrettierStatus(),
      typescript: this.getTypeScriptStatus(),
      vitest: this.getVitestStatus(),
    };
  }

  /**
   * Get ESLint status
   */
  private getEslintStatus(): ToolStatus {
    const configFiles = ['eslint.config.js', 'eslint.config.mjs', '.eslintrc.js', '.eslintrc.json'];

    for (const configFile of configFiles) {
      const configPath = join(PROJECT_ROOT, configFile);
      if (existsSync(configPath)) {
        return {
          enabled: true,
          configFile,
          status: 'configured',
          message: 'ESLint is configured',
        };
      }
    }

    return {
      enabled: false,
      configFile: '',
      status: 'not_configured',
      message: 'ESLint not configured',
    };
  }

  /**
   * Get Prettier status
   */
  private getPrettierStatus(): ToolStatus {
    const configFiles = [
      '.prettierrc',
      '.prettierrc.json',
      '.prettierrc.js',
      'prettier.config.js',
      '.prettierrc.yaml',
    ];

    for (const configFile of configFiles) {
      const configPath = join(PROJECT_ROOT, configFile);
      if (existsSync(configPath)) {
        return {
          enabled: true,
          configFile,
          status: 'configured',
          message: 'Prettier is configured',
        };
      }
    }

    return {
      enabled: false,
      configFile: '',
      status: 'not_configured',
      message: 'Prettier not configured',
    };
  }

  /**
   * Get TypeScript status
   */
  private getTypeScriptStatus(): ToolStatus {
    const configPath = join(PROJECT_ROOT, 'tsconfig.json');

    if (!existsSync(configPath)) {
      return {
        enabled: false,
        configFile: '',
        status: 'not_configured',
        message: 'TypeScript not configured',
      };
    }

    try {
      const content = readFileSync(configPath, 'utf-8');
      const config = JSON.parse(content) as { compilerOptions?: { strict?: boolean } };
      const strict = config.compilerOptions?.strict ?? false;

      return {
        enabled: true,
        configFile: 'tsconfig.json',
        status: 'configured',
        message: strict ? 'TypeScript configured (strict mode)' : 'TypeScript configured',
      };
    } catch {
      return {
        enabled: true,
        configFile: 'tsconfig.json',
        status: 'configured',
        message: 'TypeScript configured',
      };
    }
  }

  /**
   * Get Vitest status
   */
  private getVitestStatus(): ToolStatus {
    const configFiles = ['vitest.config.ts', 'vitest.config.js', 'vitest.config.mts'];

    for (const configFile of configFiles) {
      const configPath = join(PROJECT_ROOT, configFile);
      if (existsSync(configPath)) {
        return {
          enabled: true,
          configFile,
          status: 'configured',
          message: 'Vitest is configured',
        };
      }
    }

    // Check if vitest is in package.json
    const packageJsonPath = join(PROJECT_ROOT, 'package.json');
    if (existsSync(packageJsonPath)) {
      try {
        const content = readFileSync(packageJsonPath, 'utf-8');
        const packageJson = JSON.parse(content) as {
          devDependencies?: Record<string, string>;
          dependencies?: Record<string, string>;
        };
        if (
          packageJson.devDependencies?.vitest !== undefined ||
          packageJson.dependencies?.vitest !== undefined
        ) {
          return {
            enabled: true,
            configFile: 'package.json (inline)',
            status: 'configured',
            message: 'Vitest is available',
          };
        }
      } catch {
        // Ignore parse errors
      }
    }

    return {
      enabled: false,
      configFile: '',
      status: 'not_configured',
      message: 'Vitest not configured',
    };
  }
}
