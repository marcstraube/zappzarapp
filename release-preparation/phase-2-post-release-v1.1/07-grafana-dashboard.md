# Task 07: Create Custom Grafana Dashboard

## Priority

LOW - Post-Release v1.1

## Estimated Effort

3-4 hours

## Context

During the Performance & Operations review, it was noted that while Prometheus
and Grafana integration is documented, there's no custom dashboard for the
boilerplate. A pre-built dashboard would provide immediate visibility into
application health without additional setup.

## Current State

- MONITORING.md documents external monitoring setup
- References existing dashboard (ID 7587)
- No custom dashboard for zappzarapp services

## Target State

1. Create custom Grafana dashboard JSON
2. Include panels for all core services
3. Document import process
4. Optionally include dashboard in docker-compose for development

## Implementation Steps

### Step 1: Create Dashboard JSON

**Create `monitoring/grafana/dashboards/zappzarapp.json`:**

```json
{
  "annotations": {
    "list": []
  },
  "description": "zappzarapp Boilerplate Monitoring Dashboard",
  "editable": true,
  "fiscalYearStartMonth": 0,
  "graphTooltip": 0,
  "id": null,
  "links": [],
  "liveNow": false,
  "panels": [
    {
      "gridPos": { "h": 4, "w": 24, "x": 0, "y": 0 },
      "id": 1,
      "options": { "content": "# zappzarapp Dashboard\n\nMonitoring overview for your zappzarapp application.", "mode": "markdown" },
      "title": "",
      "type": "text"
    },
    {
      "gridPos": { "h": 8, "w": 6, "x": 0, "y": 4 },
      "id": 2,
      "options": { "colorMode": "value", "graphMode": "area", "justifyMode": "auto" },
      "targets": [{ "expr": "up{job=\"zappzarapp\"}", "legendFormat": "{{instance}}" }],
      "title": "Service Status",
      "type": "stat"
    },
    {
      "gridPos": { "h": 8, "w": 6, "x": 6, "y": 4 },
      "id": 3,
      "options": { "colorMode": "value", "graphMode": "none" },
      "targets": [{ "expr": "sum(rate(app_http_requests_total[5m]))", "legendFormat": "req/s" }],
      "title": "Request Rate",
      "type": "stat"
    },
    {
      "gridPos": { "h": 8, "w": 6, "x": 12, "y": 4 },
      "id": 4,
      "options": { "colorMode": "value", "graphMode": "none" },
      "targets": [{ "expr": "histogram_quantile(0.95, sum(rate(app_http_request_duration_seconds_bucket[5m])) by (le))", "legendFormat": "p95" }],
      "title": "Response Time (p95)",
      "type": "stat"
    },
    {
      "gridPos": { "h": 8, "w": 6, "x": 18, "y": 4 },
      "id": 5,
      "options": { "colorMode": "value", "graphMode": "none" },
      "targets": [{ "expr": "sum(rate(app_http_requests_total{status=~\"5..\"}[5m])) / sum(rate(app_http_requests_total[5m])) * 100", "legendFormat": "Error %" }],
      "title": "Error Rate",
      "type": "stat"
    },
    {
      "gridPos": { "h": 10, "w": 12, "x": 0, "y": 12 },
      "id": 6,
      "options": { "legend": { "displayMode": "list", "placement": "bottom" } },
      "targets": [
        { "expr": "sum(rate(app_http_requests_total[5m])) by (status)", "legendFormat": "{{status}}" }
      ],
      "title": "Requests by Status Code",
      "type": "timeseries"
    },
    {
      "gridPos": { "h": 10, "w": 12, "x": 12, "y": 12 },
      "id": 7,
      "options": { "legend": { "displayMode": "list", "placement": "bottom" } },
      "targets": [
        { "expr": "histogram_quantile(0.50, sum(rate(app_http_request_duration_seconds_bucket[5m])) by (le))", "legendFormat": "p50" },
        { "expr": "histogram_quantile(0.95, sum(rate(app_http_request_duration_seconds_bucket[5m])) by (le))", "legendFormat": "p95" },
        { "expr": "histogram_quantile(0.99, sum(rate(app_http_request_duration_seconds_bucket[5m])) by (le))", "legendFormat": "p99" }
      ],
      "title": "Response Time Distribution",
      "type": "timeseries"
    },
    {
      "collapsed": false,
      "gridPos": { "h": 1, "w": 24, "x": 0, "y": 22 },
      "id": 8,
      "title": "Container Metrics",
      "type": "row"
    },
    {
      "gridPos": { "h": 8, "w": 8, "x": 0, "y": 23 },
      "id": 9,
      "options": { "legend": { "displayMode": "list", "placement": "bottom" } },
      "targets": [
        { "expr": "container_memory_usage_bytes{name=~\"zappzarapp.*\"} / 1024 / 1024", "legendFormat": "{{name}}" }
      ],
      "title": "Container Memory (MB)",
      "type": "timeseries"
    },
    {
      "gridPos": { "h": 8, "w": 8, "x": 8, "y": 23 },
      "id": 10,
      "options": { "legend": { "displayMode": "list", "placement": "bottom" } },
      "targets": [
        { "expr": "rate(container_cpu_usage_seconds_total{name=~\"zappzarapp.*\"}[5m]) * 100", "legendFormat": "{{name}}" }
      ],
      "title": "Container CPU (%)",
      "type": "timeseries"
    },
    {
      "gridPos": { "h": 8, "w": 8, "x": 16, "y": 23 },
      "id": 11,
      "options": { "legend": { "displayMode": "list", "placement": "bottom" } },
      "targets": [
        { "expr": "rate(container_network_receive_bytes_total{name=~\"zappzarapp.*\"}[5m]) / 1024", "legendFormat": "{{name}} rx" },
        { "expr": "rate(container_network_transmit_bytes_total{name=~\"zappzarapp.*\"}[5m]) / 1024", "legendFormat": "{{name}} tx" }
      ],
      "title": "Network I/O (KB/s)",
      "type": "timeseries"
    },
    {
      "collapsed": false,
      "gridPos": { "h": 1, "w": 24, "x": 0, "y": 31 },
      "id": 12,
      "title": "Database Metrics",
      "type": "row"
    },
    {
      "gridPos": { "h": 8, "w": 8, "x": 0, "y": 32 },
      "id": 13,
      "options": { "colorMode": "value" },
      "targets": [{ "expr": "pg_up", "legendFormat": "PostgreSQL" }],
      "title": "PostgreSQL Status",
      "type": "stat"
    },
    {
      "gridPos": { "h": 8, "w": 8, "x": 8, "y": 32 },
      "id": 14,
      "targets": [{ "expr": "pg_stat_activity_count", "legendFormat": "Connections" }],
      "title": "PostgreSQL Connections",
      "type": "timeseries"
    },
    {
      "gridPos": { "h": 8, "w": 8, "x": 16, "y": 32 },
      "id": 15,
      "targets": [{ "expr": "redis_connected_clients", "legendFormat": "Clients" }],
      "title": "Redis Clients",
      "type": "timeseries"
    }
  ],
  "refresh": "10s",
  "schemaVersion": 38,
  "style": "dark",
  "tags": ["zappzarapp", "docker", "web"],
  "templating": { "list": [] },
  "time": { "from": "now-1h", "to": "now" },
  "timepicker": {},
  "timezone": "browser",
  "title": "zappzarapp Overview",
  "uid": "zappzarapp-overview",
  "version": 1,
  "weekStart": ""
}
```

