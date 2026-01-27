/**
 * Tests for DevDashboard Controller
 *
 * Coverage target: ~80% of DevDashboardController.ts (131 lines)
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import type { Request, Response } from 'express';
import { DevDashboardController } from '@backend/DevDashboard/Controllers/DevDashboardController';
import {
  CoverageService,
  type CoverageStatus,
  type CoverageResult,
} from '@backend/DevDashboard/Services/CoverageService';
import {
  DocsService,
  type DocsStatus,
  type DocsResult,
} from '@backend/DevDashboard/Services/DocsService';
import { QualityService, type QualityMetrics } from '@backend/DevDashboard/Services/QualityService';
import { SystemService, type NodeInfo } from '@backend/DevDashboard/Services/SystemService';

// Mock services using vi.mocked to create type-safe mocks
const createMockCoverageService = (): CoverageService => {
  const mock = {
    getCoverageStatus: vi.fn().mockReturnValue({
      available: true,
      outdated: false,
      reportPath: 'build/coverage/node/index.html',
      message: 'Coverage report is available',
    } as CoverageStatus),
    runCoverage: vi.fn().mockResolvedValue({
      success: true,
      message: 'Coverage generated',
      reportPath: 'build/coverage/node/index.html',
    } as CoverageResult),
  };
  return mock as unknown as CoverageService;
};

const createMockDocsService = (): DocsService => {
  const mock = {
    getDocsStatus: vi.fn().mockReturnValue({
      available: true,
      outdated: false,
      reportPath: 'docs/api/node-backend/index.html',
      message: 'Documentation is available',
    } as DocsStatus),
    generateDocs: vi.fn().mockResolvedValue({
      success: true,
      message: 'Docs generated',
      reportPath: 'docs/api/node-backend/index.html',
    } as DocsResult),
  };
  return mock as unknown as DocsService;
};

const createMockQualityService = (): QualityService => {
  const mock = {
    getNodeQualityMetrics: vi.fn().mockReturnValue({
      eslint: {
        enabled: true,
        configFile: 'eslint.config.js',
        status: 'configured',
        message: 'ESLint is configured',
      },
      prettier: {
        enabled: true,
        configFile: '.prettierrc',
        status: 'configured',
        message: 'Prettier is configured',
      },
      typescript: {
        enabled: true,
        configFile: 'tsconfig.json',
        status: 'configured',
        message: 'TypeScript is configured',
      },
      vitest: {
        enabled: true,
        configFile: 'vitest.config.ts',
        status: 'configured',
        message: 'Vitest is configured',
      },
    } as QualityMetrics),
  };
  return mock as unknown as QualityService;
};

const createMockSystemService = (): SystemService => {
  const mock = {
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
    } as NodeInfo),
  };
  return mock as unknown as SystemService;
};

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
  let mockCoverageService: CoverageService;
  let mockDocsService: DocsService;
  let mockQualityService: QualityService;
  let mockSystemService: SystemService;

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
          timestamp: expect.any(String),
          system: expect.any(Object),
          coverage: expect.any(Object),
          docs: expect.any(Object),
          quality: expect.any(Object),
        })
      );
    });

    it('should include node version information', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getStatus(req, res);

      const jsonFn = res.json as ReturnType<typeof vi.fn>;
      const call = jsonFn.mock.calls[0]?.[0];
      expect(call?.system).toHaveProperty('nodeVersion');
      expect(mockSystemService.getNodeInfo).toHaveBeenCalled();
    });

    it('should include runtime information in system object', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getStatus(req, res);

      const jsonFn = res.json as ReturnType<typeof vi.fn>;
      const call = jsonFn.mock.calls[0]?.[0];
      expect(call?.system).toHaveProperty('uptime');
      expect(call?.system).toHaveProperty('memoryUsage');
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
          timestamp: expect.any(String),
          info: expect.objectContaining({
            nodeVersion: expect.any(String),
            uptime: expect.any(Number),
          }),
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
          timestamp: expect.any(String),
          metrics: expect.objectContaining({
            eslint: expect.any(Object),
            prettier: expect.any(Object),
            typescript: expect.any(Object),
            vitest: expect.any(Object),
          }),
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
          timestamp: expect.any(String),
          available: expect.any(Boolean),
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
        .mockResolvedValue({ success: false, message: 'Generation failed' } as CoverageResult);

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
          timestamp: expect.any(String),
          available: expect.any(Boolean),
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
        .mockResolvedValue({ success: false, message: 'Generation failed' } as DocsResult);

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

      const jsonFn = res.json as ReturnType<typeof vi.fn>;
      const call = jsonFn.mock.calls[0]?.[0];
      expect(call).toHaveProperty('timestamp');
      expect(call).toHaveProperty('system');
      expect(call).toHaveProperty('coverage');
      expect(call).toHaveProperty('docs');
      expect(call).toHaveProperty('quality');
    });
  });
});
