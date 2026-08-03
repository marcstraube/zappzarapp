# External Monitoring Integration

This guide explains how to integrate the zappzarapp application with external
monitoring tools. Monitoring should run on separate infrastructure for
reliability.

## Built-in Health Endpoints

The application provides health endpoints that external monitoring tools can
poll.

### PHP Health Endpoint

**URL:** `GET /health`

**Response:**

```json
{
  "timestamp": "2024-01-15T10:30:00+00:00",
  "environment": "production",
  "overall_status": "ok",
  "services": {
    "php-fpm": {
      "status": "ok",
      "version": "8.5.9",
      "enabled": true
    },
    "node-backend": {
      "status": "ok",
      "version": "v24.12.0",
      "uptime": 3600.5,
      "enabled": true
    },
    "redis": {
      "status": "ok",
      "version": "7.2.4",
      "enabled": true,
      "tls": true
    },
    "database": {
      "status": "ok",
      "type": "postgres",
      "version": "PostgreSQL 16.2",
      "enabled": true
    }
  }
}
```

**Status codes:**

- `200` - All services healthy
- `503` - One or more services degraded

**Implementation:** `src/php/App/Infrastructure/HealthCheck.php`

### Node.js Health Endpoint

**URL:** `GET http://node:3000/health` (internal) or via Nginx proxy

**Response:**

```json
{
  "status": "ok",
  "service": "node-backend",
  "timestamp": "2024-01-15T10:30:00.000Z",
  "uptime": 3600.5,
  "node_version": "v24.12.0",
  "environment": "production"
}
```

**Implementation:** `src/node/backend/app.ts`

## Uptime Monitoring

### Uptime Kuma (Self-hosted)

Free, self-hosted uptime monitoring with notifications.

**Setup:**

```yaml
# Add to your monitoring server's docker-compose.yml
services:
  uptime-kuma:
    image: louislam/uptime-kuma:1
    volumes:
      - uptime-kuma-data:/app/data
    ports:
      - '3001:3001'
```

**Configuration:**

1. Add HTTP(s) monitor: `https://your-app.example.com/health`
2. Set check interval: 60 seconds
3. Configure notifications (Slack, Discord, Email, etc.)
4. Set expected status code: 200
5. Optional: Check for JSON response `"overall_status": "ok"`

### Better Uptime / Pingdom / StatusCake

Commercial uptime monitoring services.

**Recommended checks:**

| Check           | URL                                   | Interval | Alert                 |
| --------------- | ------------------------------------- | -------- | --------------------- |
| HTTP            | `https://your-app.example.com/health` | 1 min    | Immediate             |
| SSL Certificate | `https://your-app.example.com`        | 1 day    | 14 days before expiry |
| Response time   | `https://your-app.example.com/`       | 5 min    | > 2 seconds           |

## Prometheus Integration

### Prometheus Configuration

Add to your Prometheus server's `prometheus.yml`:

```yaml
scrape_configs:
  - job_name: 'zappzarapp'
    metrics_path: '/health'
    static_configs:
      - targets: ['your-app.example.com:8080']
    relabel_configs:
      - source_labels: [__address__]
        target_label: instance
        replacement: 'zappzarapp-production'
    # Parse JSON health response
    metric_relabel_configs:
      - source_labels: [__name__]
        regex: 'up'
        action: keep
```

### Blackbox Exporter (Recommended)

For HTTP endpoint monitoring:

```yaml
# blackbox.yml
modules:
  http_2xx:
    prober: http
    timeout: 5s
    http:
      valid_http_versions: ['HTTP/1.1', 'HTTP/2.0']
      valid_status_codes: [200]
      method: GET
      preferred_ip_protocol: 'ip4'

  http_health_json:
    prober: http
    timeout: 5s
    http:
      valid_http_versions: ['HTTP/1.1', 'HTTP/2.0']
      valid_status_codes: [200]
      method: GET
      fail_if_body_not_matches_regexp:
        - '"overall_status":\s*"ok"'
```

**Prometheus config:**

