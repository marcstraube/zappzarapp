/**
 * Tests for HealthCheckService
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { HealthCheckService } from '@backend/Shared/HealthCheck/HealthCheckService';
import { Pool, PoolClient } from 'pg';

// Mock the redis module
vi.mock('redis', () => ({
  createClient: vi.fn(() => ({
    connect: vi.fn().mockResolvedValue(undefined),
    ping: vi.fn().mockResolvedValue('PONG'),
    quit: vi.fn().mockResolvedValue(undefined),
  })),
}));

// Mock http/https modules
vi.mock('http', () => ({
  request: vi.fn(),
}));

vi.mock('https', () => ({
  request: vi.fn(),
}));

describe('HealthCheckService', () => {
  const originalEnv = process.env;

  beforeEach(() => {
    vi.resetModules();
    process.env = { ...originalEnv };
  });

  afterEach(() => {
    process.env = originalEnv;
    vi.clearAllMocks();
  });

  describe('checkLiveness', () => {
    it('should return ok status', () => {
      const service = new HealthCheckService();
      const result = service.checkLiveness();

      expect(result.status).toBe('ok');
      expect(result.service).toBe('node-backend');
      expect(result.timestamp).toBeDefined();
    });

    it('should return valid ISO timestamp', () => {
      const service = new HealthCheckService();
      const result = service.checkLiveness();

      const parsedDate = new Date(result.timestamp);
      expect(parsedDate.toISOString()).toBe(result.timestamp);
    });
  });

  describe('checkReadiness', () => {
    it('should return disabled status when no services are enabled', async () => {
      process.env.ENABLE_DATABASE = 'false';
      process.env.ENABLE_REDIS = 'false';
      process.env.NODE_MODE = 'backend';

      const service = new HealthCheckService();
      const result = await service.checkReadiness();

      expect(result.status).toBe('ok');
      expect(result.service).toBe('node-backend');
      expect(result.checks['database']?.status).toBe('disabled');
      expect(result.checks['redis']?.status).toBe('disabled');
    });

    it('should include uptime', async () => {
      const service = new HealthCheckService();
      const result = await service.checkReadiness();

      expect(result.uptime).toBeGreaterThanOrEqual(0);
      expect(typeof result.uptime).toBe('number');
    });

    it('should include environment', async () => {
      process.env.NODE_ENV = 'test';

      const service = new HealthCheckService();
      const result = await service.checkReadiness();

      expect(result.environment).toBe('test');
    });
  });

  describe('checkStatus', () => {
    it('should return all services including disabled', async () => {
      process.env.ENABLE_DATABASE = 'false';
      process.env.ENABLE_REDIS = 'false';
      process.env.NODE_MODE = 'backend';

      const service = new HealthCheckService();
      const result = await service.checkStatus();

      expect(result.services['database']?.status).toBe('disabled');
      expect(result.services['redis']?.status).toBe('disabled');
      expect(result.services['node-frontend']?.status).toBe('disabled');
    });

    it('should include node-frontend when NODE_MODE includes framework', async () => {
      process.env.NODE_MODE = 'framework-api';
      process.env.ENABLE_DATABASE = 'false';
      process.env.ENABLE_REDIS = 'false';

      const service = new HealthCheckService();
      const result = await service.checkStatus();

      // Frontend check will fail in test (no server running), but should be included
      expect(result.services['node-frontend']).toBeDefined();
      expect(result.services['node-frontend']?.status).not.toBe('disabled');
    });
  });

  describe('database checks', () => {
    it('should return disabled when ENABLE_DATABASE is false', async () => {
      process.env.ENABLE_DATABASE = 'false';

      const service = new HealthCheckService();
      const result = await service.checkReadiness();

      expect(result.checks['database']?.status).toBe('disabled');
    });

    it('should return unhealthy when pool is not provided', async () => {
      process.env.ENABLE_DATABASE = 'true';

      const service = new HealthCheckService(null);
      const result = await service.checkReadiness();

      expect(result.checks['database']?.status).toBe('unhealthy');
      expect(result.checks['database']?.message).toBe('Database pool not configured');
    });

    it('should return ok when database query succeeds', async () => {
      process.env.ENABLE_DATABASE = 'true';

      // Create a mock pool with explicit mock functions
      const releaseMock = vi.fn();
      const mockClient = {
        query: vi.fn().mockResolvedValue({ rows: [{ '?column?': 1 }] }),
        release: releaseMock,
      } as unknown as PoolClient;

      const mockPool = {
        connect: vi.fn().mockResolvedValue(mockClient),
      } as unknown as Pool;

      const service = new HealthCheckService(mockPool);
      const result = await service.checkReadiness();

      expect(result.checks['database']?.status).toBe('ok');
      expect(result.checks['database']?.latency_ms).toBeDefined();
      expect(releaseMock).toHaveBeenCalled();
    });

    it('should return unhealthy when database query fails', async () => {
      process.env.ENABLE_DATABASE = 'true';

      const mockPool = {
        connect: vi.fn().mockRejectedValue(new Error('Connection refused')),
      } as unknown as Pool;

      const service = new HealthCheckService(mockPool);
      const result = await service.checkReadiness();

      expect(result.checks['database']?.status).toBe('unhealthy');
      expect(result.checks['database']?.message).toBe('Connection refused');
    });

    it('should include database type in response', async () => {
      process.env.ENABLE_DATABASE = 'true';
      process.env.DB_TYPE = 'postgres';

      const mockClient = {
        query: vi.fn().mockResolvedValue({ rows: [] }),
        release: vi.fn(),
      } as unknown as PoolClient;

      const mockPool = {
        connect: vi.fn().mockResolvedValue(mockClient),
      } as unknown as Pool;

      const service = new HealthCheckService(mockPool);
      const result = await service.checkReadiness();

      expect(result.checks['database']?.type).toBe('postgres');
    });
  });

  describe('redis checks', () => {
    it('should return disabled when ENABLE_REDIS is false', async () => {
      process.env.ENABLE_REDIS = 'false';

      const service = new HealthCheckService();
      const result = await service.checkReadiness();

      expect(result.checks['redis']?.status).toBe('disabled');
    });

    it('should return ok when redis ping succeeds', async () => {
      process.env.ENABLE_REDIS = 'true';
      process.env.REDIS_URL = 'redis://localhost:6379';

      const service = new HealthCheckService();
      const result = await service.checkReadiness();

      expect(result.checks['redis']?.status).toBe('ok');
      expect(result.checks['redis']?.latency_ms).toBeDefined();
    });
  });

  describe('overall status', () => {
    it('should be ok when all enabled services are healthy', async () => {
      process.env.ENABLE_DATABASE = 'true';
      process.env.ENABLE_REDIS = 'false';

      const mockClient = {
        query: vi.fn().mockResolvedValue({ rows: [] }),
        release: vi.fn(),
      } as unknown as PoolClient;

      const mockPool = {
        connect: vi.fn().mockResolvedValue(mockClient),
      } as unknown as Pool;

      const service = new HealthCheckService(mockPool);
      const result = await service.checkReadiness();

      expect(result.status).toBe('ok');
    });

    it('should be degraded when any service is unhealthy', async () => {
      process.env.ENABLE_DATABASE = 'true';
      process.env.ENABLE_REDIS = 'false';

      const mockPool = {
        connect: vi.fn().mockRejectedValue(new Error('Connection failed')),
      } as unknown as Pool;

      const service = new HealthCheckService(mockPool);
      const result = await service.checkReadiness();

      expect(result.status).toBe('degraded');
    });
  });

  describe('getServiceName', () => {
    it('should return node-backend', () => {
      const service = new HealthCheckService();
      expect(service.getServiceName()).toBe('node-backend');
    });
  });

  describe('getConfig', () => {
    it('should return current configuration', () => {
      process.env.ENABLE_DATABASE = 'true';
      process.env.ENABLE_REDIS = 'false';
      process.env.NODE_MODE = 'backend';
      process.env.NODE_ENV = 'test';

      const service = new HealthCheckService();
      const config = service.getConfig();

      expect(config.enableDatabase).toBe(true);
      expect(config.enableRedis).toBe(false);
      expect(config.nodeMode).toBe('backend');
      expect(config.environment).toBe('test');
    });

    it('should return a copy of the config', () => {
      const service = new HealthCheckService();
      const config1 = service.getConfig();
      const config2 = service.getConfig();

      expect(config1).not.toBe(config2);
      expect(config1).toEqual(config2);
    });
  });

  describe('NODE_MODE parsing', () => {
    it('should enable node-frontend for framework mode', () => {
      process.env.NODE_MODE = 'framework';

      const service = new HealthCheckService();
      const config = service.getConfig();

      expect(config.enableNodeFrontend).toBe(true);
    });

    it('should enable node-frontend for framework-api mode', () => {
      process.env.NODE_MODE = 'framework-api';

      const service = new HealthCheckService();
      const config = service.getConfig();

      expect(config.enableNodeFrontend).toBe(true);
    });

    it('should disable node-frontend for backend mode', () => {
      process.env.NODE_MODE = 'backend';

      const service = new HealthCheckService();
      const config = service.getConfig();

      expect(config.enableNodeFrontend).toBe(false);
    });

    it('should disable node-frontend for api mode', () => {
      process.env.NODE_MODE = 'api';

      const service = new HealthCheckService();
      const config = service.getConfig();

      expect(config.enableNodeFrontend).toBe(false);
    });

    it('should disable node-frontend for assets mode', () => {
      process.env.NODE_MODE = 'assets';

      const service = new HealthCheckService();
      const config = service.getConfig();

      expect(config.enableNodeFrontend).toBe(false);
    });

    it('should disable node-frontend for assets-api mode', () => {
      process.env.NODE_MODE = 'assets-api';

      const service = new HealthCheckService();
      const config = service.getConfig();

      expect(config.enableNodeFrontend).toBe(false);
    });
  });
});
