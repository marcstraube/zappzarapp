/**
 * DevDashboard Module
 *
 * Development Dashboard for Node.js backend.
 * Only loaded when NODE_ENV=development.
 */

export { createDevDashboardRouter } from './routes/index.js';
export { DevDashboardController } from './controllers/DevDashboardController.js';
export { CoverageService } from './services/CoverageService.js';
export { DocsService } from './services/DocsService.js';
export { QualityService } from './services/QualityService.js';
export { SystemService } from './services/SystemService.js';
