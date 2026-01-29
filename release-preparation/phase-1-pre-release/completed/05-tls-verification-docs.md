# Task 04: Implement Environment-Based TLS Verification

## Priority

HIGH - Pre-Release

## Estimated Effort

45 minutes

## Dependency

**Requires Task 06 (Certificate Architecture) to be completed first.**

Task 06 creates the CA infrastructure (`docker/certs/ca/`, `internal/ca.crt`)
that this task's TLS helpers will use for certificate verification.

## Context

During the Security and Backend Services reviews, it was identified that several
service clients disable TLS certificate verification. The current implementation
is intentional for development (self-signed certificates) but should
automatically enable verification in production following security-by-design
principles.

**Current State:** TLS verification hardcoded to disabled everywhere.

**Target State:** Auto-detect environment, verify in production (using internal
CA), skip in development.

## Security-by-Design Principle

- **Development** (`ENV=development`): TLS enabled, verification disabled
  (self-signed)
- **Production** (`ENV=production`): TLS enabled, verification enabled (using
  internal CA from Task 06)
- **No manual intervention required** - behavior based on environment

**Note:** PHP uses `ENV`, Node.js uses `NODE_ENV` (industry standard).

## Affected Files

| File                                                     | Language   | TLS Setting                        |
| -------------------------------------------------------- | ---------- | ---------------------------------- |
| `src/node/backend/server.ts`                             | TypeScript | ✅ Already env-aware (NODE_ENV)    |
| `src/node/backend/services/CacheService.ts`              | TypeScript | ❌ Hardcoded `false`               |
| `src/node/backend/services/HealthCheckService.ts`        | TypeScript | ❌ Hardcoded `false` (3 places)    |
| `src/node/backend/db/pool.ts`                            | TypeScript | ✅ Already configurable            |
| `src/php/App/Infrastructure/Cache/RedisCache.php`        | PHP        | ❌ Hardcoded `false`               |
| `src/php/App/Infrastructure/Queue/RabbitMQQueue.php`     | PHP        | ❌ Hardcoded `false`               |
| `src/php/App/Infrastructure/HealthCheck.php`             | PHP        | ❌ Hardcoded `false` (5 places)    |
| `src/php/DevDashboard/Services/HealthCheckService.php`   | PHP        | ❌ Hardcoded `false`               |
| `src/php/App/Http/Controller/ExampleController.php`      | PHP        | ❌ Hardcoded `false` (internal)    |

## Implementation Steps

### Step 1: Add Environment Variables to .env

Add to `.env`:

```bash
# TLS Verification (auto-detected from ENV/NODE_ENV, override if needed)
# Development (ENV=development): verification disabled for self-signed certs
# Production (ENV=production): verification enabled using internal CA
#
# Override options:
# TLS_VERIFY_INTERNAL=false    # Force disable verification (e.g., external services)
# TLS_CA_PATH=/custom/ca.crt   # Custom CA certificate path
```

### Step 2: Create TLS Helper (Node.js)

Create `src/node/backend/utils/tls.ts`:

```typescript
import { readFileSync, existsSync } from 'fs';

/**
 * Default CA certificate path (from Task 06 Certificate Architecture)
 * Mounted via compose.yaml from docker/certs/internal/ca.crt
 */
const DEFAULT_CA_PATH = '/etc/ssl/certs/internal-ca.crt';

/**
 * TLS verification configuration based on environment
 * Development: disabled (self-signed certs)
 * Production: enabled with internal CA (trusted certs)
 */
export function shouldVerifyTls(): boolean {
  // Explicit override takes precedence
  const override = process.env.TLS_VERIFY_INTERNAL;
  if (override !== undefined) {
    return override === 'true';
  }

  // Default: verify in production only
  // Note: Defaults to 'production' (secure by default) - matches server.ts behavior
  const env = process.env.NODE_ENV ?? 'production';
  return env === 'production';
}

/**
 * Get CA certificate path
 */
export function getCaPath(): string {
  return process.env.TLS_CA_PATH ?? DEFAULT_CA_PATH;
}

/**
 * Get CA certificate buffer (if exists and verification enabled)
 */
export function getCaCert(): Buffer | undefined {
  if (!shouldVerifyTls()) {
    return undefined;
  }

  const caPath = getCaPath();
  if (existsSync(caPath)) {
    return readFileSync(caPath);
  }

  return undefined;
}

/**
 * Get TLS socket options for Node.js clients (e.g., Redis)
 */
export function getTlsSocketOptions(): {
  tls: true;
  rejectUnauthorized: boolean;
  ca?: Buffer;
} {
  const verify = shouldVerifyTls();
  return {
    tls: true,
    rejectUnauthorized: verify,
    ...(verify && { ca: getCaCert() }),
  };
}

/**
 * Get TLS options for HTTPS requests
 */
export function getHttpsTlsOptions(): {
  rejectUnauthorized: boolean;
  ca?: Buffer;
} {
  const verify = shouldVerifyTls();
  return {
    rejectUnauthorized: verify,
    ...(verify && { ca: getCaCert() }),
  };
}
```

