import { describe, it, expect, beforeEach, afterAll } from 'vitest';
import {
  shouldVerifyTls,
  getCaPath,
  getTlsSocketOptions,
  getHttpsTlsOptions,
} from '@backend/Shared/TlsConfig';

describe('TLS Configuration', () => {
  const originalEnv = process.env;

  beforeEach(() => {
    process.env = { ...originalEnv };
  });

  afterAll(() => {
    process.env = originalEnv;
  });

  describe('shouldVerifyTls', () => {
    it('should verify in production', () => {
      process.env.NODE_ENV = 'production';
      delete process.env.TLS_VERIFY_INTERNAL;
      expect(shouldVerifyTls()).toBe(true);
    });

    it('should not verify in development', () => {
      process.env.NODE_ENV = 'development';
      delete process.env.TLS_VERIFY_INTERNAL;
      expect(shouldVerifyTls()).toBe(false);
    });

    it('should respect explicit override to disable', () => {
      process.env.NODE_ENV = 'production';
      process.env.TLS_VERIFY_INTERNAL = 'false';
      expect(shouldVerifyTls()).toBe(false);
    });

    it('should respect explicit override to enable', () => {
      process.env.NODE_ENV = 'development';
      process.env.TLS_VERIFY_INTERNAL = 'true';
      expect(shouldVerifyTls()).toBe(true);
    });

    it('should default to production when NODE_ENV not set (secure by default)', () => {
      delete process.env.NODE_ENV;
      delete process.env.TLS_VERIFY_INTERNAL;
      expect(shouldVerifyTls()).toBe(true);
    });
  });

  describe('getCaPath', () => {
    it('should return default path when TLS_CA_PATH not set', () => {
      delete process.env.TLS_CA_PATH;
      expect(getCaPath()).toBe('/etc/ssl/certs/internal-ca.crt');
    });

    it('should return custom path when TLS_CA_PATH is set', () => {
      process.env.TLS_CA_PATH = '/custom/ca.crt';
      expect(getCaPath()).toBe('/custom/ca.crt');
    });
  });

  describe('getTlsSocketOptions', () => {
    it('should include rejectUnauthorized based on environment', () => {
      process.env.NODE_ENV = 'development';
      delete process.env.TLS_VERIFY_INTERNAL;
      const options = getTlsSocketOptions();
      expect(options.tls).toBe(true);
      expect(options.rejectUnauthorized).toBe(false);
    });

    it('should enable verification in production', () => {
      process.env.NODE_ENV = 'production';
      delete process.env.TLS_VERIFY_INTERNAL;
      const options = getTlsSocketOptions();
      expect(options.tls).toBe(true);
      expect(options.rejectUnauthorized).toBe(true);
    });
  });

  describe('getHttpsTlsOptions', () => {
    it('should disable verification in development', () => {
      process.env.NODE_ENV = 'development';
      delete process.env.TLS_VERIFY_INTERNAL;
      const options = getHttpsTlsOptions();
      expect(options.rejectUnauthorized).toBe(false);
    });

    it('should enable verification in production', () => {
      process.env.NODE_ENV = 'production';
      delete process.env.TLS_VERIFY_INTERNAL;
      const options = getHttpsTlsOptions();
      expect(options.rejectUnauthorized).toBe(true);
    });
  });
});
