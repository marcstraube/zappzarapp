/**
 * Tests for DevDashboard DocsService
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { DocsService } from '@backend/DevDashboard/services/DocsService';
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

describe('DevDashboard DocsService', () => {
  let service: DocsService;

  beforeEach(() => {
    service = new DocsService();
    vi.clearAllMocks();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  describe('getDocsStatus', () => {
    it('should return not available when docs do not exist', () => {
      vi.mocked(fs.existsSync).mockReturnValue(false);

      const status = service.getDocsStatus();

      expect(status.available).toBe(false);
      expect(status.outdated).toBe(false);
      expect(status.reportPath).toBe('/docs/api/node-backend/index.html');
      expect(status.message).toContain('typedoc');
    });

    it('should return available when docs exist and no source dir', () => {
      vi.mocked(fs.existsSync).mockImplementation((path) => {
        if (typeof path === 'string' && path.includes('docs/api/node-backend/index.html')) {
          return true;
        }
        return false; // src/node doesn't exist
      });
      vi.mocked(fs.statSync).mockReturnValue({
        mtimeMs: Date.now(),
        isFile: () => true,
      } as fs.Stats);

      const status = service.getDocsStatus();

      expect(status.available).toBe(true);
      expect(status.reportPath).toBe('/docs/api/node-backend/index.html');
    });

    it('should return fresh status when docs are newer than source', () => {
      const docsTime = Date.now();

      vi.mocked(fs.existsSync).mockImplementation((path) => {
        return typeof path === 'string' && path.includes('docs/api/node-backend/index.html');
      });
      vi.mocked(fs.statSync).mockReturnValue({
        mtimeMs: docsTime,
        isFile: () => true,
      } as fs.Stats);

      const status = service.getDocsStatus();

      expect(status.available).toBe(true);
      expect(status.outdated).toBe(false);
      expect(status.message).toBe('Documentation available');
    });
  });

  describe('generateDocs', () => {
    it('should fail when pnpm is not found', async () => {
      vi.mocked(fs.existsSync).mockReturnValue(false);

      const result = await service.generateDocs();

      expect(result.success).toBe(false);
      expect(result.message).toContain('pnpm not found');
    });
  });
});
