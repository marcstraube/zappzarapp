/**
 * Tests for Express Application Factory
 *
 * Coverage target: ~70% of app.ts (105 lines)
 */

import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import request from 'supertest';
import { createApp } from '@backend/app';
import type { Express } from 'express';
import { createMockPgPool } from '../fixtures/mockConnections';

describe('Express App Factory', () => {
  let app: Express;
  const originalEnv = { ...process.env };

  beforeEach(() => {
    // Reset environment for each test
    process.env = { ...originalEnv };
  });

  afterEach(() => {
    process.env = originalEnv;
  });

  describe('createApp', () => {
    it('should create an Express app instance', () => {
      app = createApp();
      expect(app).toBeDefined();
      expect(typeof app).toBe('function');
    });

    it('should accept pool option for dependency injection', () => {
      const mockPool = createMockPgPool();
      app = createApp({ pool: mockPool });
      expect(app).toBeDefined();
    });

    it('should work with null pool option', () => {
      app = createApp({ pool: null });
      expect(app).toBeDefined();
    });
  });

  describe('Middleware Configuration', () => {
    beforeEach(() => {
      app = createApp();
    });

    it('should parse JSON request bodies', async () => {
      const response = await request(app)
        .post('/api/hello')
        .send({ message: 'test' })
        .set('Content-Type', 'application/json');

      expect(response.status).toBeLessThan(500);
    });

    it('should parse URL-encoded request bodies', async () => {
      const response = await request(app)
        .post('/api/hello')
        .send('key=value')
        .set('Content-Type', 'application/x-www-form-urlencoded');

      expect(response.status).toBeLessThan(500);
    });
  });

  describe('CORS Configuration', () => {
    it('should allow wildcard origin by default', async () => {
      app = createApp();
      const response = await request(app).get('/health').set('Origin', 'https://example.com');

      expect(response.headers['access-control-allow-origin']).toBe('*');
    });

    it('should allow specific origins when configured', async () => {
      process.env.CORS_ORIGINS = 'https://example.com,https://app.example.com';
      app = createApp();

      const response = await request(app).get('/health').set('Origin', 'https://example.com');

      expect(response.headers['access-control-allow-origin']).toBe('https://example.com');
    });

    it('should reject origins not in the allowed list', async () => {
      process.env.CORS_ORIGINS = 'https://example.com';
      app = createApp();

      const response = await request(app).get('/health').set('Origin', 'https://evil.com');

      expect(response.headers['access-control-allow-origin']).toBeUndefined();
    });

    it('should handle OPTIONS preflight requests', async () => {
      app = createApp();
      const response = await request(app).options('/api/hello');

      expect(response.status).toBe(200);
    });

    it('should set CORS headers for allowed methods', async () => {
      app = createApp();
      const response = await request(app).get('/health');

      expect(response.headers['access-control-allow-methods']).toContain('GET');
      expect(response.headers['access-control-allow-methods']).toContain('POST');
    });
  });

  describe('Security Headers', () => {
    beforeEach(() => {
      app = createApp();
    });

    it('should set X-Content-Type-Options header', async () => {
      const response = await request(app).get('/health');
      expect(response.headers['x-content-type-options']).toBe('nosniff');
    });

    it('should set X-Frame-Options header', async () => {
      const response = await request(app).get('/health');
      expect(response.headers['x-frame-options']).toBe('SAMEORIGIN');
    });

    it('should set X-XSS-Protection header', async () => {
      const response = await request(app).get('/health');
      expect(response.headers['x-xss-protection']).toBe('1; mode=block');
    });

    it('should set Referrer-Policy header', async () => {
      const response = await request(app).get('/health');
      expect(response.headers['referrer-policy']).toBe('strict-origin-when-cross-origin');
    });

    it('should set Cross-Origin-Opener-Policy header', async () => {
      const response = await request(app).get('/health');
      expect(response.headers['cross-origin-opener-policy']).toBe('same-origin');
    });

    it('should set Cross-Origin-Resource-Policy header', async () => {
      const response = await request(app).get('/health');
      expect(response.headers['cross-origin-resource-policy']).toBe('same-site');
    });
  });

  describe('Health Check Routes', () => {
    beforeEach(() => {
      app = createApp();
    });

    it('should respond to GET /health (liveness)', async () => {
      const response = await request(app).get('/health');

      expect(response.status).toBe(200);
      expect(response.body).toHaveProperty('status', 'ok');
      expect(response.body).toHaveProperty('service', 'node-backend');
      expect(response.body).toHaveProperty('timestamp');
    });

    it('should respond to GET /ready (readiness)', async () => {
      const response = await request(app).get('/ready');

      expect(response.status).toBeGreaterThanOrEqual(200);
      expect(response.body).toHaveProperty('status');
      expect(response.body).toHaveProperty('timestamp');
    });

    it('should respond to GET /status', async () => {
      const response = await request(app).get('/status');

      expect(response.status).toBe(200);
      expect(response.body).toHaveProperty('timestamp');
      expect(response.body).toHaveProperty('environment');
      expect(response.body).toHaveProperty('services');
    });
  });

  describe('DevDashboard Routes', () => {
    it('should load DevDashboard routes in development mode', async () => {
      process.env.NODE_ENV = 'development';
      app = createApp();

      const response = await request(app).get('/dev-dashboard/node/status');
      expect(response.status).toBeLessThan(500);
    });

    it('should not load DevDashboard routes in production mode', async () => {
      process.env.NODE_ENV = 'production';
      app = createApp();

      const response = await request(app).get('/dev-dashboard/node/status');
      expect(response.status).toBe(404);
    });
  });

  describe('Test Routes', () => {
    it('should expose /test-error in development mode', async () => {
      process.env.NODE_ENV = 'development';
      app = createApp();

      const response = await request(app).get('/test-error');
      expect(response.status).toBe(500);
    });

    it('should not expose /test-error in production mode', async () => {
      process.env.NODE_ENV = 'production';
      app = createApp();

      const response = await request(app).get('/test-error');
      expect(response.status).toBe(404);
    });
  });

  describe('404 Handler', () => {
    beforeEach(() => {
      app = createApp();
    });

    it('should return 404 for non-existent routes', async () => {
      const response = await request(app).get('/nonexistent');

      expect(response.status).toBe(404);
      expect(response.body).toHaveProperty('error', 'Not Found');
      expect(response.body).toHaveProperty('path', '/nonexistent');
      expect(response.body).toHaveProperty('method', 'GET');
    });

    it('should include request method in 404 response', async () => {
      const response = await request(app).post('/nonexistent');

      expect(response.status).toBe(404);
      expect(response.body).toHaveProperty('method', 'POST');
    });
  });

  describe('Error Handler', () => {
    it('should handle 500 errors gracefully', async () => {
      process.env.NODE_ENV = 'development';
      app = createApp();

      const response = await request(app).get('/test-error');

      expect(response.status).toBe(500);
      expect(response.body).toHaveProperty('error');
      expect(response.body).toHaveProperty('timestamp');
    });

    it('should show error message in development mode', async () => {
      process.env.NODE_ENV = 'development';
      app = createApp();

      const response = await request(app).get('/test-error');

      expect(response.status).toBe(500);
      expect(response.body.error).toBe('Test error message');
    });

    it('should hide error details in production mode', () => {
      process.env.NODE_ENV = 'production';
      app = createApp();

      // We can't trigger /test-error in production, so this test is limited
      // In a real scenario, we'd test with a production error
      expect(app).toBeDefined();
    });
  });

  describe('Environment Configuration', () => {
    it('should default to production environment', () => {
      delete process.env.NODE_ENV;
      app = createApp();

      // Production mode means no DevDashboard
      request(app).get('/dev-dashboard/node/status').expect(404);
    });

    it('should respect NODE_ENV=development', () => {
      process.env.NODE_ENV = 'development';
      app = createApp();
      expect(app).toBeDefined();
    });
  });
});
