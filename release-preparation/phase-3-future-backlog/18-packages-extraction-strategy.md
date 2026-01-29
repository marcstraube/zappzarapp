# Package Extraction Strategy (v1.2+)

**Status:** Future
**Priority:** Low
**Complexity:** High
**Target Version:** 1.2+
**Estimated Effort:** 4-6 weeks

## Problem

After establishing the monorepo packages structure in v1.1 with foundation libraries (tls-config, docker-secrets, db-connection), we need to evaluate which additional components are worth extracting.

**Candidates:**
- Audit Logger (Shared/Audit)
- Encryption Service (Shared/Encryption)
- Health Check Service (Shared/HealthCheck)
- Repository Layer (Shared/Repository)
- Service Adapters (Cache, Queue, Search, Storage, etc.)

## Extraction Criteria Matrix

| Component | Stability | Dependencies | API Size | Reusability | Priority |
|-----------|-----------|--------------|----------|-------------|----------|
| Audit Logger | High | Low (0-1) | Small | High | **High** |
| Encryption | High | Low (1-2) | Small | Medium | **Medium** |
| Health Check | Medium | High (3-5) | Large | Low | Low |
| Repository | Medium | High (4-6) | Large | Medium | Low |
| Cache Service | High | Medium (2-3) | Medium | Low | **Low** |
| Queue Service | High | Medium (2-3) | Medium | Low | **Low** |
| Search Service | Medium | Medium (2-3) | Medium | Low | **Low** |
| Storage Service | Medium | Medium (2-3) | Large | Low | **Low** |

### Criteria Definitions

**Stability:**
- High: No breaking changes in last 6+ months
- Medium: 1-2 breaking changes in last 6 months
- Low: Frequent API changes

**Dependencies:**
- Low: 0-2 external packages
- Medium: 3-5 external packages
- High: 6+ external packages or complex dependencies

**API Size:**
- Small: < 10 public methods/functions
- Medium: 10-20 public methods/functions
- Large: 20+ public methods/functions

**Reusability:**
- High: Useful in 80%+ of projects
- Medium: Useful in 40-80% of projects
- Low: Useful in < 40% of projects (boilerplate-specific)

## Phase 2: Audit Logger (v1.2)

### Rationale

**Extract:** YES
- Clean interface + null object pattern
- Zero external dependencies (only uses DB connection)
- Small, focused API (5 methods)
- Every security-conscious project needs audit logging
- Different backends possible (DB, file, syslog, etc.)

### Structure

```
packages/
├── php/
│   └── audit-logger/
│       ├── src/
│       │   ├── AuditLoggerInterface.php
│       │   ├── DatabaseAuditLogger.php
│       │   ├── NullAuditLogger.php
│       │   └── FileAuditLogger.php (new)
│       ├── tests/
│       └── README.md
└── node/
    └── audit-logger/
        ├── src/
        │   ├── AuditLoggerInterface.ts
        │   ├── DatabaseAuditLogger.ts
        │   ├── NullAuditLogger.ts
        │   └── FileAuditLogger.ts (new)
        ├── tests/
        └── README.md
```

### Implementation

**Dependencies:**
- `zappzarapp/db-connection` (optional, only for DatabaseAuditLogger)
- PSR-3 Logger interface (PHP, optional)

**API:**
```typescript
interface AuditLoggerInterface {
  logCreate(tableName: string, recordId: number, userId: number, data: object): void;
  logUpdate(tableName: string, recordId: number, userId: number, changes: object): void;
  logDelete(tableName: string, recordId: number, userId: number): void;
  logRead(tableName: string, recordId: number, userId: number): void;
  logAction(action: string, details: object, userId: number): void;
}
```

**Effort:** 2-3 weeks
- Extract existing implementations
- Add FileAuditLogger backend
- Write comprehensive tests
- Documentation

## Phase 3: Encryption Service (v1.2-1.3)

### Rationale

**Extract:** MAYBE
- Security-critical → needs thorough audits
- Multiple cipher strategies (AES-256-GCM, ChaCha20-Poly1305)
- Small API (5-8 methods)
- Moderate dependencies (crypto extensions, docker-secrets)

**Concerns:**
- Security implications of standalone package
- Needs extensive documentation on secure usage
- Versioning must be very conservative (no breaking changes)

### Structure

```
packages/
├── php/
│   └── encryption/
│       ├── src/
│       │   ├── EncryptionInterface.php
│       │   ├── AesGcmEncryption.php
│       │   ├── ChaCha20Encryption.php
│       │   └── EncryptionFactory.php
│       ├── tests/
│       ├── docs/
│       │   ├── security-considerations.md
│       │   └── key-management.md
│       └── README.md
└── node/
    └── encryption/
        ├── src/
        │   ├── EncryptionInterface.ts
        │   ├── AesGcmEncryption.ts
        │   ├── ChaCha20Encryption.ts
        │   └── EncryptionFactory.ts
        ├── tests/
        ├── docs/
        └── README.md
```

