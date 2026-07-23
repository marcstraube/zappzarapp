/**
 * DevDashboard Routes
 *
 * Routes for the Node.js Development Dashboard.
 * These routes are only available in development mode.
 */

import { Router } from 'express';
import { DevDashboardController } from '../Controllers/DevDashboardController.js';
import { CoverageService } from '../Services/CoverageService.js';
import { DocsService } from '../Services/DocsService.js';
import { QualityService } from '../Services/QualityService.js';
import { SystemService } from '../Services/SystemService.js';

/**
 * Create the DevDashboard router
 *
 * @public Boilerplate API — shipped for consumers, no in-tree default importer expected.
 */
export function createDevDashboardRouter(): Router {
  const router = Router();

  // Initialize services
  const coverageService = new CoverageService();
  const docsService = new DocsService();
  const qualityService = new QualityService();
  const systemService = new SystemService();

  // Initialize controller with services
  const controller = new DevDashboardController(
    coverageService,
    docsService,
    qualityService,
    systemService
  );

  // Status routes
  router.get('/status', controller.getStatus);
  router.get('/system', controller.getSystem);
  router.get('/quality', controller.getQuality);

  // Coverage routes
  router.get('/coverage/status', controller.getCoverageStatus);
  router.post('/coverage/generate', controller.generateCoverage);

  // Documentation routes
  router.get('/docs/status', controller.getDocsStatus);
  router.post('/docs/generate', controller.generateDocs);

  return router;
}
