# Project Backlog

Future tasks and improvements to be implemented.

---

## High Priority

### Database Password Configuration Issue

**Status:** Open
**Created:** 2026-01-19
**Context:** Discovered during health endpoint testing

**Problem:** PostgreSQL returns "password authentication failed for user 'app'" when health checks attempt database connections.

**Root Cause:** The `DB_PASSWORD` in `.env.example` doesn't match the password configured in PostgreSQL container.

**Fix Required:**
1. Verify PostgreSQL is initialized with correct password from `DB_PASSWORD`
2. Check if `POSTGRES_PASSWORD` in compose.yaml uses `${DB_PASSWORD}`
3. Update `.env.example` documentation

**Files to check:**
- `.env.example` (DB_PASSWORD default)
- `compose.yaml` (postgres service environment)
- `docker/postgres/init-scripts/` (if any)

---

### v1.0 Release Preparation

**Status:** Planned
**Created:** 2026-01-19
**Context:** Project approaching first public release, git history needs cleanup

**Goal:** Prepare clean v1.0 release with squashed git history and consolidated changelog.

**Prerequisites (complete before release):**
- [ ] All critical bugs fixed
- [ ] All high-priority tasks completed (or deferred to v1.1)
- [ ] Full test suite passes (`make check`, `make goss-test-matrix`)
- [ ] Documentation complete and accurate

**Release Steps:**

1. **Final Quality Check**
   ```bash
   make check
   make goss-test-matrix
   ```

2. **Changelog Consolidation**
   - Convert granular version history to feature summary
   - Format: "v1.0.0 Initial Release - Feature Overview"
   - List all major features, not individual changes

3. **Git History Cleanup (Orphan Branch)**
   ```bash
   # Create clean branch without history
   git checkout --orphan release-1.0
   git add -A
   git commit -m "feat: initial release v1.0.0"

   # Replace main branch
   git branch -D main
   git branch -m release-1.0 main
   ```

4. **Tagging & Release**
   ```bash
   git tag -a v1.0.0 -m "Initial public release"
   git push -f origin main
   git push origin v1.0.0
   ```

5. **Post-Release**
   - Create GitHub/GitLab release with release notes
   - Archive old branch if needed for reference

**Files to modify:**
- `documentation/CHANGELOG.md` (consolidate to v1.0 summary)
- `.claude/BACKLOG.md` (clean up completed items)

**Notes:**
- Force-push required - coordinate if others have cloned
- Consider keeping old branch as `archive/pre-v1.0` for reference

---

### Service Integration Examples

**Status:** Planned (v1.1)
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

## Medium Priority

### Quick Wins

#### Container Security Scanning: Add Node Images

**Status:** Planned
**Created:** 2026-01-19
**Context:** Trivy fully integrated but `security-scan` only covers PHP + Nginx

**Current state (already working):**
- `make security-scan` - scans PHP + Nginx images ✅
- `make security-sbom` - generates SBOM ✅
- `make security-config` - scans Dockerfiles ✅
- CI/CD integration (GitHub + GitLab) ✅
- Documentation complete ✅

**Missing:** Node and Node-Backend images not included in `security-scan`

**Task:** Extend `make security-scan` to also scan:
- `zappzarapp-node:latest`
- `zappzarapp-node-backend:latest`

**Files to modify:**
- `Makefile` (extend `security-scan` target)

---

#### pnpm Update

**Status:** Planned
**Created:** 2026-01-19

**Task:** Update Node.js dependencies to latest versions.

**Steps:**
1. Run `make pnpm -- update` (executes pnpm in container)
2. Run `make test` to verify nothing breaks
3. Fix any breaking changes if needed

**Note:** Lockfiles (`pnpm-lock.yaml`) are never committed - this is a boilerplate. Users generate their own lockfiles.

---

#### ESLint Errors in PHPStorm (Node Tests)

**Status:** Planned
**Created:** 2026-01-19
**Context:** PHPStorm shows ESLint errors in Node backend unit tests that our CI linters don't catch

**Task:** Investigate discrepancy between PHPStorm ESLint and configured linters.

