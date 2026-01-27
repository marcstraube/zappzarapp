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

      const jsonFn = res.json as ReturnType<typeof vi.fn>;
      const response = jsonFn.mock.calls[0]?.[0] as Record<string, unknown>;

      // Verify structure
      expect(response).toHaveProperty('timestamp');
      expect(response).toHaveProperty('system');
      expect(response).toHaveProperty('coverage');
      expect(response).toHaveProperty('docs');
      expect(response).toHaveProperty('quality');

      // Verify timestamp is valid ISO string
      expect(typeof response.timestamp).toBe('string');
      expect(new Date(response.timestamp as string).toISOString()).toBe(response.timestamp);

      // Verify system data matches mock
      const system = response.system as Record<string, unknown>;
      expect(system).toHaveProperty('nodeVersion', 'v20.11.0');
      expect(system).toHaveProperty('environment', 'test');

      // Verify coverage data matches mock
      const coverage = response.coverage as Record<string, unknown>;
      expect(coverage).toHaveProperty('available', true);
      expect(coverage).toHaveProperty('reportPath', 'build/coverage/node/index.html');

      // Verify docs data matches mock
      const docs = response.docs as Record<string, unknown>;
      expect(docs).toHaveProperty('available', true);
      expect(docs).toHaveProperty('reportPath', 'docs/api/node-backend/index.html');

      // Verify quality data matches mock
      const quality = response.quality as Record<string, unknown>;
      expect(quality).toHaveProperty('eslint');
      expect(quality).toHaveProperty('prettier');
      expect(quality).toHaveProperty('typescript');
      expect(quality).toHaveProperty('vitest');
    });

    it('should include node version information', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getStatus(req, res);

      const jsonFn = res.json as ReturnType<typeof vi.fn>;
      const response = jsonFn.mock.calls[0]?.[0] as Record<string, unknown>;
      const system = response.system as Record<string, unknown>;

      expect(system.nodeVersion).toBe('v20.11.0');
      expect(mockSystemService.getNodeInfo).toHaveBeenCalled();
    });

    it('should include runtime information in system object', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getStatus(req, res);

      const jsonFn = res.json as ReturnType<typeof vi.fn>;
      const response = jsonFn.mock.calls[0]?.[0] as Record<string, unknown>;
      const system = response.system as Record<string, unknown>;

      expect(system.uptime).toBe(12345);
      expect(system.memoryUsage).toEqual({ heapUsed: 50, heapTotal: 100, external: 5, rss: 150 });
      expect(mockSystemService.getNodeInfo).toHaveBeenCalled();
    });
  });

  describe('getSystem', () => {
    it('should return system information', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getSystem(req, res);

      const jsonFn = res.json as ReturnType<typeof vi.fn>;
      const response = jsonFn.mock.calls[0]?.[0] as Record<string, unknown>;

      expect(response).toHaveProperty('timestamp');
      expect(typeof response.timestamp).toBe('string');
      expect(new Date(response.timestamp as string).toISOString()).toBe(response.timestamp);

      const info = response.info as Record<string, unknown>;
      expect(info.nodeVersion).toBe('v20.11.0');
      expect(info.uptime).toBe(12345);
      expect(info.environment).toBe('test');
      expect(info.memoryUsage).toEqual({ heapUsed: 50, heapTotal: 100, external: 5, rss: 150 });
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

      const jsonFn = res.json as ReturnType<typeof vi.fn>;
      const response = jsonFn.mock.calls[0]?.[0] as Record<string, unknown>;

      expect(response).toHaveProperty('timestamp');
      expect(typeof response.timestamp).toBe('string');
      expect(new Date(response.timestamp as string).toISOString()).toBe(response.timestamp);

      const metrics = response.metrics as Record<string, unknown>;
      expect(metrics).toHaveProperty('eslint');
      expect(metrics).toHaveProperty('prettier');
      expect(metrics).toHaveProperty('typescript');
      expect(metrics).toHaveProperty('vitest');

      // Verify eslint config details
      const eslint = metrics.eslint as Record<string, unknown>;
      expect(eslint.enabled).toBe(true);
      expect(eslint.configFile).toBe('eslint.config.js');
      expect(eslint.status).toBe('configured');

      // Verify prettier config details
      const prettier = metrics.prettier as Record<string, unknown>;
      expect(prettier.enabled).toBe(true);
      expect(prettier.configFile).toBe('.prettierrc');
      expect(prettier.status).toBe('configured');
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

      const jsonFn = res.json as ReturnType<typeof vi.fn>;
      const response = jsonFn.mock.calls[0]?.[0] as Record<string, unknown>;

      expect(response).toHaveProperty('timestamp');
      expect(typeof response.timestamp).toBe('string');
      expect(new Date(response.timestamp as string).toISOString()).toBe(response.timestamp);

      expect(response.available).toBe(true);
      expect(response.outdated).toBe(false);
      expect(response.reportPath).toBe('build/coverage/node/index.html');
      expect(response.message).toBe('Coverage report is available');
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

      const jsonFn = res.json as ReturnType<typeof vi.fn>;
      const response = jsonFn.mock.calls[0]?.[0] as Record<string, unknown>;

      expect(response.success).toBe(true);
      expect(response.message).toBe('Coverage generated');
      expect(response.reportPath).toBe('build/coverage/node/index.html');
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

      const jsonFn = res.json as ReturnType<typeof vi.fn>;
      const response = jsonFn.mock.calls[0]?.[0] as Record<string, unknown>;

      expect(response.success).toBe(false);
      expect(response.message).toBe('Generation failed');
    });
  });

  describe('getDocsStatus', () => {
    it('should return docs status', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getDocsStatus(req, res);

      const jsonFn = res.json as ReturnType<typeof vi.fn>;
      const response = jsonFn.mock.calls[0]?.[0] as Record<string, unknown>;

      expect(response).toHaveProperty('timestamp');
      expect(typeof response.timestamp).toBe('string');
      expect(new Date(response.timestamp as string).toISOString()).toBe(response.timestamp);

      expect(response.available).toBe(true);
      expect(response.outdated).toBe(false);
      expect(response.reportPath).toBe('docs/api/node-backend/index.html');
      expect(response.message).toBe('Documentation is available');
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

      const jsonFn = res.json as ReturnType<typeof vi.fn>;
      const response = jsonFn.mock.calls[0]?.[0] as Record<string, unknown>;

      expect(response.success).toBe(true);
      expect(response.message).toBe('Docs generated');
      expect(response.reportPath).toBe('docs/api/node-backend/index.html');
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

      const jsonFn = res.json as ReturnType<typeof vi.fn>;
      const response = jsonFn.mock.calls[0]?.[0] as Record<string, unknown>;

      expect(response.success).toBe(false);
      expect(response.message).toBe('Generation failed');
    });
  });

  describe('Response Format', () => {
    it('should return consistent response format', () => {
      const req = createMockRequest() as Request;
      const res = createMockResponse() as Response;

      controller.getStatus(req, res);

      const jsonFn = res.json as ReturnType<typeof vi.fn>;
      const response = jsonFn.mock.calls[0]?.[0] as Record<string, unknown>;

      expect(response).toHaveProperty('timestamp');
      expect(response).toHaveProperty('system');
      expect(response).toHaveProperty('coverage');
      expect(response).toHaveProperty('docs');
      expect(response).toHaveProperty('quality');

      // Verify each section has expected structure
      expect(typeof response.timestamp).toBe('string');
      expect(typeof response.system).toBe('object');
      expect(typeof response.coverage).toBe('object');
      expect(typeof response.docs).toBe('object');
      expect(typeof response.quality).toBe('object');
    });
  });
});
