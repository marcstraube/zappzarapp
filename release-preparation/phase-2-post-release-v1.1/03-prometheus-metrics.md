# Task 03: Add Optional Prometheus Metrics Endpoint

## Priority

MEDIUM - Post-Release v1.1

## Estimated Effort

3-4 hours

## Context

During the Performance & Operations review, it was identified that while health
endpoints exist, there's no native Prometheus `/metrics` endpoint. The
MONITORING.md documentation explains how to add one, but having it as a
built-in option would improve observability out of the box.

## Current State

- Health endpoints exist (`/health`)
- No `/metrics` endpoint for Prometheus
- MONITORING.md documents manual setup
- No built-in metric collection

## Target State

1. Add optional `/metrics` endpoint to both PHP and Node.js
2. Enable via environment variable (disabled by default)
3. Include useful default metrics
4. Document configuration

## Implementation Steps

### Step 1: PHP Metrics Endpoint

Install prometheus client and create metrics endpoint:

**Add to composer.json:**

```json
{
    "require": {
        "promphp/prometheus_client_php": "^2.10"
    }
}
```

**Create `src/php/App/Infrastructure/Metrics/PrometheusMetrics.php`:**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;

/**
 * Prometheus Metrics Collection
 *
 * Provides /metrics endpoint for Prometheus scraping.
 * Enable via PROMETHEUS_ENABLED=true environment variable.
 */
final class PrometheusMetrics
{
    private static ?CollectorRegistry $registry = null;

    public static function isEnabled(): bool
    {
        return getenv('PROMETHEUS_ENABLED') === 'true';
    }

    public static function getRegistry(): CollectorRegistry
    {
        if (self::$registry === null) {
            self::$registry = new CollectorRegistry(new InMemory());
            self::registerDefaultMetrics();
        }

        return self::$registry;
    }

    public static function render(): string
    {
        $renderer = new RenderTextFormat();
        return $renderer->render(self::getRegistry()->getMetricFamilySamples());
    }

    public static function incrementRequestCounter(string $method, string $path, int $status): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $counter = self::getRegistry()->getOrRegisterCounter(
            'app',
            'http_requests_total',
            'Total HTTP requests',
            ['method', 'path', 'status']
        );
        $counter->inc([$method, $path, (string) $status]);
    }

    public static function observeRequestDuration(string $method, string $path, float $duration): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $histogram = self::getRegistry()->getOrRegisterHistogram(
            'app',
            'http_request_duration_seconds',
            'HTTP request duration in seconds',
            ['method', 'path'],
            [0.01, 0.05, 0.1, 0.5, 1.0, 5.0]
        );
        $histogram->observe($duration, [$method, $path]);
    }

    private static function registerDefaultMetrics(): void
    {
        // PHP info gauge
        $info = self::$registry->getOrRegisterGauge(
            'php',
            'info',
            'PHP runtime information',
            ['version']
        );
        $info->set(1, [PHP_VERSION]);
    }
}
```

**Create `/metrics` route (example for vanilla PHP):**

```php
// public/metrics.php
<?php

declare(strict_types=1);

use App\Infrastructure\Metrics\PrometheusMetrics;

require_once __DIR__ . '/../vendor/autoload.php';