```yaml
scrape_configs:
  - job_name: 'blackbox-http'
    metrics_path: /probe
    params:
      module: [http_health_json]
    static_configs:
      - targets:
          - https://your-app.example.com/health
    relabel_configs:
      - source_labels: [__address__]
        target_label: __param_target
      - source_labels: [__param_target]
        target_label: instance
      - target_label: __address__
        replacement: blackbox-exporter:9115
```

### Grafana Dashboard

Import dashboard ID `7587` (Blackbox Exporter) or create custom dashboard.

**Key panels:**

- HTTP response time
- SSL certificate expiry
- Service availability (up/down)
- Response status codes

## Application Performance Monitoring (APM)

### New Relic

**PHP Agent:**

```dockerfile
# Add to docker/php/Dockerfile
RUN curl -L https://download.newrelic.com/php_agent/release/newrelic-php5-*-linux.tar.gz | tar xz \
    && cd newrelic-php5-* \
    && NR_INSTALL_SILENT=true ./newrelic-install install
```

**Environment variables:**

```bash
NEW_RELIC_LICENSE_KEY=your_license_key
NEW_RELIC_APP_NAME="zappzarapp Production"
```

### Datadog

**PHP & Node agents:**

```yaml
# docker-compose.monitoring.yml
services:
  datadog-agent:
    image: datadog/agent:latest
    environment:
      - DD_API_KEY=your_api_key
      - DD_APM_ENABLED=true
      - DD_LOGS_ENABLED=true
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - /proc/:/host/proc/:ro
      - /sys/fs/cgroup/:/host/sys/fs/cgroup:ro
```

### Sentry (Error Tracking)

**PHP SDK:**

```bash
docker compose exec php composer require sentry/sentry
```

```php
// config/sentry.php
\Sentry\init([
    'dsn' => getenv('SENTRY_DSN'),
    'environment' => getenv('ZAPPZARAPP_ENV'),
    'traces_sample_rate' => 0.1,
]);
```

**Node.js SDK:**

```bash
docker compose exec node pnpm add @sentry/node
```

```typescript
// src/node/backend/app.ts
import * as Sentry from '@sentry/node';

Sentry.init({
  dsn: process.env.SENTRY_DSN,
  environment: process.env.NODE_ENV,
  tracesSampleRate: 0.1,
});
```

## Log Aggregation

### Loki + Grafana

Collect and query logs from Docker containers.

**Promtail configuration:**

```yaml
# promtail-config.yml
server:
  http_listen_port: 9080

positions:
  filename: /tmp/positions.yaml

clients:
  - url: http://loki:3100/loki/api/v1/push

scrape_configs:
  - job_name: docker
    docker_sd_configs:
      - host: unix:///var/run/docker.sock
        refresh_interval: 5s
    relabel_configs:
      - source_labels: ['__meta_docker_container_name']
        regex: '/(.*)'
        target_label: 'container'
      - source_labels:
          ['__meta_docker_container_label_com_docker_compose_service']
        target_label: 'service'
```

### ELK Stack (Elasticsearch, Logstash, Kibana)

**Filebeat configuration:**

```yaml
# filebeat.yml
filebeat.autodiscover:
  providers:
    - type: docker
      hints.enabled: true

output.elasticsearch:
  hosts: ['elasticsearch:9200']

setup.kibana:
  host: 'kibana:5601'
```

## Infrastructure Monitoring

### Container Metrics

**cAdvisor + Prometheus:**

```yaml
services:
  cadvisor:
    image: gcr.io/cadvisor/cadvisor:latest
    volumes:
      - /:/rootfs:ro
      - /var/run:/var/run:ro
      - /sys:/sys:ro
      - /var/lib/docker/:/var/lib/docker:ro
    ports:
      - '8081:8080'
```

**Prometheus scrape config:**

```yaml
scrape_configs:
  - job_name: 'cadvisor'
    static_configs:
      - targets: ['cadvisor:8080']
```

### Database Monitoring

**PostgreSQL Exporter:**