### Step 3: Create TLS Helper (PHP)

Create `src/php/App/Infrastructure/TlsConfig.php`:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure;

/**
 * TLS verification configuration based on environment
 * Development: disabled (self-signed certs)
 * Production: enabled with internal CA (trusted certs)
 *
 * CA certificate path comes from Task 06 Certificate Architecture:
 * - Mounted via compose.yaml from docker/certs/internal/ca.crt
 * - Default: /etc/ssl/certs/internal-ca.crt
 */
final class TlsConfig
{
    /** Default CA path (from Task 06 Certificate Architecture) */
    private const DEFAULT_CA_PATH = '/etc/ssl/certs/internal-ca.crt';

    public static function shouldVerify(): bool
    {
        // Explicit override takes precedence
        $override = getenv('TLS_VERIFY_INTERNAL');
        if ($override !== false) {
            return $override === 'true';
        }

        // Default: verify in production only
        // Uses ENV variable (project standard), not APP_ENV
        $env = $_ENV['ENV'] ?? getenv('ENV') ?: 'production';
        return $env === 'production';
    }

    /**
     * Get CA certificate path
     */
    public static function getCaPath(): string
    {
        return $_ENV['TLS_CA_PATH'] ?? getenv('TLS_CA_PATH') ?: self::DEFAULT_CA_PATH;
    }

    /**
     * Get CA certificate path if verification is enabled and file exists
     */
    public static function getCaFile(): ?string
    {
        if (!self::shouldVerify()) {
            return null;
        }

        $caPath = self::getCaPath();
        return file_exists($caPath) ? $caPath : null;
    }

    /**
     * Get SSL context options for stream_context_create()
     *
     * @return array{verify_peer: bool, verify_peer_name: bool, allow_self_signed: bool, cafile?: string}
     */
    public static function getSslContextOptions(): array
    {
        $verify = self::shouldVerify();
        $options = [
            'verify_peer'       => $verify,
            'verify_peer_name'  => $verify,
            'allow_self_signed' => !$verify,
        ];

        $caFile = self::getCaFile();
        if ($caFile !== null) {
            $options['cafile'] = $caFile;
        }

        return $options;
    }

    /**
     * Get Redis stream context options
     *
     * @return array{stream: array{verify_peer: bool, verify_peer_name: bool, allow_self_signed: bool, cafile?: string}}
     */
    public static function getRedisStreamOptions(): array
    {
        return ['stream' => self::getSslContextOptions()];
    }
}
```

### Step 4: Update Node.js Services

**CacheService.ts** (line ~142):

```typescript
import { getTlsSocketOptions } from '../utils/tls';

// In connect() method:
socket: {
  connectTimeout: this.timeout,
  ...(useTls && getTlsSocketOptions()),
},
```

**HealthCheckService.ts** (3 places):

```typescript
import { getHttpsTlsOptions, getTlsSocketOptions } from '../utils/tls';

// For Redis connections:
...(useTls && getTlsSocketOptions()),

// For HTTPS requests:
...getHttpsTlsOptions(),
```

### Step 5: Update PHP Services

**RedisCache.php**:

```php
use App\Infrastructure\TlsConfig;

// Replace hardcoded array with:
TlsConfig::getRedisStreamOptions()
```

**RabbitMQQueue.php**:

```php
use App\Infrastructure\TlsConfig;

// Replace hardcoded array with:
TlsConfig::getSslContextOptions()
```

**HealthCheck.php** (5 places):

```php
use App\Infrastructure\TlsConfig;

// Replace all 'ssl' => [...] arrays with:
'ssl' => TlsConfig::getSslContextOptions(),
```

**DevDashboard/HealthCheckService.php**:

```php
// Note: DevDashboard is dev-only, keep hardcoded false
// (or import TlsConfig from App namespace)
```

### Step 6: Update ExampleController

The `ExampleController.php` makes internal HTTP calls. Update to use TlsConfig:

```php
use App\Infrastructure\TlsConfig;

