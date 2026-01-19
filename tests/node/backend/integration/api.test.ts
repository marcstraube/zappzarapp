import { describe, it, expect, beforeAll } from 'vitest';
import request from 'supertest';
import { createApp } from '@backend/app';
import type { Express } from 'express';

// Response type interfaces for type safety
interface HealthResponse {
  status: string;
  service: string;
  timestamp: string;
}

interface ReadyResponse extends HealthResponse {
  uptime: number;
  environment: string;
  checks: {
    database: { status: string };
    redis: { status: string };
  };
}

interface StatusResponse {
  timestamp: string;
  environment: string;
  services: {
    database: { status: string };
    redis: { status: string };
    'node-frontend': { status: string };
  };
}

interface EchoResponse {
  echo: unknown;
  timestamp: string;
}

interface ErrorResponse {
  error: string;
  timestamp?: string;
}

describe('API Integration Tests', () => {
  let app: Express;

  beforeAll(() => {
    app = createApp();
  });

  describe('GET /health (liveness)', () => {
    it('should return health status', async () => {
      const response = await request(app).get('/health').expect(200);

      expect(response.body).toHaveProperty('status', 'ok');
      expect(response.body).toHaveProperty('service', 'node-backend');
      expect(response.body).toHaveProperty('timestamp');
    });

    it('should return valid timestamp', async () => {
      const response = await request(app).get('/health').expect(200);
      const body = response.body as HealthResponse;

      const timestamp = new Date(body.timestamp);
      expect(timestamp.getTime()).not.toBeNaN();
    });
  });

  describe('GET /ready (readiness)', () => {
    it('should return readiness status', async () => {
      const response = await request(app).get('/ready');

      // Status can be 200 (ok) or 503 (degraded/unhealthy)
      expect([200, 503]).toContain(response.status);
      expect(response.body).toHaveProperty('status');
      expect(response.body).toHaveProperty('service', 'node-backend');
      expect(response.body).toHaveProperty('timestamp');
      expect(response.body).toHaveProperty('uptime');
      expect(response.body).toHaveProperty('environment');
      expect(response.body).toHaveProperty('checks');
    });

    it('should include database and redis checks', async () => {
      const response = await request(app).get('/ready');
      const body = response.body as ReadyResponse;

      expect(body.checks).toHaveProperty('database');
      expect(body.checks).toHaveProperty('redis');
    });
  });

  describe('GET /status (overview)', () => {
    it('should return status overview', async () => {
      const response = await request(app).get('/status').expect(200);

      expect(response.body).toHaveProperty('timestamp');
      expect(response.body).toHaveProperty('environment');
      expect(response.body).toHaveProperty('services');
    });

    it('should include all services', async () => {
      const response = await request(app).get('/status').expect(200);
      const body = response.body as StatusResponse;

      expect(body.services).toHaveProperty('database');
      expect(body.services).toHaveProperty('redis');
      expect(body.services).toHaveProperty('node-frontend');
    });
  });

  describe('GET /api/hello', () => {
    it('should return hello world message', async () => {
      const response = await request(app).get('/api/hello').expect(200);

      expect(response.body).toHaveProperty('message', 'Hello, World!');
      expect(response.body).toHaveProperty('timestamp');
      expect(response.body).toHaveProperty('server', 'Node.js + Express');
    });

    it('should greet with custom name', async () => {
      const response = await request(app).get('/api/hello?name=Alice').expect(200);

      expect(response.body).toHaveProperty('message', 'Hello, Alice!');
    });

    it('should handle special characters in name', async () => {
      const response = await request(app).get('/api/hello?name=John%20Doe').expect(200);

      expect(response.body).toHaveProperty('message', 'Hello, John Doe!');
    });
  });

  describe('POST /api/echo', () => {
    it('should echo back JSON data', async () => {
      const testData = { test: 'data', number: 123 };
      const response = await request(app)
        .post('/api/echo')
        .send(testData)
        .set('Content-Type', 'application/json')
        .expect(200);

      const body = response.body as EchoResponse;
      expect(body).toHaveProperty('echo');
      expect(body.echo).toEqual(testData);
      expect(body).toHaveProperty('timestamp');
    });

    it('should handle empty object', async () => {
      const response = await request(app)
        .post('/api/echo')
        .send({})
        .set('Content-Type', 'application/json')
        .expect(200);
      const body = response.body as EchoResponse;

      expect(body.echo).toEqual({});
    });

    it('should handle nested objects', async () => {
      const nestedData = {
        user: {
          name: 'John',
          age: 30,
          address: {
            city: 'Berlin',
            country: 'Germany',
          },
        },
      };

      const response = await request(app)
        .post('/api/echo')
        .send(nestedData)
        .set('Content-Type', 'application/json')
        .expect(200);
      const body = response.body as EchoResponse;

      expect(body.echo).toEqual(nestedData);
    });
  });

  describe('Security Headers', () => {
    it('should include security headers', async () => {
      const response = await request(app).get('/health').expect(200);

      expect(response.headers['x-content-type-options']).toBe('nosniff');
      expect(response.headers['x-frame-options']).toBe('SAMEORIGIN');
      expect(response.headers['x-xss-protection']).toBe('1; mode=block');
    });
  });

  describe('CORS Headers', () => {
    it('should include CORS headers', async () => {
      const response = await request(app).get('/health').expect(200);

      expect(response.headers['access-control-allow-origin']).toBe('*');
    });

    it('should handle OPTIONS request', async () => {
      await request(app).options('/api/hello').expect(200);
    });
  });

  describe('404 Not Found', () => {
    it('should return 404 for unknown routes', async () => {
      const response = await request(app).get('/unknown/route').expect(404);

      expect(response.body).toHaveProperty('error', 'Not Found');
      expect(response.body).toHaveProperty('path', '/unknown/route');
      expect(response.body).toHaveProperty('method', 'GET');
    });
  });

  describe('Error Handling', () => {
    it('should handle 500 errors with error handler', async () => {
      // /test-error route is available in non-production environments
      const response = await request(app).get('/test-error').expect(500);
      const body = response.body as ErrorResponse;

      expect(body).toHaveProperty('error');
      expect(body).toHaveProperty('timestamp');
      expect(body.error).toBe('Test error message');
    });

    it('should show detailed error in development mode', async () => {
      // In development, the /test-error route should return the actual error message
      const response = await request(app).get('/test-error').expect(500);
      const body = response.body as ErrorResponse;

      // Development mode should show the actual error message
      expect(body.error).toBe('Test error message');
      expect(body.error).not.toBe('Internal Server Error');
    });
  });
});