```yaml
services:
  postgres-exporter:
    image: prometheuscommunity/postgres-exporter
    environment:
      DATA_SOURCE_NAME: 'postgresql://app:password@your-db-host:5432/app?sslmode=disable'
```

**MySQL/MariaDB Exporter:**

```yaml
services:
  mysql-exporter:
    image: prom/mysqld-exporter
    environment:
      DATA_SOURCE_NAME: 'app:password@(your-db-host:3306)/app'
```

### Redis Monitoring

**Redis Exporter:**

```yaml
services:
  redis-exporter:
    image: oliver006/redis_exporter
    environment:
      REDIS_ADDR: 'redis://your-redis-host:6379'
```

## Alerting Examples

### Prometheus Alertmanager Rules

```yaml
# alerts.yml
groups:
  - name: zappzarapp
    rules:
      - alert: ServiceDown
        expr: probe_success{job="blackbox-http"} == 0
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: 'Service {{ $labels.instance }} is down'

      - alert: HighResponseTime
        expr: probe_http_duration_seconds{job="blackbox-http"} > 2
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: 'High response time on {{ $labels.instance }}'

      - alert: SSLCertExpiringSoon
        expr: probe_ssl_earliest_cert_expiry - time() < 86400 * 14
        labels:
          severity: warning
        annotations:
          summary: 'SSL certificate expires in less than 14 days'

      - alert: HighMemoryUsage
        expr:
          container_memory_usage_bytes / container_spec_memory_limit_bytes > 0.9
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: 'Container {{ $labels.name }} memory usage > 90%'
```

## Notification Channels

Configure alerts to your preferred channels:

| Channel         | Tool                      | Setup               |
| --------------- | ------------------------- | ------------------- |
| Slack           | Alertmanager, Uptime Kuma | Webhook URL         |
| Discord         | Alertmanager, Uptime Kuma | Webhook URL         |
| Email           | Alertmanager              | SMTP config         |
| PagerDuty       | Alertmanager              | Integration key     |
| Telegram        | Uptime Kuma               | Bot token + Chat ID |
| Microsoft Teams | Alertmanager              | Webhook URL         |

## Quick Start Recommendations

### Minimal Setup (Free)

1. **Uptime Kuma** (self-hosted) - Uptime monitoring
2. **Health endpoint** (`/health`) - Already built-in
3. **Docker logs** - `make logs`

### Small Team

1. **Uptime Kuma** or **Better Uptime** (free tier)
2. **Sentry** (free tier) - Error tracking
3. **Grafana Cloud** (free tier) - Metrics visualization

### Production

1. **Prometheus + Grafana** - Metrics
2. **Loki** - Log aggregation
3. **Blackbox Exporter** - Endpoint monitoring
4. **Alertmanager** - Alert routing
5. **Sentry** or **New Relic** - APM

## Adding Custom Metrics

If you need a `/metrics` endpoint for Prometheus:

### PHP (Prometheus Client)

```bash
docker compose exec php composer require promphp/prometheus_client_php
```

```php
// src/php/App/Infrastructure/Metrics.php
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

$registry = new CollectorRegistry(new InMemory());
$counter = $registry->getOrRegisterCounter('app', 'requests_total', 'Total requests');
$counter->inc();

// Render for Prometheus
$renderer = new RenderTextFormat();
echo $renderer->render($registry->getMetricFamilySamples());
```

### Node.js (prom-client)

```bash
docker compose exec node pnpm add prom-client
```

```typescript
// src/node/backend/metrics.ts
import { Registry, collectDefaultMetrics } from 'prom-client';

const register = new Registry();
collectDefaultMetrics({ register });

app.get('/metrics', async (req, res) => {
  res.set('Content-Type', register.contentType);
  res.end(await register.metrics());
});
```

## Related Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) - System architecture
- [PERFORMANCE.md](PERFORMANCE.md) - Performance tuning
- [TROUBLESHOOTING.md](../TROUBLESHOOTING.md) - Common issues
- [ACCESS-LOG-MONITORING.md](../security/ACCESS-LOG-MONITORING.md) - Access log
  analysis
