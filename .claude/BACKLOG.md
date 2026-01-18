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

## Medium Priority

(No items yet)

---

## Low Priority

(No items yet)

---

## Completed

(Items move here when done)
