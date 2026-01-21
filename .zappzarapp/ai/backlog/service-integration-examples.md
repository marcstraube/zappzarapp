# Service Integration Examples

**Status:** Complete **Size:** Large **Scope:** feature **Created:** 2026-01-19
**Completed:** 2026-01-21 **Planning:** Not required

## Context

Boilerplate should demonstrate best-practice integration patterns for all
optional services.

## Goal

Production-ready example code for integrating all optional services in both PHP
and Node.js backends.

## Requirements

- Full test coverage (unit + integration tests)
- Security by design (input validation, prepared statements, secure defaults)
- Consistent error handling patterns
- Documentation with usage examples

## Services Integrated

| Service       | PHP                        | Node.js                    | Status      |
| ------------- | -------------------------- | -------------------------- | ----------- |
| Redis         | Session/Cache example      | Session/Cache example      | ✅ Complete |
| RabbitMQ      | Producer/Consumer example  | Producer/Consumer example  | ✅ Complete |
| PostgreSQL    | Repository pattern example | Repository pattern example | ✅ Complete |
| Meilisearch   | Search indexing example    | Search indexing example    | ✅ Complete |
| Elasticsearch | Search/Analytics example   | Search/Analytics example   | ✅ Complete |
| SeaweedFS/S3  | File upload example        | File upload example        | ✅ Complete |

## Completion Notes

**2026-01-20:** Redis Cache + Session services for PHP and Node.js with
Interface+Implementation pattern, full unit tests, TLS support.

**2026-01-20:** RabbitMQ Queue services for PHP and Node.js with
QueueInterface + RabbitMQQueue/QueueService implementation, full unit tests, TLS
support, Docker secrets integration.

**2026-01-21:** PostgreSQL/MariaDB Repository pattern for PHP and Node.js with
AbstractPdoRepository/AbstractRepository base classes, UserRepository example,
DatabaseConfigInterface, migrations for users table, full unit tests.

**2026-01-21:** Meilisearch Search services for PHP and Node.js with
SearchInterface/SearchServiceInterface + MeilisearchSearch/SearchService
implementation, health check integration, full unit tests, TLS support.

**2026-01-21:** Elasticsearch Search/Analytics services for PHP and Node.js with
ElasticsearchInterface + ElasticsearchClient for PHP, ElasticsearchService for
Node.js. Native cURL/fetch implementations without external dependencies. Full
unit tests covering configuration, TLS detection, and error handling.

**2026-01-21:** SeaweedFS/S3 Storage services for PHP and Node.js with
StorageInterface + S3Storage for PHP, StorageService for Node.js. Full AWS
Signature Version 4 implementation for S3-compatible APIs. Supports upload,
download, copy, move, list, metadata, pre-signed URLs. Full unit tests.

## Files Created

**PHP:**

- `src/php/App/Infrastructure/Cache/` (Redis)
- `src/php/App/Infrastructure/Queue/` (RabbitMQ)
- `src/php/App/Infrastructure/Database/` (Repository pattern)
- `src/php/App/Infrastructure/Search/` (Meilisearch)
- `src/php/App/Infrastructure/Elasticsearch/` (Elasticsearch)
- `src/php/App/Infrastructure/Storage/` (S3/SeaweedFS)

**Node.js:**

- `src/node/backend/services/CacheService.ts` (Redis)
- `src/node/backend/services/QueueService.ts` (RabbitMQ)
- `src/node/backend/services/DatabaseService.ts` (Repository)
- `src/node/backend/services/SearchService.ts` (Meilisearch)
- `src/node/backend/services/ElasticsearchService.ts` (Elasticsearch)
- `src/node/backend/services/StorageService.ts` (S3/SeaweedFS)

**Tests:** Corresponding unit tests in `tests/php/` and `tests/node/`