'ssl' => TlsConfig::getSslContextOptions(),
```

### Step 7: Add Tests

**tests/node/unit/utils/tls.test.ts**:

```typescript
import { shouldVerifyTls, getCaPath, getTlsSocketOptions, getHttpsTlsOptions } from '@/backend/utils/tls';

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
      const options = getTlsSocketOptions();
      expect(options.tls).toBe(true);
      expect(options.rejectUnauthorized).toBe(false);
    });
  });
});
```

**tests/php/Unit/Infrastructure/TlsConfigTest.php**:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\TlsConfig;
use PHPUnit\Framework\TestCase;

class TlsConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset environment after each test
        putenv('ENV');
        putenv('TLS_VERIFY_INTERNAL');
        putenv('TLS_CA_PATH');
        unset($_ENV['ENV'], $_ENV['TLS_CA_PATH']);
    }

    public function testShouldVerifyInProduction(): void
    {
        $_ENV['ENV'] = 'production';
        putenv('TLS_VERIFY_INTERNAL');

        $this->assertTrue(TlsConfig::shouldVerify());
    }

    public function testShouldNotVerifyInDevelopment(): void
    {
        $_ENV['ENV'] = 'development';
        putenv('TLS_VERIFY_INTERNAL');

        $this->assertFalse(TlsConfig::shouldVerify());
    }

    public function testExplicitOverrideToDisable(): void
    {
        $_ENV['ENV'] = 'production';
        putenv('TLS_VERIFY_INTERNAL=false');

        $this->assertFalse(TlsConfig::shouldVerify());
    }

    public function testExplicitOverrideToEnable(): void
    {
        $_ENV['ENV'] = 'development';
        putenv('TLS_VERIFY_INTERNAL=true');

        $this->assertTrue(TlsConfig::shouldVerify());
    }

    public function testDefaultsToProductionWhenEnvNotSet(): void
    {
        // No ENV set - should default to production (secure by default)
        $this->assertTrue(TlsConfig::shouldVerify());
    }

    public function testGetCaPathReturnsDefault(): void
    {
        putenv('TLS_CA_PATH');
        unset($_ENV['TLS_CA_PATH']);

        $this->assertSame('/etc/ssl/certs/internal-ca.crt', TlsConfig::getCaPath());
    }

    public function testGetCaPathReturnsCustomPath(): void
    {
        $_ENV['TLS_CA_PATH'] = '/custom/ca.crt';

        $this->assertSame('/custom/ca.crt', TlsConfig::getCaPath());
    }

    public function testGetSslContextOptionsInDevelopment(): void
    {
        $_ENV['ENV'] = 'development';

        $options = TlsConfig::getSslContextOptions();

        $this->assertFalse($options['verify_peer']);
        $this->assertFalse($options['verify_peer_name']);
        $this->assertTrue($options['allow_self_signed']);
        $this->assertArrayNotHasKey('cafile', $options);
    }

    public function testGetSslContextOptionsInProduction(): void
    {
        $_ENV['ENV'] = 'production';

        $options = TlsConfig::getSslContextOptions();

        $this->assertTrue($options['verify_peer']);
        $this->assertTrue($options['verify_peer_name']);
        $this->assertFalse($options['allow_self_signed']);
        // cafile only included if file exists
    }
}
```

## Verification

```bash
# Run unit tests
make test-php
make test-node

# Verify TLS helper exists
test -f src/node/backend/utils/tls.ts && echo "Node helper exists"
test -f src/php/App/Infrastructure/TlsConfig.php && echo "PHP helper exists"

# Check no hardcoded false remains (except DevDashboard)
grep -r "rejectUnauthorized: false" src/node/ | grep -v test | wc -l
# Expected: 0

grep -r "'verify_peer'\s*=>\s*false" src/php/App/ | wc -l
# Expected: 0

# Verify CA is mounted (after Task 06)
make up
docker compose exec php ls -la /etc/ssl/certs/internal-ca.crt
docker compose exec node ls -la /etc/ssl/certs/internal-ca.crt
```

## Files to Create

1. `src/node/backend/utils/tls.ts` - Node.js TLS helper with CA support
2. `src/php/App/Infrastructure/TlsConfig.php` - PHP TLS helper with CA support
3. `tests/node/unit/utils/tls.test.ts` - Node.js tests
4. `tests/php/Unit/Infrastructure/TlsConfigTest.php` - PHP tests

## Files to Modify

1. `src/node/backend/services/CacheService.ts`
2. `src/node/backend/services/HealthCheckService.ts`
3. `src/php/App/Infrastructure/Cache/RedisCache.php`
4. `src/php/App/Infrastructure/Queue/RabbitMQQueue.php`
5. `src/php/App/Infrastructure/HealthCheck.php`
6. `src/php/App/Http/Controller/ExampleController.php`
7. `.env` - Add TLS_VERIFY_INTERNAL and TLS_CA_PATH documentation

## Notes

- **Dependency:** Requires Task 06 to be completed first (CA infrastructure)
- DevDashboard is development-only, can keep hardcoded `false`
- `server.ts` already uses `NODE_ENV` - no changes needed
- `db/pool.ts` already has configurable SSL verification - no changes needed
- Internal services (same Docker network) are protected by network isolation,
  but TLS verification adds defense-in-depth
- The `TLS_VERIFY_INTERNAL` override allows production deployments with external
  services that don't use the internal CA
- The `TLS_CA_PATH` override allows custom CA certificates (corporate PKI)
