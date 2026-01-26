/**
 * Tests for DevDashboard Controller
 *
 * Coverage target: ~80% of DevDashboardController.ts (131 lines)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import type { Request, Response } from 'express';
import { DevDashboardController } from '@backend/DevDashboard/controllers/DevDashboardController';
import type {
  CoverageServiceInterface,
  DocsServiceInterface,
  QualityServiceInterface,
  SystemServiceInterface,
} from '@backend/DevDashboard/services/CoverageService';

// Mock services
const createMockCoverageService = (): CoverageServiceInterface => ({
  getCoverageStatus: vi.fn().mockReturnValue({
    exists: true,
    lastGenerated: new Date('2024-01-01T00:00:00Z'),
    reportPath: 'build/coverage/node/index.html',
    thresholds: { lines: 80, functions: 80, branches: 80, statements: 80 },
  }),
  runCoverage: vi.fn().mockResolvedValue({
    success: true,
    message: 'Coverage generated',
    reportPath: 'build/coverage/node/index.html',
  }),
});

const createMockDocsService = (): DocsServiceInterface => ({
  getDocsStatus: vi.fn().mockReturnValue({
    exists: true,
    lastGenerated: new Date('2024-01-01T00:00:00Z'),
    reportPath: 'build/docs/index.html',
  }),
  generateDocs: vi.fn().mockResolvedValue({
    success: true,
    message: 'Docs generated',
    reportPath: 'build/docs/index.html',
  }),
});

const createMockQualityService = (): QualityServiceInterface => ({
  getNodeQualityMetrics: vi.fn().mockReturnValue({
    eslint: { available: true, command: 'pnpm lint' },
    prettier: { available: true, command: 'pnpm format:check' },
    typescript: { available: true, command: 'pnpm typecheck' },
    vitest: { available: true, command: 'pnpm test' },
  }),
});

const createMockSystemService = (): SystemServiceInterface => ({
  getNodeInfo: vi.fn().mockReturnValue({
    nodeVersion: 'v20.11.0',
    npmVersion: '10.0.0',
    pnpmVersion: '8.0.0',
    environment: 'test',
    uptime: 12345,
    memoryUsage: { heapUsed: 50, heapTotal: 100, external: 5, rss: 150 },
    packageInfo: {
      dependencies: 10,
      devDependencies: 20,
      name: 'test',
      version: '1.0.0',
    },
  }),
});

// Mock request and response
const createMockRequest = (overrides = {}): Partial<Request> => ({
  method: 'GET',
  url: '/dev-dashboard/node/status',
  ...overrides,
});

const createMockResponse = (): Partial<Response> => {
  const res: Partial<Response> = {
    json: vi.fn().mockReturnThis(),
    status: vi.fn().mockReturnThis(),
  };
  return res;
};

describe('DevDashboard Controller', () => {
  let controller: DevDashboardController;
  let mockCoverageService: CoverageServiceInterface;
  let mockDocsService: DocsServiceInterface;
  let mockQualityService: QualityServiceInterface;
  let mockSystemService: SystemServiceInterface;

  beforeEach(() => {
    mockCoverageService = createMockCoverageService();
    mockDocsService = createMockDocsService();
    mockQualityService = createMockQualityService();
    mockSystemService = createMockSystemService();

    controller = new DevDashboardController(
      mockCoverageService,
      mockDocsService,
      mockQualityService,
      mockSystemService
    );
  });

  describe('getStatus', () => {
    it('should return overall status with all service data', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getStatus(req, res);

      expect(res.json).toHaveBeenCalledWith(
        expect.objectContaining({
          status: 'ok',
          timestamp: expect.any(String),
          node: expect.any(Object),
          runtime: expect.any(Object),
          coverage: expect.any(Object),
          quality: expect.any(Object),
        })
      );
    });

    it('should include node version information', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getStatus(req, res);

      const call = (res.json as ReturnType<typeof vi.fn>).mock.calls[0][0];
      expect(call.system).toHaveProperty('nodeVersion');
      expect(mockSystemService.getNodeInfo).toHaveBeenCalled();
    });

    it('should include runtime information in system object', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getStatus(req, res);

      const call = (res.json as ReturnType<typeof vi.fn>).mock.calls[0][0];
      expect(call.system).toHaveProperty('uptime');
      expect(call.system).toHaveProperty('memoryUsage');
      expect(mockSystemService.getNodeInfo).toHaveBeenCalled();
    });
  });

  describe('getSystem', () => {
    it('should return system information', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getSystem(req, res);

      expect(res.json).toHaveBeenCalledWith(
        expect.objectContaining({
          nodeVersion: expect.any(String),
          uptime: expect.any(Number),
        })
      );
    });

    it('should call system service method', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getSystem(req, res);

      expect(mockSystemService.getNodeInfo).toHaveBeenCalled();
    });
  });

  describe('getQuality', () => {
    it('should return quality metrics', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getQuality(req, res);

      expect(res.json).toHaveBeenCalledWith(
        expect.objectContaining({
          eslint: expect.any(Object),
          prettier: expect.any(Object),
          typescript: expect.any(Object),
          vitest: expect.any(Object),
        })
      );
    });

    it('should call quality service', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getQuality(req, res);

      expect(mockQualityService.getNodeQualityMetrics).toHaveBeenCalled();
    });
  });

  describe('getCoverageStatus', () => {
    it('should return coverage status', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getCoverageStatus(req, res);

      expect(res.json).toHaveBeenCalledWith(
        expect.objectContaining({
          exists: expect.any(Boolean),
          reportPath: expect.any(String),
        })
      );
    });

    it('should call coverage service', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getCoverageStatus(req, res);

      expect(mockCoverageService.getCoverageStatus).toHaveBeenCalled();
    });
  });

  describe('generateCoverage', () => {
    it('should generate coverage report', async () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      await controller.generateCoverage(req, res);

      expect(res.json).toHaveBeenCalledWith(
        expect.objectContaining({
          success: true,
          message: expect.any(String),
        })
      );
    });

    it('should call coverage service generate method', async () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      await controller.generateCoverage(req, res);

      expect(mockCoverageService.runCoverage).toHaveBeenCalled();
    });

    it('should handle generation errors', async () => {
      mockCoverageService.runCoverage = vi
        .fn()
        .mockResolvedValue({ success: false, message: 'Generation failed' });

      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      await controller.generateCoverage(req, res);

      expect(res.json).toHaveBeenCalledWith(
        expect.objectContaining({
          success: false,
          message: 'Generation failed',
        })
      );
    });
  });

  describe('getDocsStatus', () => {
    it('should return docs status', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getDocsStatus(req, res);

      expect(res.json).toHaveBeenCalledWith(
        expect.objectContaining({
          exists: expect.any(Boolean),
          reportPath: expect.any(String),
        })
      );
    });

    it('should call docs service', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getDocsStatus(req, res);

      expect(mockDocsService.getDocsStatus).toHaveBeenCalled();
    });
  });

  describe('generateDocs', () => {
    it('should generate documentation', async () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      await controller.generateDocs(req, res);

      expect(res.json).toHaveBeenCalledWith(
        expect.objectContaining({
          success: true,
          message: expect.any(String),
        })
      );
    });

    it('should call docs service generate method', async () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      await controller.generateDocs(req, res);

      expect(mockDocsService.generateDocs).toHaveBeenCalled();
    });

    it('should handle generation errors', async () => {
      mockDocsService.generateDocs = vi
        .fn()
        .mockResolvedValue({ success: false, message: 'Generation failed' });

      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      await controller.generateDocs(req, res);

      expect(res.json).toHaveBeenCalledWith(
        expect.objectContaining({
          success: false,
          message: 'Generation failed',
        })
      );
    });
  });

  describe('Response Format', () => {
    it('should return consistent response format', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getStatus(req, res);

      const call = (res.json as ReturnType<typeof vi.fn>).mock.calls[0][0];
      expect(call).toHaveProperty('timestamp');
      expect(call).toHaveProperty('system');
      expect(call).toHaveProperty('coverage');
      expect(call).toHaveProperty('docs');
      expect(call).toHaveProperty('quality');
    });
  });
});
