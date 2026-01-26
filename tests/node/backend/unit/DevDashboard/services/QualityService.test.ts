/**
 * Tests for DevDashboard QualityService
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { QualityService } from '@backend/DevDashboard/services/QualityService';
import * as fs from 'fs';

// Mock fs module
vi.mock('fs', () => ({
  existsSync: vi.fn(),
  readFileSync: vi.fn(),
}));

describe('DevDashboard QualityService', () => {
  let service: QualityService;

  beforeEach(() => {
    service = new QualityService();
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('getNodeQualityMetrics', () => {
    it('should return all quality metrics', () => {
      vi.mocked(fs.existsSync).mockReturnValue(false);

      const metrics = service.getNodeQualityMetrics();

      expect(metrics).toHaveProperty('eslint');
      expect(metrics).toHaveProperty('prettier');
      expect(metrics).toHaveProperty('typescript');
      expect(metrics).toHaveProperty('vitest');
    });

    it('should detect ESLint when eslint.config.js exists', () => {
      vi.mocked(fs.existsSync).mockImplementation((path) => {
        return typeof path === 'string' && path.includes('eslint.config.js');
      });

      const metrics = service.getNodeQualityMetrics();

      expect(metrics.eslint.enabled).toBe(true);
      expect(metrics.eslint.configFile).toBe('eslint.config.js');
      expect(metrics.eslint.status).toBe('configured');
    });

    it('should detect Prettier when .prettierrc.json exists', () => {
      vi.mocked(fs.existsSync).mockImplementation((path) => {
        return typeof path === 'string' && path.includes('.prettierrc.json');
      });

      const metrics = service.getNodeQualityMetrics();

      expect(metrics.prettier.enabled).toBe(true);
      expect(metrics.prettier.configFile).toBe('.prettierrc.json');
    });

    it('should detect TypeScript when tsconfig.json exists', () => {
      vi.mocked(fs.existsSync).mockImplementation((path) => {
        return typeof path === 'string' && path.includes('tsconfig.json');
      });
      vi.mocked(fs.readFileSync).mockReturnValue('{"compilerOptions": {"strict": true}}');

      const metrics = service.getNodeQualityMetrics();

      expect(metrics.typescript.enabled).toBe(true);
      expect(metrics.typescript.configFile).toBe('tsconfig.json');
      expect(metrics.typescript.message).toContain('strict mode');
    });

    it('should detect Vitest when vitest.config.ts exists', () => {
      vi.mocked(fs.existsSync).mockImplementation((path) => {
        return typeof path === 'string' && path.includes('vitest.config.ts');
      });

      const metrics = service.getNodeQualityMetrics();

      expect(metrics.vitest.enabled).toBe(true);
      expect(metrics.vitest.configFile).toBe('vitest.config.ts');
    });

    it('should return disabled status when tools are not configured', () => {
      vi.mocked(fs.existsSync).mockReturnValue(false);

      const metrics = service.getNodeQualityMetrics();

      expect(metrics.eslint.enabled).toBe(false);
      expect(metrics.prettier.enabled).toBe(false);
      expect(metrics.typescript.enabled).toBe(false);
      expect(metrics.vitest.enabled).toBe(false);
    });
  });
});