if (!PrometheusMetrics::isEnabled()) {
    http_response_code(404);
    echo 'Metrics disabled. Set PROMETHEUS_ENABLED=true to enable.';
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo PrometheusMetrics::render();
```

### Step 2: Node.js Metrics Endpoint

**Add to package.json dependencies:**

```json
{
    "dependencies": {
        "prom-client": "^15.1.0"
    }
}
```

**Create `src/node/backend/services/MetricsService.ts`:**

```typescript
/**
 * Prometheus Metrics Service
 *
 * Provides /metrics endpoint for Prometheus scraping.
 * Enable via PROMETHEUS_ENABLED=true environment variable.
 */

import { Registry, collectDefaultMetrics, Counter, Histogram } from 'prom-client';

export class MetricsService {
  private static registry: Registry | null = null;
  private static requestCounter: Counter | null = null;
  private static requestDuration: Histogram | null = null;

  static isEnabled(): boolean {
    return process.env.PROMETHEUS_ENABLED === 'true';
  }

  static getRegistry(): Registry {
    if (this.registry === null) {
      this.registry = new Registry();
      this.registerDefaultMetrics();
    }
    return this.registry;
  }

  static async getMetrics(): Promise<string> {
    return this.getRegistry().metrics();
  }

  static getContentType(): string {
    return this.getRegistry().contentType;
  }

  static incrementRequestCounter(method: string, path: string, status: number): void {
    if (!this.isEnabled()) return;

    if (this.requestCounter === null) {
      this.requestCounter = new Counter({
        name: 'app_http_requests_total',
        help: 'Total HTTP requests',
        labelNames: ['method', 'path', 'status'],
        registers: [this.getRegistry()],
      });
    }

    this.requestCounter.inc({ method, path, status: String(status) });
  }

  static observeRequestDuration(method: string, path: string, duration: number): void {
    if (!this.isEnabled()) return;

    if (this.requestDuration === null) {
      this.requestDuration = new Histogram({
        name: 'app_http_request_duration_seconds',
        help: 'HTTP request duration in seconds',
        labelNames: ['method', 'path'],
        buckets: [0.01, 0.05, 0.1, 0.5, 1.0, 5.0],
        registers: [this.getRegistry()],
      });
    }

    this.requestDuration.observe({ method, path }, duration);
  }

  private static registerDefaultMetrics(): void {
    collectDefaultMetrics({
      register: this.registry!,
      prefix: 'app_',
    });
  }
}
```

**Add route in `app.ts`:**

```typescript
import { MetricsService } from './services/MetricsService';

// Metrics endpoint
app.get('/metrics', async (req, res) => {
  if (!MetricsService.isEnabled()) {
    return res.status(404).send('Metrics disabled');
  }

  res.set('Content-Type', MetricsService.getContentType());
  res.send(await MetricsService.getMetrics());
});
```

### Step 3: Environment Configuration

Add to `.env`:

```bash
# Prometheus Metrics
# Set to 'true' to enable /metrics endpoint
PROMETHEUS_ENABLED=false
```

### Step 4: Nginx Configuration

Add metrics location to nginx config:

```nginx
# Prometheus metrics (if enabled)
location = /metrics {
    # Restrict to internal/monitoring IPs
    # allow 10.0.0.0/8;
    # deny all;

    try_files $uri @php;
}
```

### Step 5: Documentation

Create `.zappzarapp/docs/infrastructure/METRICS.md`:

```markdown
# Prometheus Metrics

The boilerplate includes optional Prometheus metrics endpoints.

## Enabling Metrics

Set in `.env`:

```bash
PROMETHEUS_ENABLED=true
```

## Endpoints

| Service | Endpoint | Port |
|---------|----------|------|
| PHP | `/metrics` | 8080/8443 |
| Node.js | `/metrics` | 3000 |

## Available Metrics

### Default Metrics

- `app_http_requests_total` - Total HTTP requests (method, path, status)
- `app_http_request_duration_seconds` - Request duration histogram
- `process_*` - Node.js process metrics
- `php_info` - PHP version information

### Prometheus Configuration

```yaml
scrape_configs:
  - job_name: 'zappzarapp-php'
    static_configs:
      - targets: ['your-app:8080']
    metrics_path: '/metrics'

  - job_name: 'zappzarapp-node'
    static_configs:
      - targets: ['your-app:3000']
    metrics_path: '/metrics'
```

## Security Considerations

Metrics endpoints should be restricted in production:

1. Use firewall rules to allow only monitoring infrastructure
2. Use nginx `allow`/`deny` directives
3. Consider authentication if exposed publicly
```

## Verification

1. **PHP metrics work:**

   ```bash
   PROMETHEUS_ENABLED=true docker compose exec php curl localhost:8080/metrics
   ```

2. **Node.js metrics work:**

   ```bash
   PROMETHEUS_ENABLED=true docker compose exec node curl localhost:3000/metrics
   ```

3. **Disabled by default:**

   ```bash
   curl localhost:8080/metrics
   # Should return 404
   ```

## Files to Create/Modify

1. `composer.json` - Add prometheus client
2. `src/php/App/Infrastructure/Metrics/PrometheusMetrics.php` - New file
3. `public/metrics.php` - New file
4. `package.json` - Add prom-client
5. `src/node/backend/services/MetricsService.ts` - New file
6. `src/node/backend/app.ts` - Add route
7. `.env` - Add PROMETHEUS_ENABLED
8. `.zappzarapp/docs/infrastructure/METRICS.md` - New documentation

## Notes

- Metrics are disabled by default (no performance impact)
- InMemory storage for PHP (resets per-request)
- Consider Redis storage for PHP in high-traffic scenarios
- Metrics endpoint should be protected in production