### Step 2: Create Provisioning Configuration

**Create `monitoring/grafana/provisioning/dashboards/default.yml`:**

```yaml
apiVersion: 1

providers:
  - name: 'default'
    orgId: 1
    folder: 'zappzarapp'
    type: file
    disableDeletion: false
    updateIntervalSeconds: 10
    options:
      path: /var/lib/grafana/dashboards
```

### Step 3: Add Development Monitoring Stack (Optional)

**Create `compose.monitoring.yaml`:**

```yaml
# Development monitoring stack
# Usage: docker compose -f compose.yaml -f compose.monitoring.yaml up

services:
  prometheus:
    image: prom/prometheus:v2.50.0
    volumes:
      - ./monitoring/prometheus/prometheus.yml:/etc/prometheus/prometheus.yml:ro
      - prometheus-data:/prometheus
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.path=/prometheus'
      - '--web.enable-lifecycle'
    ports:
      - '9090:9090'
    networks:
      - backend

  grafana:
    image: grafana/grafana:10.3.0
    volumes:
      - ./monitoring/grafana/provisioning:/etc/grafana/provisioning:ro
      - ./monitoring/grafana/dashboards:/var/lib/grafana/dashboards:ro
      - grafana-data:/var/lib/grafana
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin
      - GF_USERS_ALLOW_SIGN_UP=false
    ports:
      - '3030:3000'
    networks:
      - backend

  cadvisor:
    image: gcr.io/cadvisor/cadvisor:v0.49.1
    volumes:
      - /:/rootfs:ro
      - /var/run:/var/run:ro
      - /sys:/sys:ro
      - /var/lib/docker/:/var/lib/docker:ro
    ports:
      - '8081:8080'
    networks:
      - backend

volumes:
  prometheus-data:
  grafana-data:
```