**Investigation steps:**
1. Document which errors PHPStorm shows (file, line, rule)
2. Run `make lint-node` and compare output
3. Check ESLint config: `.eslintrc.*`, `eslint.config.*`
4. Check if PHPStorm uses different ESLint version/config
5. Determine if PHPStorm is correct (missing CI config) or wrong (misconfigured)

**Potential causes:**
- PHPStorm using global ESLint instead of project's
- Different ESLint config for tests vs. src
- Missing `overrides` section in ESLint config for test files
- PHPStorm not respecting `.eslintignore`

**Resolution:**
- If PHPStorm is right: Fix ESLint config for CI
- If PHPStorm is wrong: Configure PHPStorm to use project ESLint

**Files to check:**
- `eslint.config.js` or `.eslintrc.*`
- `tests/node/backend/**/*.test.ts`
- PHPStorm Settings → Languages & Frameworks → JavaScript → Code Quality Tools → ESLint

---

#### Claude Settings Consolidation

**Status:** Planned
**Created:** 2026-01-19
**Context:** Simplify Claude Code permissions by leveraging Makefile targets

**Goal:** Allow all Makefile targets in `settings.json` and remove redundant specific permissions from `settings.local.json`.

**Rationale:**
- Makefile targets provide a controlled interface to underlying tools
- `Bash(make:*)` in settings.json covers all targets safely
- Many permissions in settings.local.json are redundant (e.g., `Bash(docker:*)` when `make build-*` exists)

**Implementation Steps:**
1. Audit Makefile for all available targets
2. Update `settings.json` to allow `Bash(make:*)`
3. Identify permissions in `settings.local.json` covered by make targets
4. Remove redundant permissions from `settings.local.json`
5. Test that common workflows still work

**Files to modify:**
- `.claude/settings.json`
- `.claude/settings.local.json`

---

#### DevDashboard Update Check

**Status:** Planned
**Created:** 2026-01-19
**Context:** Recent changes to health endpoints and services

**Task:** Verify DevDashboard is up-to-date with recent changes.

**Checklist:**
- [ ] Health endpoint URLs updated (`/health`, `/ready`, `/api/health`)
- [ ] Service status display reflects new response format
- [ ] Any new services added that should appear?
- [ ] Remove references to deprecated endpoints

**Files to review:**
- `src/php/DevDashboard/`
- `templates/dev-dashboard/`

---

#### Container Analytics Audit

**Status:** Planned
**Created:** 2026-01-19
**Context:** We disabled Meilisearch analytics with `MEILI_NO_ANALYTICS=true`

**Task:** Check all containers for telemetry/analytics that should be disabled.

**Containers to audit:**
- Elasticsearch (telemetry settings?)
- MinIO (analytics?)
- RabbitMQ (telemetry?)
- Redis (no analytics expected)
- PostgreSQL/MariaDB (no analytics expected)
- Mercure (analytics?)

**Goal:** Privacy-respecting defaults, no phone-home behavior.

---

### Code Quality

#### PHP Code Quality: SuppressWarnings Cleanup

**Status:** Planned
**Created:** 2026-01-19
**Context:** Continuation of code quality improvements from 2026-01-18

**Task:** Audit remaining `@SuppressWarnings` annotations and reduce class complexity.

