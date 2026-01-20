# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

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

---

[Unreleased]: https://github.com/marcstraube/zappzarapp/compare/v1.0.0...HEAD
