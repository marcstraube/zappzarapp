/**
 * DevDashboard Integration Tests
 *
 * Tests for the Node.js DevDashboard API endpoints.
 * These tests run against the actual Express app in development mode.
 */

import { describe, it, expect, beforeAll, afterAll } from 'vitest';
import request from 'supertest';
import { createApp } from '@backend/app';
import type { Express } from 'express';

// Response type interfaces
interface StatusResponse {
  timestamp: string;
  system: {
    nodeVersion: string;
    environment: string;
    uptime: number;
    memoryUsage: {
      heapUsed: number;
      heapTotal: number;
    };
    packageInfo: {
      dependencies: number;
      devDependencies: number;
    };
  };
  coverage: {
    available: boolean;
    outdated: boolean;
    reportPath: string;
    message: string;
  };
  docs: {
    available: boolean;
    outdated: boolean;
    reportPath: string;
    message: string;
  };
  quality: {
    eslint: { enabled: boolean };
    prettier: { enabled: boolean };
    typescript: { enabled: boolean };
    vitest: { enabled: boolean };
  };
}

interface QualityResponse {
  timestamp: string;
  metrics: {
    eslint: { enabled: boolean; configFile: string; status: string; message: string };
    prettier: { enabled: boolean; configFile: string; status: string; message: string };
    typescript: { enabled: boolean; configFile: string; status: string; message: string };
    vitest: { enabled: boolean; configFile: string; status: string; message: string };
  };
}

interface SystemResponse {
  timestamp: string;
  info: {
    nodeVersion: string;
    environment: string;
    uptime: number;
    memoryUsage: {
      heapUsed: number;
      heapTotal: number;
      external: number;
      rss: number;
    };
    packageInfo: {
      dependencies: number;
      devDependencies: number;
    };
  };
}

interface CoverageStatusResponse {
  timestamp: string;
  available: boolean;
  outdated: boolean;
  reportPath: string;
  message: string;
}

interface DocsStatusResponse {
  timestamp: string;
  available: boolean;
  outdated: boolean;
  reportPath: string;
  message: string;
}