**Goals:**
1. Remove remaining `@SuppressWarnings` where possible
2. Reduce class complexity (CyclomaticComplexity, NPathComplexity)
3. Extract methods/classes where appropriate
4. Document legitimate suppressions (why they're needed)

**Steps:**
1. `grep -r "@SuppressWarnings" src/php/` to find all occurrences
2. For each: Can the underlying issue be fixed?
3. Refactor complex methods into smaller units
4. Run `make phpmd` to verify improvements

**Files to review:**
- All files with `@SuppressWarnings` annotations
- Classes flagged by PHPMD for complexity

---

#### Shell Compatibility (Brace Expansion)

**Status:** Planned
**Created:** 2026-01-19
**Context:** Brace expansion `{a,b}` is Bash-specific, not POSIX sh compatible

**Task:** Audit codebase for brace expansion usage and decide whether to fix for sh compatibility.

**Questions to answer:**
- Where is brace expansion used? (scripts, Makefile, entrypoints)
- Do we need POSIX sh compatibility? (Alpine uses ash/busybox)
- What's the fix cost vs. benefit?

**Files to check:**
- `docker/*/entrypoint.sh`
- `Makefile`
- `tests/goss/*.sh`

---

#### Makefile Clean Targets Analysis

**Status:** Planned
**Created:** 2026-01-19

**Task:** Analyze whether additional clean sub-commands are useful.

**Current state:**
- `make clean` - what does it clean?

**Potential additions:**
- `make clean-images` - remove Docker images
- `make clean-volumes` - remove Docker volumes
- `make clean-cache` - remove build caches (.phpstan.cache, etc.)
- `make clean-all` - nuclear option

**Analysis needed:**
- What do other boilerplates offer?
- What's actually useful in daily workflow?
- Risk of data loss (volumes contain DB data!)

---

### Testing Enhancements

#### Mutation Testing Integration

**Status:** Planned
**Created:** 2026-01-19
**Context:** Current tests pass, but do they actually catch bugs?

**Goal:** Add mutation testing to verify test effectiveness.

**Tools:**
- **PHP:** Infection (https://infection.github.io/)
- **Node:** Stryker (https://stryker-mutator.io/)

**What it does:**
- Modifies code (mutations) and re-runs tests
- If tests still pass → "mutant survived" = test gap
- Mutation Score Indicator (MSI) shows test quality

**Implementation:**
1. Add Infection to composer dev dependencies
2. Add Stryker to package.json dev dependencies
3. Create Makefile targets: `mutation-test-php`, `mutation-test-node`
4. Configure for CI (optional, slow but valuable)

**Expected findings:**
- Tests that don't assert correctly
- Dead code paths
- Missing edge case coverage

**Files to create/modify:**
- `infection.json5` (PHP config)
- `stryker.conf.json` (Node config)
- `Makefile` (new targets)

---

#### Security Static Analysis (SAST)

**Status:** Planned
**Created:** 2026-01-19
**Context:** PHPStan finds type errors but not security vulnerabilities

**Goal:** Add security-focused static analysis to catch vulnerabilities.

**Tools:**
- **Semgrep:** Language-agnostic, great security rules
- **Psalm Taint Analysis:** PHP-specific, finds SQL injection, XSS
- **ESLint Security Plugin:** Node.js security patterns

**What it finds:**
- SQL Injection (string concatenation in queries)
- XSS (unescaped output)
- Command Injection
- Path Traversal
- Insecure Deserialization
- Hardcoded Secrets

**Implementation:**
1. Add Semgrep config (`.semgrep.yml`)
2. Enable Psalm taint analysis mode
3. Add `eslint-plugin-security` to Node
4. Create Makefile targets: `security-scan`, `security-scan-php`, `security-scan-node`

**Files to create/modify:**
- `.semgrep.yml` or `.semgrep/`
- `psalm.xml` (add taint analysis)
- `eslint.config.js` (add security plugin)
- `Makefile` (new targets)

---

### Infrastructure

#### MinIO Custom Build from Source

**Status:** Planned
**Created:** 2026-01-19
**Context:** MinIO stopped publishing Docker images to Docker Hub in October 2025

**Problem:** MinIO no longer publishes official Docker images. The last available version on Docker Hub is `RELEASE.2025-09-07T16-13-09Z`, which is missing a critical security patch from October 2025.

**Current workaround:** Using older official version (already applied)

**Goal:** Build MinIO from source in our own Dockerfile for:
- Full control over versions and security patches
- Consistency with project philosophy (custom Dockerfiles for all services)
- Independence from third-party Docker Hub publishers

**Implementation:**
1. Create `docker/minio/Dockerfile` with multi-stage build
2. Clone MinIO source, checkout specific release tag
3. Build binary with Go
4. Create minimal runtime image (Alpine-based)
5. Update compose.yaml to use custom image

**Reference:**
```dockerfile
FROM golang:1.21-alpine AS builder
RUN git clone https://github.com/minio/minio.git && \
    cd minio && \
    git checkout RELEASE.2025-10-15T17-29-55Z && \
    go build -o /minio ./cmd/minio

FROM alpine:3.19
COPY --from=builder /minio /usr/bin/minio
# ... health check, entrypoint
```

**Files to modify:**
- `docker/minio/Dockerfile` (rewrite for source build)
- `compose.yaml` (if image name changes)

**Notes:**
- Renovate can monitor MinIO GitHub releases for updates
- Consider adding to CI/CD for automated rebuilds

---

#### TLS Certificate Architecture

**Status:** Planned
**Created:** 2026-01-19
**Context:** User question about separate certs for frontend/backend services

**Questions to investigate:**
1. Should nginx frontend and backend services have separate certificates?
2. How can a dev use custom CA certificates (corporate environments)?
3. Is there a clean way to inject custom CA bundles into containers?
4. Should we support `EXTRA_CA_CERTS` or similar environment variable?

**Potential solutions:**
- Volume mount for custom CA certs
- Environment variable pointing to CA bundle
- Init script that adds certs to system trust store

---

### UI/UX

#### Page Design Customization

**Status:** Planned
**Created:** 2026-01-19

**Task:** Adapt error pages and static pages to zappzarapp design.

**Pages to update:**
- `public/index.html` (fallback/static)
- Error pages (404, 500, 502, 503)
- Maintenance page
- nginx default error pages

**Considerations:**
- Consistent branding across all pages
- Dark/light mode support
- Accessibility (contrast, screen readers)

---

#### Frontend Testing Framework (Storybook)

**Status:** Planned
**Created:** 2026-01-19

**Task:** Evaluate Storybook or similar for component testing and accessibility.

**Benefits:**
- Visual component documentation
- Isolated component development
- Accessibility testing (color contrast, WCAG)
- Screenshot testing for visual regression

**Questions:**
- Storybook vs. alternatives (Histoire, Ladle)?
- Integration with existing Vite setup?
- CI/CD integration for visual regression?

---

### IDE Integration

#### PHPStorm Database Configuration

**Status:** Planned
**Created:** 2026-01-19
**Assignee:** User (manual) / Claude (investigation)

**Task:** Configure PHPStorm database connection, investigate auto-config from .env.

**Investigation needed:**
1. Can PHPStorm read `.env` files for connection params?
2. Is there a `.idea/dataSources.xml` template we can provide?
3. Can we document the manual setup steps?

**Files to check/create:**
- `.idea/dataSources.xml` (template?)
- `documentation/IDE-SETUP.md` (if needed)

---

### Future Ideas (Brainstorm)

#### DevDashboard Feature Ideas

**Status:** Brainstorm
**Created:** 2026-01-19

**Potential features for developer productivity:**

1. **Quick Actions**
   - One-click cache clear (Redis, OPcache)
   - Restart individual services
   - Trigger queue worker

2. **Configuration Viewer**
   - Show active .env values (masked secrets)
   - Show enabled COMPOSE_PROFILES
   - Show active NODE_MODE

3. **Log Viewer**
   - Real-time logs via WebSocket (see separate task)
   - Log level filtering
   - Search in logs

4. **Database Tools**
   - Quick query runner
   - Table browser
   - Migration status

5. **Queue Monitor**
   - RabbitMQ queue depths
   - Failed job viewer
   - Retry failed jobs

6. **Performance Metrics**
   - Response time graphs
   - Memory usage
   - CPU usage per container

---

#### LiveLogs via WebSocket

**Status:** Planned
**Created:** 2026-01-19
**Context:** Feature request for DevDashboard

**Task:** Investigate real-time log streaming to DevDashboard via WebSocket.

**Technical approach:**
1. WebSocket endpoint in Node.js backend
2. Connect to Docker API or `docker logs --follow`
3. Stream logs to browser
4. Filter by service/container

**Challenges:**
- Docker socket access from container
- Authentication/authorization
- Performance with high log volume
- Log buffering strategy

**Alternatives:**
- Mercure for SSE-based streaming
- Polling (simpler but less real-time)

**Files to create:**
- `src/node/backend/websocket/logStreamer.ts`
- `templates/dev-dashboard/partials/log-viewer.php`

---

## Low Priority

(No items yet)

---

## Completed

(Completed items are moved to CHANGELOG.md)
