/**
 * Tests for DevDashboard CoverageService
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { CoverageService } from '@backend/DevDashboard/Services/CoverageService';
import * as fs from 'fs';

// Mock fs module
vi.mock('fs', () => ({
  existsSync: vi.fn(),
  statSync: vi.fn(),
  readdirSync: vi.fn(),
}));

// Mock child_process
vi.mock('child_process', () => ({
  spawn: vi.fn(),
}));

describe('DevDashboard CoverageService', () => {
  let service: CoverageService;

  beforeEach(() => {
    service = new CoverageService();
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('getCoverageStatus', () => {
    it('should return not available when coverage report does not exist', () => {
      vi.mocked(fs.existsSync).mockReturnValue(false);

      const status = service.getCoverageStatus();

      expect(status.available).toBe(false);
      expect(status.outdated).toBe(false);
      expect(status.reportPath).toBe('/build/coverage/node/index.html');
      expect(status.message).toContain('Run coverage');
    });

    it('should return available when coverage report exists and no source dirs', () => {
      // Coverage report exists, but source/test dirs don't
      vi.mocked(fs.existsSync).mockImplementation((path) => {
        return typeof path === 'string' && path.includes('coverage/node/index.html');
      });
      vi.mocked(fs.statSync).mockReturnValue({
        mtimeMs: Date.now(),
        isFile: () => true,
      } as fs.Stats);

      const status = service.getCoverageStatus();

      expect(status.available).toBe(true);
      expect(status.reportPath).toBe('/build/coverage/node/index.html');
    });

    it('should return fresh status when coverage is newer than source', () => {
      const reportTime = Date.now();

      vi.mocked(fs.existsSync).mockImplementation((path) => {
        return typeof path === 'string' && path.includes('coverage/node/index.html');
      });
      vi.mocked(fs.statSync).mockReturnValue({
        mtimeMs: reportTime,
        isFile: () => true,
      } as fs.Stats);

      const status = service.getCoverageStatus();

      expect(status.available).toBe(true);
      expect(status.outdated).toBe(false);
      expect(status.message).toBe('Coverage report available');
    });
  });

  describe('runCoverage', () => {
    it('should fail when pnpm is not found', async () => {
      vi.mocked(fs.existsSync).mockReturnValue(false);

      const result = await service.runCoverage();

      expect(result.success).toBe(false);
      expect(result.message).toContain('pnpm not found');
    });
  });
});
