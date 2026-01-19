# Project Backlog

Future tasks and improvements to be implemented.

---

## High Priority

### Internal TLS Migration

**Status:** Planned
**Created:** 2026-01-17
**Context:** Identified during GOSS test implementation

**Goal:** Migrate all internal service-to-service communication to TLS/mTLS for zero-trust networking.

**Current State:**

| Connection | Current | Target |
|------------|---------|--------|
| nginx → node-backend | HTTP | HTTPS (mTLS) |
| nginx → node-frontend | HTTP | HTTPS (mTLS) |
| nginx → php | Unix Socket | Unix Socket (no change) |
| php → meilisearch | HTTP | HTTPS |
| php → elasticsearch | HTTP | HTTPS |
| php → mercure | HTTP | HTTPS |
| node → meilisearch | HTTP | HTTPS |
| node → elasticsearch | HTTP | HTTPS |

**Already TLS-enabled:**
- nginx external (HTTPS on 8443)
- redis (TLS on 6379)
- postgres (SSL enabled)
- mariadb (SSL required)
- minio (HTTPS)
- rabbitmq (TLS)

**Benefits:**
- Zero-trust networking for distributed deployments (Kubernetes, multi-server)
- Defense in depth - even internal traffic is encrypted
- Mutual TLS (mTLS) provides strong service authentication
- Required for service mesh integration (Istio, Linkerd)

**Implementation Steps:**
1. Generate internal CA for mTLS certificates
2. Configure nginx upstream blocks for HTTPS
3. Enable TLS on node-backend Express server
4. Enable TLS on node-frontend framework server
5. Configure meilisearch with TLS
6. Configure elasticsearch with TLS (xpack.security)
7. Configure mercure with TLS
8. Update GOSS tests for internal TLS verification
9. Update health check endpoints to use HTTPS internally

**Files to Modify:**
- `docker/nginx/snippets/node-backend-proxy.conf`
- `docker/nginx/snippets/node-frontend-proxy.conf` (new)
- `src/node/backend/server.ts`
- `docker/node/Dockerfile`
- `docker/meilisearch/Dockerfile`
- `docker/elasticsearch/Dockerfile`
- `docker/mercure/Dockerfile`
- `compose.yaml` (environment variables)
- `compose.override.yaml` (development volumes for certs)

---

### Service Integration Examples

**Status:** Planned
**Created:** 2026-01-19
**Context:** Boilerplate should demonstrate best-practice integration patterns

**Goal:** Add production-ready example code for integrating all optional services in both PHP and Node.js backends.

**Requirements:**
- Full test coverage (unit + integration tests)
- Security by design (input validation, prepared statements, secure defaults)
- Consistent error handling patterns
- Documentation with usage examples

**Services to integrate:**

| Service | PHP | Node.js |
|---------|-----|---------|
| Redis | Session/Cache example | Session/Cache example |
| RabbitMQ | Producer/Consumer example | Producer/Consumer example |
| PostgreSQL | Repository pattern example | Repository pattern example |
| Meilisearch | Search indexing example | Search indexing example |
| Elasticsearch | Search/Analytics example | Search/Analytics example |
| MinIO/S3 | File upload example | File upload example |

**Implementation per service:**
1. Service class with dependency injection
2. Unit tests with mocks
3. Integration tests (optional, requires running service)
4. Usage documentation in code comments
5. Error handling with proper logging

**Files to create (PHP):**
- `src/php/App/Services/RedisService.php`
- `src/php/App/Services/QueueService.php` (RabbitMQ)
- `tests/php/App/Unit/Services/*Test.php`
- `tests/php/App/Feature/Services/*Test.php`

**Files to create (Node.js):**
- `src/node/backend/services/RedisService.ts`
- `src/node/backend/services/QueueService.ts`
- `tests/node/backend/unit/services/*.test.ts`
- `tests/node/backend/integration/services/*.test.ts`

---

### Unified Health Endpoints

**Status:** Planned
**Created:** 2026-01-19
**Context:** Current health endpoints have inconsistent naming and response formats

**Goal:** Unified health check system with consistent naming, response format, and full service coverage for both PHP and Node.js backends.

**Current State (inconsistent):**

| Endpoint | Service | Type |
|----------|---------|------|
| `/api/health` | PHP | Simple liveness |
| `/status` | PHP | Full service check |
| `/health` | Node | Simple liveness |

**Target State (unified):**

| Endpoint | Purpose | Response |
|----------|---------|----------|
| `/health` | Liveness probe (K8s) | `{"status":"ok"}` |
| `/health/ready` | Readiness probe (K8s) | Full service check |
| `/health/live` | Alias for `/health` | `{"status":"ok"}` |

**Unified Response Format:**
```json
{
  "status": "ok|degraded|unhealthy",
  "timestamp": "2026-01-19T12:00:00Z",
  "service": "php-backend|node-backend",
  "version": "1.0.0",
  "environment": "development|production",
  "uptime": 12345,
  "checks": {
    "database": { "status": "ok", "latency_ms": 5 },
    "redis": { "status": "ok", "latency_ms": 2 },
    "rabbitmq": { "status": "ok" }
  }
}
```

**Implementation Steps:**
1. Create `HealthCheckService.ts` for Node.js (mirrors PHP `HealthCheck.php`)
2. Implement unified endpoint naming in both backends
3. Update nginx configuration for health routes
4. Update Docker healthchecks in Dockerfiles
5. Update Kubernetes deployment manifests
6. Update GOSS test specifications
7. Update Welcome Page to use new endpoints
8. Update/create tests for new endpoints
9. Deprecate old endpoints with redirect (optional)

**Files to modify:**
- `src/php/App/Infrastructure/HealthCheck.php`
- `src/php/App/Http/Controller/StatusController.php`
- `public/index.php` (routes)
- `src/node/backend/app.ts`
- `docker/*/Dockerfile` (HEALTHCHECK commands)
- `kubernetes/templates/*/deployment.yaml`
- `tests/goss/services/*.yaml`
- `templates/app/welcome.php`
- `tests/php/App/*/HealthCheck*Test.php`
- `tests/node/backend/*/health*.test.ts`

---

## Medium Priority

### Code Quality Improvements

**Status:** Completed
**Created:** 2026-01-18
**Completed:** 2026-01-18

- [x] Move hooks from `settings.local.json` to `settings.json` (committable)
- [x] Fix PHPUnit warnings (node -> node-backend hostname)
- [x] Harden PHP error handling (safeSocketOpen/safeRedisConnect wrappers)
- [x] Review PHP code with suppressed PHPMD warnings (ErrorControlOperator eliminated)

---

## Low Priority

(No items yet)

---

## Completed

(Items move here when done)