### Implementation

**Dependencies:**
- `zappzarapp/docker-secrets` (for key loading)
- Native crypto extensions (OpenSSL, Node crypto)

**API:**
```typescript
interface EncryptionInterface {
  encrypt(plaintext: string): string;
  decrypt(ciphertext: string): string;
  encryptBinary(data: Buffer): Buffer;
  decryptBinary(encrypted: Buffer): Buffer;
  rotateKey(oldKey: string, newKey: string): void;
}
```

**Effort:** 3-4 weeks
- Extract existing implementations
- Security audit (internal + external?)
- Add key rotation support
- Extensive documentation
- Test vectors for compliance

**Decision Point:** Extract only if we commit to:
- Professional security audit
- Long-term support (no breaking changes)
- Comprehensive documentation

## Service Adapters: DO NOT EXTRACT

### Rationale

**Cache Service (Redis):**
- ❌ Thin wrapper around `predis` / `ioredis`
- ❌ Minimal value over existing libraries
- ❌ Too boilerplate-specific (TLS config, prefixing)

**Queue Service (RabbitMQ):**
- ❌ Better libraries exist (`php-amqplib`, `amqplib`)
- ❌ Our wrapper is opinionated for boilerplate use
- ❌ Limited reusability

**Search Service (Meilisearch):**
- ❌ Official SDKs available
- ❌ Our abstraction is boilerplate-specific
- ❌ Low demand for standalone version

**Storage Service (S3/SeaweedFS):**
- ❌ AWS SDK and compatible libraries exist
- ❌ Complex signature implementation
- ❌ Better to recommend official SDKs

**Elasticsearch Service:**
- ❌ Official clients available
- ❌ Thin wrapper with minimal value
- ❌ Boilerplate-specific features (bulk indexing patterns)

### Alternative: Integration Guides

Instead of extracting service adapters, provide:
- Documentation: "How to integrate Redis"
- Documentation: "How to integrate RabbitMQ"
- Code examples in boilerplate
- Best practices for TLS, connection pooling, etc.

**Keep these services IN the boilerplate** as reference implementations, not as standalone packages.

## Health Check Service: DO NOT EXTRACT

### Rationale

**Why NOT:**
- ❌ Too many dependencies (DB, Redis, queue, search, etc.)
- ❌ Boilerplate-specific check implementations
- ❌ Every project has different health check requirements
- ❌ Large API surface (10+ check methods)
- ❌ Tight coupling to infrastructure layout

**Better approach:**
- Keep in boilerplate as reference implementation
- Document extensibility patterns
- Provide interface for custom checks

## Repository Layer: DO NOT EXTRACT (Yet)

### Rationale

**Why NOT (for now):**
- ⚠️ Still evolving (AbstractRepository has 515 LOC)
- ⚠️ Many dependencies (ConnectionFactory, AuditLogger, Encryption)
- ⚠️ Large API (20+ methods)
- ⚠️ Database-specific implementations (PostgreSQL vs MySQL)
- ⚠️ Query builder patterns still stabilizing

**Reconsider in v2.0:**
- If API becomes stable
- If community shows interest
- If we can provide real value over Doctrine/Eloquent/TypeORM

## Implementation Roadmap

### Version 1.2 (Q2 2026)
- [ ] Extract Audit Logger package
- [ ] Add FileAuditLogger backend
- [ ] Tests + Documentation
- [ ] CI integration

### Version 1.3 (Q3 2026)
- [ ] Decide: Extract Encryption Service?
- [ ] If yes: Security audit
- [ ] If yes: Extract with conservative API
- [ ] Update boilerplate to use packages

### Version 2.0 (2027)
- [ ] Evaluate Repository Layer extraction
- [ ] Evaluate Health Check patterns
- [ ] Decide on monorepo vs separate repos (see 19-standalone-packages-evaluation.md)

## Success Criteria

**For Audit Logger (v1.2):**
- [ ] Clean interface with 3+ backend implementations
- [ ] 90%+ test coverage
- [ ] Performance impact < 2%
- [ ] Documentation with security examples
- [ ] Community feedback positive

**For Encryption Service (v1.3):**
- [ ] Security audit passed
- [ ] Key rotation support
- [ ] Cipher agility (multiple algorithms)
- [ ] 95%+ test coverage
- [ ] Extensive documentation on secure usage

## References

- Current implementations:
  - PHP: `src/php/App/Infrastructure/Audit/`
  - Node: `src/node/backend/Shared/Audit/`
  - PHP: `src/php/App/Infrastructure/Encryption/`
  - Node: `src/node/backend/Shared/Encryption/`
- Security best practices: OWASP Cryptographic Storage Cheat Sheet
- Package extraction criteria: 26-monorepo-packages-foundation.md
