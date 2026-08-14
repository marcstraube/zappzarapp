/**
 * DevDashboard Routes
 *
 * Routes for the Node.js Development Dashboard.
 * These routes are only available in development mode.
 */

import { Router, type NextFunction, type Request, type Response } from 'express';
import { DevDashboardController } from '../Controllers/DevDashboardController.js';
import { CoverageService } from '../Services/CoverageService.js';
import { DocsService } from '../Services/DocsService.js';
import { QualityService } from '../Services/QualityService.js';
import { SystemService } from '../Services/SystemService.js';

/**
 * Cross-site request protection for the mutating dashboard endpoints
 *
 * The dashboard has no authentication, so without this check any website
 * could fire POSTs (coverage/docs generators) at it from the developer's
 * browser. Modern browsers label every request with Sec-Fetch-Site; when
 * that header exists it alone decides (a cross-site fetch can still carry
 * custom headers after a CORS preflight, so X-Requested-With must not
 * override it). Legacy clients without the header fall back to an Origin
 * comparison, and header-less scripted clients (curl) opt in explicitly
 * via X-Requested-With: XMLHttpRequest.
 */
function rejectCrossSite(req: Request, res: Response, next: NextFunction): void {
  const fetchSite = req.headers['sec-fetch-site'];
  let sameOrigin: boolean;

  if (typeof fetchSite === 'string' && fetchSite !== '') {
    sameOrigin = fetchSite === 'same-origin' || fetchSite === 'none';
  } else if (typeof req.headers.origin === 'string' && req.headers.origin !== '') {
    sameOrigin = req.headers.origin === `${req.protocol}://${req.headers.host ?? ''}`;
  } else {
    sameOrigin = req.headers['x-requested-with'] === 'XMLHttpRequest';
  }

  if (!sameOrigin) {
    res.status(403).json({ error: 'Cross-site request rejected' });
    return;
  }

  next();
}

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
  router.post('/coverage/generate', rejectCrossSite, controller.generateCoverage);

  // Documentation routes
  router.get('/docs/status', controller.getDocsStatus);
  router.post('/docs/generate', rejectCrossSite, controller.generateDocs);

  return router;
}
