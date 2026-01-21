# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Meilisearch Search services for PHP (`SearchInterface`, `MeilisearchSearch`,
  `MeilisearchConfig`)
- Meilisearch Search service for Node.js (`SearchService`,
  `SearchServiceInterface`)
- Shared `loadCredential` utility for Node.js to avoid code duplication
- Meilisearch health check integration in `HealthCheckService`
- Full unit test coverage for Meilisearch services
- RabbitMQ Queue services for PHP (`QueueInterface`, `RabbitMQQueue`)
- RabbitMQ Queue service for Node.js (`QueueService`, `QueueServiceInterface`)
- Redis Cache and Session services for PHP (`CacheInterface`, `RedisCache`,
  `SessionInterface`, `RedisSession`)
- Redis Cache and Session services for Node.js (`CacheService`,
  `SessionService`)
- Full unit test coverage for all new services (46 tests)
- TLS support for Redis connections (auto-detect `rediss://` URLs)
- ESLint rules for redundant code detection (`no-unnecessary-condition`,
  `no-unnecessary-type-assertion`)
- PHPStorm inspection profile: disabled "variable only used in closure" for test
  files

### Changed

- CaptainHook secret scanner now allows `$PROJECT_DIR$/vendor/` paths (fixes
  false positives in IDE config files)

---

[Unreleased]: https://github.com/marcstraube/zappzarapp/compare/v1.0.0...HEAD
