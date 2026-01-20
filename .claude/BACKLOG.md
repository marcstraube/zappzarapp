# Project Backlog

Future tasks and improvements to be implemented.

## Task Scope Guide

Each task has a **Scope** indicator to help with session planning:

| Scope | Time | Files | Session |
|-------|------|-------|---------|
| Small | <30 min | 1-3 files | Same session OK |
| Medium | 30 min - 2h | 3-10 files | Flexible |
| Large | >2h | Many files, exploration | New session recommended |

---

## High Priority

### v1.0 Release Preparation

**Status:** Planned
**Scope:** Large
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

### Makefile Target Testing with BATS + Goss Integration

**Status:** Planned
**Scope:** Large
**Created:** 2026-01-19
**Planning:** Required
**Context:** Feature request for environment-specific testing of Make targets

**Goal:** Add automated tests for Makefile targets using BATS for command execution and Goss for state validation.

**Before starting:** Use Plan Mode to analyze:
- Current Makefile structure and target dependencies
- Which targets are environment-sensitive (DB_TYPE, NODE_MODE, etc.)
- Existing Goss test structure and how to integrate
- CI/CD pipeline integration

**Tools:**

| Tool | Purpose |
|------|---------|
| BATS | Command execution, exit codes, output validation |
| Goss | Container/system state validation after commands |

**Combined approach:**
```bash
@test "make up creates healthy containers" {
  run make up
  [ "$status" -eq 0 ]

  # Goss validates resulting state
  run make goss-test
  [ "$status" -eq 0 ]
}
```

**Test scenarios:**

1. **BATS-only tests:**
   - Target exit codes and basic functionality
   - Error handling (missing dependencies, containers not running)
   - Output validation for help/info targets

2. **BATS + Goss combined tests:**
   - `make up` → Goss validates container state
   - `make build-*` → Goss validates image contents
   - Environment matrix (DB_TYPE, NODE_MODE) → Goss validates config

**Files to create:**
- `tests/bats/` directory structure
- `tests/bats/make-targets.bats` (core command tests)
- `tests/bats/make-environment.bats` (env-specific with Goss)
- `tests/bats/helpers/` (shared setup, Goss integration)
- `Makefile` (new `test-bats` and `test-full` targets)

**Dependencies:**
- BATS installation (via package manager or git submodule)
- `bats-support` and `bats-assert` helper libraries
- Existing Goss setup (already in project)

---

### Service Integration Examples

**Status:** Planned (v1.1)
**Scope:** Large
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
| SeaweedFS/S3 | File upload example | File upload example |

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

### Code Quality

#### PHP Code Quality: SuppressWarnings Cleanup

**Status:** Planned
**Scope:** Medium
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

### Testing Enhancements

#### Mutation Testing Integration

**Status:** Planned
**Scope:** Medium
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
**Scope:** Medium
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

#### TLS Certificate Architecture

**Status:** Planned
**Scope:** Medium
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
**Scope:** Medium
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
**Scope:** Large
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
**Scope:** Large
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

### Database Schema Diff / Advanced Migrations

**Status:** Planned
**Scope:** Large
**Planning:** Required
**Created:** 2026-01-19
**Context:** Feature request for automated schema diff generation

**Goal:** Extend `make db-migrations` with schema-diff capabilities:

1. `make db-migrate` - Apply migrations (current functionality)
2. `make db-diff` - Generate migration SQL from schema changes

**Before starting:** Use Plan Mode to evaluate:
- Best tooling for this project (Atlas, Flyway, Skeema, migra, etc.)
- Multi-DB support requirements (PostgreSQL + MariaDB)
- "Source of Truth" approach (code-first vs. DB-first)
- Docker integration and CI/CD considerations
- Complexity vs. benefit trade-off

**Candidate tools:**

| Tool | PostgreSQL | MariaDB | Notes |
|------|------------|---------|-------|
| Atlas | ✅ | ✅ | Go, declarative, modern |
| Flyway | ✅ | ✅ | Java, widely adopted |
| Skeema | ❌ | ✅ | MySQL/MariaDB only |
| migra | ✅ | ❌ | Python, PostgreSQL only |

**Notes:**
- Current simple SQL-file approach may be sufficient for most users
- This is an enhancement for larger projects
- Keep backwards compatibility with existing `migrations/` structure

---

## Completed

(Completed items are moved to CHANGELOG.md)
