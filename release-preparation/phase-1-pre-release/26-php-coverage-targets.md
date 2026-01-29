# 26: PHP Test Coverage Improvement

## Context

zappzarapp is a **Developer Platform** with security-by-design principles,
targeting both small private projects and enterprise use cases. As platform
infrastructure that developers rely on, coverage standards must reflect this
responsibility.

## Current State (26. Jan 2026)

**Overall: 26.55%** (998 / 3759 lines)

### By Category

| Category | Current | Lines |
|----------|---------|-------|
| Security (Encryption, TLS, Audit) | ~85% | 146 |
| Core Infrastructure | ~50% | 1200 |
| Optional Services | ~10% | 1100 |
| Dev Tools | ~21% | 1300 |

## Coverage Model for Developer Platform

### Tier 1: Security-Critical (Target: 90%+)

Non-negotiable for a security-by-design platform.

| Component | Current | Target | Gap | Priority |
|-----------|---------|--------|-----|----------|
| Encryption/ | 97.73% | 90%+ | ✅ Done | - |
| TlsConfig.php | 100% | 90%+ | ✅ Done | - |
| Audit/ | 74.07% | 90%+ | +16% | **P1** |
| Session/ | 90.11% | 90%+ | ✅ Done | - |

### Tier 2: Core Infrastructure (Target: 80%+)

Foundation that all users depend on.

| Component | Current | Target | Gap | Priority |
|-----------|---------|--------|-----|----------|
| Database/ | 81.20% | 80%+ | ✅ Done | - |
| DatabaseConfig.php | 98.63% | 80%+ | ✅ Done | - |
| Cache/ | 51.30% | 80%+ | +29% | **P1** |
| HealthCheck.php | 0% | 80%+ | +80% | **P1** |
| HealthStatus.php | 0% | 80%+ | +80% | **P1** |
| Http/ | 11.43% | 80%+ | +69% | **P1** |

### Tier 3: Optional Services (Target: 60%+)

Services that may not be used by all, but should work reliably when enabled.

| Component | Current | Target | Gap | Priority |
|-----------|---------|--------|-----|----------|
| Search/ (Meilisearch) | 27.82% | 60%+ | +32% | P2 |
| Elasticsearch/ | 0% | 60%+ | +60% | P2 |
| Queue/ (RabbitMQ) | 0% | 60%+ | +60% | P2 |
| Storage/ (SeaweedFS) | 0% | 60%+ | +60% | P2 |
| ViteHelper.php | 54.29% | 60%+ | +6% | P2 |

### Tier 4: Developer Tools (Target: 50%+)

Internal tooling - important but not user-facing.

| Component | Current | Target | Gap | Priority |
|-----------|---------|--------|-----|----------|
| DevDashboard/Controllers | 9.78% | 50%+ | +40% | P3 |
| DevDashboard/Services | 22.19% | 50%+ | +28% | P3 |
| DevDashboard/Response | 25% | 50%+ | +25% | P3 |

## Release Targets

### v1.0 Release (Phase 1)

| Metric | Current | Target |
|--------|---------|--------|
| **Overall** | 26.55% | **55%** |
| Security-Critical | ~85% | **90%+** |
| Core Infrastructure | ~50% | **80%+** |
| Optional Services | ~10% | 40% |
| Dev Tools | ~21% | 35% |

### v1.1 Release (Phase 2)

| Metric | Target |
|--------|--------|
| **Overall** | **70%** |
| Security-Critical | **95%+** |
| Core Infrastructure | **85%+** |
| Optional Services | **60%+** |
| Dev Tools | **50%+** |

## Implementation Plan

### Phase 1 Priority Order

1. **HealthCheck.php** (0% → 80%)
   - 421 lines, core feature
   - Status endpoint used by monitoring
   - Estimated: 15-20 test cases

2. **Http/** (11% → 80%)
   - Request/Response handling
   - CORS, routing, middleware
   - Estimated: 20-25 test cases

3. **Cache/** (51% → 80%)
   - Redis integration
   - Critical for performance
   - Estimated: 10-15 test cases

4. **Audit/** (74% → 90%)
   - Security logging
   - Compliance requirement
   - Estimated: 5-8 test cases

5. **HealthStatus.php** (0% → 80%)
   - Quick win - only 16 lines
   - Estimated: 3-5 test cases

### Phase 2 Priority Order

1. Optional Services (Elasticsearch, Queue, Storage)
2. DevDashboard improvements
3. Edge cases and error paths

## Rationale: Why These Targets?

### Developer Platform vs. Boilerplate

| Aspect | Boilerplate | Developer Platform |
|--------|-------------|-------------------|
| Code Purpose | Starting point | Reliable foundation |
| User Expectation | "I'll fix bugs" | "It should work" |
| Security | Nice-to-have | Non-negotiable |
| Coverage Standard | 50% acceptable | 70%+ expected |

### Security-by-Design Implications

- Enterprise users require audit compliance
- Security components must be thoroughly tested
- Trust is built through demonstrated quality
- 90%+ security coverage is industry expectation for security-focused tools

### Universal Usability (Small + Enterprise)

- Small projects: Trust that defaults are secure
- Enterprise: Evidence of quality for compliance
- Both: Reliable infrastructure that "just works"

## Metrics to Track

- Line coverage (primary)
- Method coverage (secondary)
- Class coverage (tertiary)
- Mutation testing score (future consideration)

## Files to Create/Modify

### New Test Files Needed

- `tests/php/App/Unit/Infrastructure/HealthCheckTest.php`
- `tests/php/App/Unit/Infrastructure/HealthStatusTest.php`
- `tests/php/App/Unit/Http/` - Multiple test files
- `tests/php/App/Unit/Infrastructure/Cache/` - Expand existing

### Existing to Expand

- `tests/php/App/Unit/Infrastructure/Audit/` - Add edge cases
- `tests/php/App/Unit/Infrastructure/Cache/` - Complete coverage

## Definition of Done

- [ ] Overall coverage ≥ 55%
- [ ] All Security-Critical components ≥ 90%
- [ ] All Core Infrastructure components ≥ 80%
- [ ] No component at 0% coverage
- [ ] CI pipeline enforces coverage thresholds
- [ ] Coverage report accessible via Dev Dashboard

## Priority

**High** - Quality foundation for Developer Platform positioning.

## References

- Current report: `build/coverage-php/index.html`
- PHPUnit config: `phpunit.xml`
- Coverage output: `build/coverage-php/`
