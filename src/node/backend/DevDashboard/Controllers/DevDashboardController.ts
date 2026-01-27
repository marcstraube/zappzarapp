/**
 * DevDashboard Controller
 *
 * Handles all DevDashboard API requests for Node.js.
 */

import { Request, Response } from 'express';
import { CoverageService, CoverageResult, CoverageStatus } from '../Services/CoverageService.js';
import { DocsService, DocsResult, DocsStatus } from '../Services/DocsService.js';
import { QualityService, QualityMetrics } from '../Services/QualityService.js';
import { SystemService, NodeInfo } from '../Services/SystemService.js';

interface StatusResponse {
  timestamp: string;
  system: NodeInfo;
  coverage: CoverageStatus;
  docs: DocsStatus;
  quality: QualityMetrics;
}

interface CoverageGenerateResponse {
  success: boolean;
  message: string;
  reportPath?: string;
  output?: string;
}

interface DocsGenerateResponse {
  success: boolean;
  message: string;
  reportPath?: string;
  output?: string;
}

export class DevDashboardController {
  private readonly coverageService: CoverageService;
  private readonly docsService: DocsService;
  private readonly qualityService: QualityService;
  private readonly systemService: SystemService;

  constructor(
    coverageService: CoverageService,
    docsService: DocsService,
    qualityService: QualityService,
    systemService: SystemService
  ) {
    this.coverageService = coverageService;
    this.docsService = docsService;
    this.qualityService = qualityService;
    this.systemService = systemService;
  }

  /**
   * GET /dev-dashboard/node/status
   *
   * Returns overall Node.js DevDashboard status
   */
  getStatus = (_req: Request, res: Response): void => {
    const response: StatusResponse = {
      timestamp: new Date().toISOString(),
      system: this.systemService.getNodeInfo(),
      coverage: this.coverageService.getCoverageStatus(),
      docs: this.docsService.getDocsStatus(),
      quality: this.qualityService.getNodeQualityMetrics(),
    };

    res.json(response);
  };

  /**
   * POST /dev-dashboard/node/coverage/generate
   *
   * Generate Node.js test coverage report
   */
  generateCoverage = async (_req: Request, res: Response): Promise<void> => {
    const result: CoverageResult = await this.coverageService.runCoverage();

    const response: CoverageGenerateResponse = {
      success: result.success,
      message: result.message,
      reportPath: result.reportPath,
      output: result.output,
    };

    const statusCode = result.success ? 200 : 500;
    res.status(statusCode).json(response);
  };

  /**
   * POST /dev-dashboard/node/docs/generate
   *
   * Generate Node.js API documentation
   */
  generateDocs = async (_req: Request, res: Response): Promise<void> => {
    const result: DocsResult = await this.docsService.generateDocs();

    const response: DocsGenerateResponse = {
      success: result.success,
      message: result.message,
      reportPath: result.reportPath,
      output: result.output,
    };

    const statusCode = result.success ? 200 : 500;
    res.status(statusCode).json(response);
  };

  /**
   * GET /dev-dashboard/node/quality
   *
   * Returns Node.js quality metrics
   */
  getQuality = (_req: Request, res: Response): void => {
    const metrics = this.qualityService.getNodeQualityMetrics();

    res.json({
      timestamp: new Date().toISOString(),
      metrics,
    });
  };

  /**
   * GET /dev-dashboard/node/system
   *
   * Returns Node.js system information
   */
  getSystem = (_req: Request, res: Response): void => {
    const info = this.systemService.getNodeInfo();

    res.json({
      timestamp: new Date().toISOString(),
      info,
    });
  };

  /**
   * GET /dev-dashboard/node/coverage/status
   *
   * Returns coverage report status
   */
  getCoverageStatus = (_req: Request, res: Response): void => {
    const status = this.coverageService.getCoverageStatus();

    res.json({
      timestamp: new Date().toISOString(),
      ...status,
    });
  };

  /**
   * GET /dev-dashboard/node/docs/status
   *
   * Returns documentation status
   */
  getDocsStatus = (_req: Request, res: Response): void => {
    const status = this.docsService.getDocsStatus();

    res.json({
      timestamp: new Date().toISOString(),
      ...status,
    });
  };
}
