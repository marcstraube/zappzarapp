/**
 * Tests for Database Configuration (12-Factor App compliant)
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';

// Helper to create fresh module import with custom env
async function loadDatabaseConfig(env: Record<string, string | undefined>) {
  // Clear all DB-related env vars
  delete process.env.DATABASE_URL;
  delete process.env.DB_TYPE;
  delete process.env.DB_HOST;
  delete process.env.DB_PORT;
  delete process.env.DB_NAME;
  delete process.env.DB_USER;
  delete process.env.DB_PASSWORD;
  delete process.env.DB_PASSWORD_FILE;
  delete process.env.DB_SSL_CA;
  delete process.env.DB_SSL_VERIFY;

  // Set new env vars
  Object.entries(env).forEach(([key, value]) => {
    if (value !== undefined) {
      process.env[key] = value;
    }
  });

  // Reset module cache and re-import
  vi.resetModules();
  return await import('@node/config/database');
}

describe('Database Configuration', () => {
  const originalEnv = { ...process.env };

  beforeEach(() => {
    vi.resetModules();
  });

  afterEach(() => {
    // Restore original env
    process.env = { ...originalEnv };
  });

  describe('getDatabaseConfig', () => {
    describe('parsing DATABASE_URL', () => {
      it('should parse PostgreSQL URL correctly', async () => {
        const { getDatabaseConfig } = await loadDatabaseConfig({
          DATABASE_URL: 'postgresql://myuser:mypass@dbhost:5432/mydb',
        });

        const config = getDatabaseConfig();

        expect(config.type).toBe('postgres');
        expect(config.host).toBe('dbhost');
        expect(config.port).toBe(5432);
        expect(config.name).toBe('mydb');
        expect(config.user).toBe('myuser');
        expect(config.password).toBe('mypass');
      });

      it('should parse postgres:// URL as PostgreSQL', async () => {
        const { getDatabaseConfig } = await loadDatabaseConfig({
          DATABASE_URL: 'postgres://user:pass@host:5432/db',
        });

        const config = getDatabaseConfig();

        expect(config.type).toBe('postgres');
      });

      it('should parse MySQL URL correctly', async () => {
        const { getDatabaseConfig } = await loadDatabaseConfig({
          DATABASE_URL: 'mysql://myuser:mypass@dbhost:3306/mydb',
        });

        const config = getDatabaseConfig();

        expect(config.type).toBe('mysql');
        expect(config.host).toBe('dbhost');
        expect(config.port).toBe(3306);
        expect(config.name).toBe('mydb');
        expect(config.user).toBe('myuser');
        expect(config.password).toBe('mypass');
      });

      it('should decode URL-encoded password', async () => {
        // Password: p@ss:word/123 (URL-encoded: p%40ss%3Aword%2F123)
        const { getDatabaseConfig } = await loadDatabaseConfig({
          DATABASE_URL: 'postgresql://user:p%40ss%3Aword%2F123@host:5432/db',
        });

        const config = getDatabaseConfig();

        expect(config.password).toBe('p@ss:word/123');
      });

      it('should use default port when not specified in URL', async () => {
        const { getDatabaseConfig } = await loadDatabaseConfig({
          DATABASE_URL: 'postgresql://user:pass@host/db',
        });

        const config = getDatabaseConfig();

        expect(config.port).toBe(5432);
      });

      it('should use default port for MySQL when not specified', async () => {
        const { getDatabaseConfig } = await loadDatabaseConfig({
          DATABASE_URL: 'mysql://user:pass@host/db',
        });

        const config = getDatabaseConfig();

        expect(config.port).toBe(3306);
      });

      it('should use default user when not specified in URL', async () => {
        const { getDatabaseConfig } = await loadDatabaseConfig({
          DATABASE_URL: 'postgresql://:pass@host:5432/db',
        });

        const config = getDatabaseConfig();

        expect(config.user).toBe('app');
      });
    });

    describe('loading from individual variables', () => {
      it('should load PostgreSQL config from individual vars', async () => {
        const { getDatabaseConfig } = await loadDatabaseConfig({
          DB_TYPE: 'postgres',
          DB_HOST: 'myhost',
          DB_PORT: '5433',
          DB_NAME: 'mydb',
          DB_USER: 'myuser',
          DB_PASSWORD: 'mypass',
        });

        const config = getDatabaseConfig();

        expect(config.type).toBe('postgres');
        expect(config.host).toBe('myhost');
        expect(config.port).toBe(5433);
        expect(config.name).toBe('mydb');
        expect(config.user).toBe('myuser');
        expect(config.password).toBe('mypass');
      });

      it('should load MySQL config from individual vars', async () => {
        const { getDatabaseConfig } = await loadDatabaseConfig({
          DB_TYPE: 'mysql',
          DB_HOST: 'myhost',
          DB_PORT: '3307',
          DB_NAME: 'mydb',
          DB_USER: 'myuser',
          DB_PASSWORD: 'mypass',
        });

        const config = getDatabaseConfig();

        expect(config.type).toBe('mysql');
        expect(config.host).toBe('myhost');
        expect(config.port).toBe(3307);
      });

      it('should use default values when no env vars are set', async () => {
        const { getDatabaseConfig } = await loadDatabaseConfig({});

        const config = getDatabaseConfig();

        expect(config.type).toBe('postgres');
        expect(config.host).toBe('postgres');
        expect(config.port).toBe(5432);
        expect(config.name).toBe('app');
        expect(config.user).toBe('app');
        expect(config.password).toBe('secret');
      });

      it('should use mariadb as default host when DB_TYPE is mysql', async () => {
        const { getDatabaseConfig } = await loadDatabaseConfig({
          DB_TYPE: 'mysql',
        });

        const config = getDatabaseConfig();

        expect(config.host).toBe('mariadb');
        expect(config.port).toBe(3306);
      });
    });

    describe('precedence', () => {
      it('should prefer DATABASE_URL over individual vars', async () => {
        const { getDatabaseConfig } = await loadDatabaseConfig({
          DATABASE_URL: 'postgresql://urluser:urlpass@urlhost:5555/urldb',
          DB_TYPE: 'mysql',
          DB_HOST: 'individualhost',
          DB_PORT: '3306',
          DB_NAME: 'individualdb',
          DB_USER: 'individualuser',
          DB_PASSWORD: 'individualpass',
        });

        const config = getDatabaseConfig();

        expect(config.type).toBe('postgres');
        expect(config.host).toBe('urlhost');
        expect(config.port).toBe(5555);
        expect(config.name).toBe('urldb');
        expect(config.user).toBe('urluser');
        expect(config.password).toBe('urlpass');
      });

      it('should fall back to individual vars when DATABASE_URL is empty', async () => {
        const { getDatabaseConfig } = await loadDatabaseConfig({
          DATABASE_URL: '',
          DB_TYPE: 'mysql',
          DB_HOST: 'myhost',
          DB_PORT: '3306',
          DB_NAME: 'mydb',
          DB_USER: 'myuser',
          DB_PASSWORD: 'mypass',
        });

        const config = getDatabaseConfig();

        expect(config.type).toBe('mysql');
        expect(config.host).toBe('myhost');
      });
    });
  });

  describe('getDatabaseUrl', () => {
    it('should generate PostgreSQL URL from config', async () => {
      const { getDatabaseUrl } = await loadDatabaseConfig({
        DB_TYPE: 'postgres',
        DB_HOST: 'myhost',
        DB_PORT: '5432',
        DB_NAME: 'mydb',
        DB_USER: 'myuser',
        DB_PASSWORD: 'mypass',
      });

      const url = getDatabaseUrl();

      expect(url).toBe('postgresql://myuser:mypass@myhost:5432/mydb');
    });

    it('should generate MySQL URL from config', async () => {
      const { getDatabaseUrl } = await loadDatabaseConfig({
        DB_TYPE: 'mysql',
        DB_HOST: 'myhost',
        DB_PORT: '3306',
        DB_NAME: 'mydb',
        DB_USER: 'myuser',
        DB_PASSWORD: 'mypass',
      });

      const url = getDatabaseUrl();

      expect(url).toBe('mysql://myuser:mypass@myhost:3306/mydb');
    });

    it('should encode special characters in password', async () => {
      const { getDatabaseUrl } = await loadDatabaseConfig({
        DB_TYPE: 'postgres',
        DB_HOST: 'host',
        DB_PORT: '5432',
        DB_NAME: 'db',
        DB_USER: 'user',
        DB_PASSWORD: 'p@ss:word/123',
      });

      const url = getDatabaseUrl();

      expect(url).toContain('p%40ss%3Aword%2F123');
    });

    it('should accept custom config parameter', async () => {
      const { getDatabaseUrl } = await loadDatabaseConfig({});

      const url = getDatabaseUrl({
        type: 'mysql',
        host: 'customhost',
        port: 3307,
        name: 'customdb',
        user: 'customuser',
        password: 'custompass',
      });

      expect(url).toBe('mysql://customuser:custompass@customhost:3307/customdb');
    });
  });

  describe('isPostgres', () => {
    it('should return true for postgres type', async () => {
      const { isPostgres } = await loadDatabaseConfig({
        DB_TYPE: 'postgres',
      });

      expect(isPostgres()).toBe(true);
    });

    it('should return false for mysql type', async () => {
      const { isPostgres } = await loadDatabaseConfig({
        DB_TYPE: 'mysql',
      });

      expect(isPostgres()).toBe(false);
    });

    it('should accept custom config parameter', async () => {
      const { isPostgres } = await loadDatabaseConfig({});

      expect(
        isPostgres({
          type: 'postgres',
          host: '',
          port: 0,
          name: '',
          user: '',
          password: '',
        }),
      ).toBe(true);
      expect(
        isPostgres({
          type: 'mysql',
          host: '',
          port: 0,
          name: '',
          user: '',
          password: '',
        }),
      ).toBe(false);
    });
  });

  describe('isMySQL', () => {
    it('should return true for mysql type', async () => {
      const { isMySQL } = await loadDatabaseConfig({
        DB_TYPE: 'mysql',
      });

      expect(isMySQL()).toBe(true);
    });

    it('should return false for postgres type', async () => {
      const { isMySQL } = await loadDatabaseConfig({
        DB_TYPE: 'postgres',
      });

      expect(isMySQL()).toBe(false);
    });

    it('should accept custom config parameter', async () => {
      const { isMySQL } = await loadDatabaseConfig({});

      expect(
        isMySQL({
          type: 'mysql',
          host: '',
          port: 0,
          name: '',
          user: '',
          password: '',
        }),
      ).toBe(true);
      expect(
        isMySQL({
          type: 'postgres',
          host: '',
          port: 0,
          name: '',
          user: '',
          password: '',
        }),
      ).toBe(false);
    });
  });

  describe('databaseConfig singleton', () => {
    it('should export a pre-configured singleton', async () => {
      const { databaseConfig, getDatabaseConfig } = await loadDatabaseConfig({
        DB_TYPE: 'postgres',
        DB_HOST: 'singletonhost',
      });

      expect(databaseConfig).toBeDefined();
      expect(databaseConfig.type).toBe('postgres');
      expect(databaseConfig.host).toBe('singletonhost');
      expect(databaseConfig).toEqual(getDatabaseConfig());
    });
  });

  describe('getSslConfig', () => {
    it('should return SSL config from environment variables', async () => {
      const { getSslConfig } = await loadDatabaseConfig({
        DB_TYPE: 'mysql',
        DB_SSL_CA: '/custom/path/to/ca.crt',
        DB_SSL_VERIFY: 'true',
      });

      const ssl = getSslConfig();

      expect(ssl.ca).toBe('/custom/path/to/ca.crt');
      expect(ssl.verify).toBe(true);
    });

    it('should default SSL verify to false', async () => {
      const { getSslConfig } = await loadDatabaseConfig({
        DB_TYPE: 'postgres',
      });

      const ssl = getSslConfig();

      expect(ssl.verify).toBe(false);
    });

    it('should return empty CA for postgres without explicit config', async () => {
      const { getSslConfig } = await loadDatabaseConfig({
        DB_TYPE: 'postgres',
      });

      const ssl = getSslConfig();

      // PostgreSQL doesn't auto-detect internal cert
      expect(ssl.ca).toBe('');
    });
  });

  describe('hasSsl', () => {
    it('should return false when CA is not set', async () => {
      const { hasSsl } = await loadDatabaseConfig({
        DB_TYPE: 'postgres',
      });

      expect(hasSsl()).toBe(false);
    });

    it('should return false when CA file does not exist', async () => {
      const { hasSsl } = await loadDatabaseConfig({
        DB_TYPE: 'mysql',
        DB_SSL_CA: '/nonexistent/path/to/ca.crt',
      });

      expect(hasSsl()).toBe(false);
    });
  });

  describe('system CA bundle', () => {
    it('should resolve "system" to system CA bundle path', async () => {
      const { getSslConfig } = await loadDatabaseConfig({
        DB_TYPE: 'mysql',
        DB_SSL_CA: 'system',
      });

      const ssl = getSslConfig();
      // In test environment, system CA bundle may or may not exist
      const systemCaPath = '/etc/ssl/certs/ca-certificates.crt';
      const { existsSync } = await import('fs');
      if (existsSync(systemCaPath)) {
        expect(ssl.ca).toBe(systemCaPath);
      } else {
        expect(ssl.ca).toBe('');
      }
    });

    it('should handle "system" case-insensitively', async () => {
      const { getSslConfig } = await loadDatabaseConfig({
        DB_TYPE: 'postgres',
        DB_SSL_CA: 'SYSTEM',
      });

      const ssl = getSslConfig();
      const systemCaPath = '/etc/ssl/certs/ca-certificates.crt';
      const { existsSync } = await import('fs');
      if (existsSync(systemCaPath)) {
        expect(ssl.ca).toBe(systemCaPath);
      }
    });

    it('should work with postgres when "system" is set', async () => {
      const { getSslConfig } = await loadDatabaseConfig({
        DB_TYPE: 'postgres',
        DB_SSL_CA: 'system',
      });

      const ssl = getSslConfig();
      const systemCaPath = '/etc/ssl/certs/ca-certificates.crt';
      const { existsSync } = await import('fs');
      if (existsSync(systemCaPath)) {
        expect(ssl.ca).toBe(systemCaPath);
      }
    });
  });

  describe('PostgreSQL sslmode', () => {
    it('should return empty sslmode by default', async () => {
      const { getPostgresSslMode } = await loadDatabaseConfig({
        DB_TYPE: 'postgres',
      });

      expect(getPostgresSslMode()).toBe('');
    });

    it('should return verify-full when SSL_VERIFY is true', async () => {
      const { getPostgresSslMode } = await loadDatabaseConfig({
        DB_TYPE: 'postgres',
        DB_SSL_CA: 'system',
        DB_SSL_VERIFY: 'true',
      });

      const systemCaPath = '/etc/ssl/certs/ca-certificates.crt';
      const { existsSync } = await import('fs');
      if (existsSync(systemCaPath)) {
        expect(getPostgresSslMode()).toBe('verify-full');
      }
    });

    it('should return require when SSL_VERIFY is false', async () => {
      const { getPostgresSslMode } = await loadDatabaseConfig({
        DB_TYPE: 'postgres',
        DB_SSL_CA: 'system',
        DB_SSL_VERIFY: 'false',
      });

      const systemCaPath = '/etc/ssl/certs/ca-certificates.crt';
      const { existsSync } = await import('fs');
      if (existsSync(systemCaPath)) {
        expect(getPostgresSslMode()).toBe('require');
      }
    });

    it('should include sslmode in URL when configured', async () => {
      const { getDatabaseUrl } = await loadDatabaseConfig({
        DB_TYPE: 'postgres',
        DB_HOST: 'myhost',
        DB_PORT: '5432',
        DB_NAME: 'mydb',
        DB_USER: 'myuser',
        DB_PASSWORD: 'mypass',
        DB_SSL_CA: 'system',
        DB_SSL_VERIFY: 'true',
      });

      const systemCaPath = '/etc/ssl/certs/ca-certificates.crt';
      const { existsSync } = await import('fs');
      if (existsSync(systemCaPath)) {
        const url = getDatabaseUrl();
        expect(url).toContain('?sslmode=verify-full');
      }
    });

    it('should not include sslmode in MySQL URL', async () => {
      const { getDatabaseUrl } = await loadDatabaseConfig({
        DB_TYPE: 'mysql',
        DB_SSL_CA: 'system',
        DB_SSL_VERIFY: 'true',
      });

      const url = getDatabaseUrl();
      expect(url).not.toContain('sslmode');
    });
  });

  describe('roundtrip tests', () => {
    it('should parse and regenerate equivalent URL for PostgreSQL', async () => {
      const originalUrl = 'postgresql://myuser:mypass@myhost:5432/mydb';
      const { getDatabaseConfig, getDatabaseUrl } = await loadDatabaseConfig({
        DATABASE_URL: originalUrl,
      });

      const config = getDatabaseConfig();
      const regeneratedUrl = getDatabaseUrl(config);

      expect(regeneratedUrl).toBe(originalUrl);
    });

    it('should parse and regenerate equivalent URL for MySQL', async () => {
      const originalUrl = 'mysql://myuser:mypass@myhost:3306/mydb';
      const { getDatabaseConfig, getDatabaseUrl } = await loadDatabaseConfig({
        DATABASE_URL: originalUrl,
      });

      const config = getDatabaseConfig();
      const regeneratedUrl = getDatabaseUrl(config);

      expect(regeneratedUrl).toBe(originalUrl);
    });

    it('should handle roundtrip with special characters in password', async () => {
      const originalUrl = 'postgresql://user:p%40ss%3Aword%2F123@host:5432/db';
      const { getDatabaseConfig, getDatabaseUrl } = await loadDatabaseConfig({
        DATABASE_URL: originalUrl,
      });

      const config = getDatabaseConfig();
      expect(config.password).toBe('p@ss:word/123');

      const regeneratedUrl = getDatabaseUrl(config);
      expect(regeneratedUrl).toBe(originalUrl);
    });
  });

  // ===========================================================================
  // Docker Secrets (_FILE) Support Tests
  // ===========================================================================

  describe('Docker Secrets (_FILE support)', () => {
    // Helper to create temp file with password content
    async function createTempFile(content: string): Promise<string> {
      const fs = await import('fs');
      const os = await import('os');
      const path = await import('path');
      const tempDir = os.tmpdir();
      const tempFile = path.join(tempDir, `db_password_test_${Date.now()}_${Math.random().toString(36).slice(2)}.txt`);
      fs.writeFileSync(tempFile, content);
      return tempFile;
    }

    // Helper to remove temp file
    async function removeTempFile(filePath: string): Promise<void> {
      const fs = await import('fs');
      fs.unlinkSync(filePath);
    }

    it('should read password from file when DB_PASSWORD_FILE is set', async () => {
      const tempFile = await createTempFile('secret_from_file\n');

      try {
        const { getDatabaseConfig } = await loadDatabaseConfig({
          DB_PASSWORD_FILE: tempFile,
        });

        const config = getDatabaseConfig();
        expect(config.password).toBe('secret_from_file');
      } finally {
        await removeTempFile(tempFile);
      }
    });

    it('should trim whitespace from password file content', async () => {
      const tempFile = await createTempFile('  password_with_spaces  \n\n');

      try {
        const { getDatabaseConfig } = await loadDatabaseConfig({
          DB_PASSWORD_FILE: tempFile,
        });

        const config = getDatabaseConfig();
        expect(config.password).toBe('password_with_spaces');
      } finally {
        await removeTempFile(tempFile);
      }
    });

    it('should prefer _FILE over direct env var', async () => {
      const tempFile = await createTempFile('from_file');

      try {
        const { getDatabaseConfig } = await loadDatabaseConfig({
          DB_PASSWORD_FILE: tempFile,
          DB_PASSWORD: 'from_env',
        });

        const config = getDatabaseConfig();
        expect(config.password).toBe('from_file');
      } finally {
        await removeTempFile(tempFile);
      }
    });

    it('should fall back to env var when file does not exist', async () => {
      const { getDatabaseConfig } = await loadDatabaseConfig({
        DB_PASSWORD_FILE: '/nonexistent/path/to/password.txt',
        DB_PASSWORD: 'fallback_password',
      });

      const config = getDatabaseConfig();
      expect(config.password).toBe('fallback_password');
    });

    it('should fall back to default when file does not exist and no env var', async () => {
      const { getDatabaseConfig } = await loadDatabaseConfig({
        DB_PASSWORD_FILE: '/nonexistent/path/to/password.txt',
      });

      const config = getDatabaseConfig();
      expect(config.password).toBe('secret');
    });

    it('should fall back to env var when _FILE path is empty', async () => {
      const { getDatabaseConfig } = await loadDatabaseConfig({
        DB_PASSWORD_FILE: '',
        DB_PASSWORD: 'env_password',
      });

      const config = getDatabaseConfig();
      expect(config.password).toBe('env_password');
    });
  });
});