describe('DevDashboard Integration Tests', () => {
  let app: Express;
  const originalEnv = process.env;

  beforeAll(() => {
    // Set NODE_ENV to development to enable DevDashboard routes
    process.env = { ...originalEnv, NODE_ENV: 'development' };
    app = createApp();
  });

  afterAll(() => {
    process.env = originalEnv;
  });

  describe('GET /dev-dashboard/node/status', () => {
    it('should return overall status', async () => {
      const response = await request(app).get('/dev-dashboard/node/status').expect(200);

      const body = response.body as StatusResponse;
      expect(body).toHaveProperty('timestamp');
      expect(body).toHaveProperty('system');
      expect(body).toHaveProperty('coverage');
      expect(body).toHaveProperty('docs');
      expect(body).toHaveProperty('quality');
    });

    it('should include system information', async () => {
      const response = await request(app).get('/dev-dashboard/node/status').expect(200);

      const body = response.body as StatusResponse;
      expect(body.system).toHaveProperty('nodeVersion');
      expect(body.system).toHaveProperty('environment');
      expect(body.system).toHaveProperty('uptime');
      expect(body.system).toHaveProperty('memoryUsage');
    });

    it('should include coverage status', async () => {
      const response = await request(app).get('/dev-dashboard/node/status').expect(200);

      const body = response.body as StatusResponse;
      expect(body.coverage).toHaveProperty('available');
      expect(body.coverage).toHaveProperty('outdated');
      expect(body.coverage).toHaveProperty('reportPath');
      expect(body.coverage).toHaveProperty('message');
    });

    it('should include quality metrics', async () => {
      const response = await request(app).get('/dev-dashboard/node/status').expect(200);

      const body = response.body as StatusResponse;
      expect(body.quality).toHaveProperty('eslint');
      expect(body.quality).toHaveProperty('prettier');
      expect(body.quality).toHaveProperty('typescript');
      expect(body.quality).toHaveProperty('vitest');
    });
  });

  describe('GET /dev-dashboard/node/system', () => {
    it('should return system information', async () => {
      const response = await request(app).get('/dev-dashboard/node/system').expect(200);

      const body = response.body as SystemResponse;
      expect(body).toHaveProperty('timestamp');
      expect(body).toHaveProperty('info');
      expect(body.info).toHaveProperty('nodeVersion');
      expect(body.info).toHaveProperty('uptime');
      expect(body.info).toHaveProperty('memoryUsage');
      expect(body.info).toHaveProperty('packageInfo');
    });

    it('should return valid Node version', async () => {
      const response = await request(app).get('/dev-dashboard/node/system').expect(200);

      const body = response.body as SystemResponse;
      expect(body.info.nodeVersion).toMatch(/^v\d+\.\d+\.\d+/);
    });
  });

  describe('GET /dev-dashboard/node/quality', () => {
    it('should return quality metrics', async () => {
      const response = await request(app).get('/dev-dashboard/node/quality').expect(200);

      const body = response.body as QualityResponse;
      expect(body).toHaveProperty('timestamp');
      expect(body).toHaveProperty('metrics');
    });

    it('should include all quality tools', async () => {
      const response = await request(app).get('/dev-dashboard/node/quality').expect(200);

      const body = response.body as QualityResponse;
      expect(body.metrics).toHaveProperty('eslint');
      expect(body.metrics).toHaveProperty('prettier');
      expect(body.metrics).toHaveProperty('typescript');
      expect(body.metrics).toHaveProperty('vitest');
    });

    it('should have consistent tool structure', async () => {
      const response = await request(app).get('/dev-dashboard/node/quality').expect(200);

      const body = response.body as QualityResponse;
      const tools = ['eslint', 'prettier', 'typescript', 'vitest'] as const;

      for (const tool of tools) {
        expect(body.metrics[tool]).toHaveProperty('enabled');
        expect(body.metrics[tool]).toHaveProperty('status');
        expect(body.metrics[tool]).toHaveProperty('message');
      }
    });
  });

  describe('GET /dev-dashboard/node/coverage/status', () => {
    it('should return coverage status', async () => {
      const response = await request(app).get('/dev-dashboard/node/coverage/status').expect(200);

      const body = response.body as CoverageStatusResponse;
      expect(body).toHaveProperty('timestamp');
      expect(body).toHaveProperty('available');
      expect(body).toHaveProperty('outdated');
      expect(body).toHaveProperty('reportPath');
      expect(body).toHaveProperty('message');
    });

    it('should have correct report path', async () => {
      const response = await request(app).get('/dev-dashboard/node/coverage/status').expect(200);

      const body = response.body as CoverageStatusResponse;
      expect(body.reportPath).toBe('/build/coverage/node/index.html');
    });
  });

  describe('GET /dev-dashboard/node/docs/status', () => {
    it('should return docs status', async () => {
      const response = await request(app).get('/dev-dashboard/node/docs/status').expect(200);

      const body = response.body as DocsStatusResponse;
      expect(body).toHaveProperty('timestamp');
      expect(body).toHaveProperty('available');
      expect(body).toHaveProperty('outdated');
      expect(body).toHaveProperty('reportPath');
      expect(body).toHaveProperty('message');
    });

    it('should have correct report path', async () => {
      const response = await request(app).get('/dev-dashboard/node/docs/status').expect(200);

      const body = response.body as DocsStatusResponse;
      expect(body.reportPath).toBe('/docs/api/node-backend/index.html');
    });
  });

  describe('POST /dev-dashboard/node/coverage/generate', () => {
    // Skip this test - it tries to run vitest inside vitest causing recursion
    // The endpoint is manually testable via: curl -X POST https://localhost:3000/dev-dashboard/node/coverage/generate
    it.skip('should accept POST request', { timeout: 60000 }, async () => {
      const response = await request(app)
        .post('/dev-dashboard/node/coverage/generate')
        .set('Content-Type', 'application/json');

      expect(response.body).toHaveProperty('success');
      expect(response.body).toHaveProperty('message');
    });
  });

  describe('POST /dev-dashboard/node/docs/generate', () => {
    it('should accept POST request', { timeout: 60000 }, async () => {
      // This test might fail if pnpm/typedoc is not available in test environment
      // but it should at least accept the request and return a valid response structure
      const response = await request(app)
        .post('/dev-dashboard/node/docs/generate')
        .set('Content-Type', 'application/json');

      // Response should have the correct structure regardless of success/failure
      expect(response.body).toHaveProperty('success');
      expect(response.body).toHaveProperty('message');
    });
  });
});