**Create `monitoring/prometheus/prometheus.yml`:**

```yaml
global:
  scrape_interval: 15s
  evaluation_interval: 15s

scrape_configs:
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']

  - job_name: 'cadvisor'
    static_configs:
      - targets: ['cadvisor:8080']

  - job_name: 'zappzarapp'
    static_configs:
      - targets: ['nginx:8080']
    metrics_path: '/metrics'
```

### Step 4: Add Documentation

**Update `.zappzarapp/docs/infrastructure/MONITORING.md`:**

```markdown
## Pre-Built Grafana Dashboard

A custom Grafana dashboard is included for zappzarapp monitoring.

### Import Dashboard

1. Open Grafana (http://localhost:3030 if using compose.monitoring.yaml)
2. Go to Dashboards → Import
3. Upload `monitoring/grafana/dashboards/zappzarapp.json`
4. Select your Prometheus data source
5. Click Import

### Dashboard Panels

| Panel | Metrics | Description |
|-------|---------|-------------|
| Service Status | up | Health of monitored endpoints |
| Request Rate | app_http_requests_total | Requests per second |
| Response Time | app_http_request_duration_seconds | p95 latency |
| Error Rate | app_http_requests_total (5xx) | Percentage of errors |
| Container Memory | container_memory_usage_bytes | Memory per container |
| Container CPU | container_cpu_usage_seconds_total | CPU per container |
| PostgreSQL | pg_* | Database metrics |
| Redis | redis_* | Cache metrics |

### Development Monitoring Stack

For local development with full monitoring:

```bash
docker compose -f compose.yaml -f compose.monitoring.yaml up
```

Access:
- Grafana: http://localhost:3030 (admin/admin)
- Prometheus: http://localhost:9090
- cAdvisor: http://localhost:8081
```

### Step 5: Add Makefile Targets

```makefile
##@ Monitoring

.PHONY: monitoring-up monitoring-down

monitoring-up: ## Start monitoring stack (Prometheus, Grafana, cAdvisor)
	docker compose -f compose.yaml -f compose.monitoring.yaml up -d

monitoring-down: ## Stop monitoring stack
	docker compose -f compose.yaml -f compose.monitoring.yaml down
```

## Verification

1. **Dashboard JSON is valid:**

   ```bash
   jq . monitoring/grafana/dashboards/zappzarapp.json > /dev/null
   ```

2. **Monitoring stack starts:**

   ```bash
   make monitoring-up
   docker compose -f compose.yaml -f compose.monitoring.yaml ps
   ```

3. **Grafana loads dashboard:**
   - Open http://localhost:3030
   - Login with admin/admin
   - Navigate to Dashboards
   - Verify zappzarapp dashboard appears

## Files to Create

1. `monitoring/grafana/dashboards/zappzarapp.json` - Dashboard definition
2. `monitoring/grafana/provisioning/dashboards/default.yml` - Provisioning config
3. `monitoring/prometheus/prometheus.yml` - Prometheus config
4. `compose.monitoring.yaml` - Monitoring stack compose file
5. `Makefile` - Add monitoring targets
6. `.zappzarapp/docs/infrastructure/MONITORING.md` - Update documentation

## Notes

- Dashboard requires Prometheus metrics endpoint (Task 03)
- cAdvisor requires Docker socket access
- Grafana default credentials: admin/admin
- Dashboard UID should be unique if deploying multiple instances
