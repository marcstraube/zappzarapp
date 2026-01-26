/**
 * Tests for DevDashboard SystemService
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { SystemService } from '@backend/DevDashboard/services/SystemService';
import * as fs from 'fs';

// Mock fs module
vi.mock('fs', () => ({
  existsSync: vi.fn(),
  readFileSync: vi.fn(),
}));

// Mock child_process
vi.mock('child_process', () => ({
  execSync: vi.fn(),
}));

describe('DevDashboard SystemService', () => {
  let service: SystemService;
  const originalEnv = process.env;

  beforeEach(() => {
    service = new SystemService();
    process.env = { ...originalEnv, NODE_ENV: 'development' };
    vi.clearAllMocks();
  });

  afterEach(() => {
    process.env = originalEnv;
    vi.restoreAllMocks();
  });

  describe('getNodeInfo', () => {
    it('should return node version', () => {
      vi.mocked(fs.existsSync).mockReturnValue(false);

      const info = service.getNodeInfo();

      expect(info.nodeVersion).toBe(process.version);
    });

    it('should return environment', () => {
      vi.mocked(fs.existsSync).mockReturnValue(false);

      const info = service.getNodeInfo();

      expect(info.environment).toBe('development');
    });

    it('should return uptime', () => {
      vi.mocked(fs.existsSync).mockReturnValue(false);

      const info = service.getNodeInfo();

      expect(info.uptime).toBeGreaterThanOrEqual(0);
      expect(typeof info.uptime).toBe('number');
    });

    it('should return memory usage', () => {
      vi.mocked(fs.existsSync).mockReturnValue(false);

      const info = service.getNodeInfo();

      expect(info.memoryUsage).toHaveProperty('heapUsed');
      expect(info.memoryUsage).toHaveProperty('heapTotal');
      expect(info.memoryUsage).toHaveProperty('external');
      expect(info.memoryUsage).toHaveProperty('rss');
      expect(typeof info.memoryUsage.heapUsed).toBe('number');
    });

    it('should return package info when package.json exists', () => {
      vi.mocked(fs.existsSync).mockReturnValue(true);
      vi.mocked(fs.readFileSync).mockReturnValue(
        JSON.stringify({
          name: 'test-project',
          version: '1.0.0',
          dependencies: { express: '^4.0.0', pino: '^8.0.0' },
          devDependencies: { vitest: '^1.0.0' },
        })
      );

      const info = service.getNodeInfo();

      expect(info.packageInfo.name).toBe('test-project');
      expect(info.packageInfo.version).toBe('1.0.0');
      expect(info.packageInfo.dependencies).toBe(2);
      expect(info.packageInfo.devDependencies).toBe(1);
    });

    it('should return zero counts when package.json does not exist', () => {
      vi.mocked(fs.existsSync).mockReturnValue(false);

      const info = service.getNodeInfo();

      expect(info.packageInfo.dependencies).toBe(0);
      expect(info.packageInfo.devDependencies).toBe(0);
    });
  });
});
