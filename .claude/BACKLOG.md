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

### Resolve .claude/ Directory Commit Policy

**Status:** Open
**Scope:** Small
**Created:** 2026-01-20
**Context:** Documentation says AI agent files should not be committed, but .claude/ is committed

**Problem:**

`documentation/CONTRIBUTING.md` states:
> Do not commit personal tool configurations:
> - `.claude/` - Claude Code settings

But the project has 18 `.claude/` files committed (commands, settings, learnings, backlog).

**Options to evaluate:**

1. **Keep .claude/ committed (update docs)**
   - Pro: Slash commands, learnings, backlog are project-specific and valuable
   - Pro: New contributors get working Claude Code setup immediately
   - Con: Contradicts "personal configuration" philosophy
   - Action: Update CONTRIBUTING.md to explain which .claude/ files ARE committed

2. **Move to different location**
   - Pro: Avoids .claude/ which is typically personal
   - Con: Breaks Claude Code conventions, more complex setup
   - Action: Move commands to `.project/commands/`, settings elsewhere

3. **Split: commit some, gitignore others**
   - Pro: Best of both worlds
   - Con: More complex to maintain
   - Action: Commit commands/settings.json, gitignore sessions/learnings/backlog

**Decision needed:** Which approach fits this boilerplate best?

**Files to modify (depending on decision):**
- `documentation/CONTRIBUTING.md`
- `.gitignore`
- Possibly move files

---

### Fix frontend-clean and Rename to node-frontend-*

**Status:** Open
**Scope:** Small
**Created:** 2026-01-20
**Context:** Bug discovered during cleanup of accidentally committed frontend files

**Problems:**

1. **frontend-clean doesn't delete everything**: `.nuxt`, `.next`, etc. directories contain root-owned files (created by container). Host-side `rm -rf` fails silently due to permissions.

2. **Naming inconsistency**: All Node-related targets use `node-*` prefix, but frontend scaffolding uses `frontend-*`.

**Solution:**

1. Run cleanup inside container (has root permissions)
2. Restore placeholder `package.json` after cleanup
3. Rename targets:
   - `frontend-clean` → `node-frontend-clean`
   - `frontend-nuxt` → `node-frontend-nuxt`
   - `frontend-next` → `node-frontend-next`
   - `frontend-remix` → `node-frontend-remix`
   - `frontend-sveltekit` → `node-frontend-sveltekit`

**Files to modify:**
- `Makefile` (target implementations and dependencies)
- `src/node/frontend/package.json` (update help message in scripts)
- `documentation/development/MAKEFILE-REFERENCE.md`
- `documentation/development/FRONTEND-SCAFFOLDING.md`
- `.claude/LEARNINGS.md` (if referenced)

---

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

### Boilerplate Update Mechanism

**Status:** Planned
**Scope:** Medium
**Created:** 2026-01-20
**Context:** Users need a way to pull boilerplate updates into their projects

**Goal:** Add `make update` target to simplify pulling upstream boilerplate changes.

**Challenge:** Boilerplate updates are complex:

| File Type | Update Behavior |
|-----------|-----------------|
| `Makefile`, `docker/*`, `compose.*` | Should be updated |
| `.env`, `secrets/`, user code | Never overwrite |
| `composer.json`, `package.json` | Merge needed (user has own deps) |

**Recommended Approach:** Git-based (requires user to have upstream remote)

```makefile
update: ## Update boilerplate from upstream
    @git remote get-url upstream 2>/dev/null || \
        (echo "Adding upstream remote..." && git remote add upstream https://github.com/xxx/zappzarapp)
    @git fetch upstream
    @echo "Changes from upstream:"
    @git diff --stat HEAD upstream/main
    @echo ""
    @echo "To update, run: git merge upstream/main"
    @echo "Or for rebase: git rebase upstream/main"
```

**Additional Features to Consider:**
- `make update-check` — Show what would change (dry-run)
- `make update-docker` — Update only docker-related files
- Documentation for conflict resolution
- Warning about uncommitted changes before update

**Files to modify:**
- `Makefile` (new `update` target)
- `documentation/development/UPDATING.md` (new guide)
- `README.md` (mention update workflow)

---

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

Security vulnerability scanning split into independent subtasks for systematic implementation.

##### 1. ESLint Security Plugin (Node.js)

**Status:** Planned
**Scope:** Small
**Created:** 2026-01-20
**Context:** Quick win - minimal setup, immediate value

**Goal:** Add security-focused linting rules for Node.js code.

**What it finds:**
- `eval()` and `Function()` usage
- `child_process` with dynamic input
- Non-literal `require()` calls
- Regular Expression DoS (ReDoS)
- Unsafe object property access

**Implementation:**
1. `make pnpm CMD="add -D eslint-plugin-security"`
2. Add plugin to `eslint.config.js`
3. Run initial scan, fix or suppress findings
4. Add to `make check` pipeline

**Files to modify:**
- `package.json` (new dev dependency)
- `eslint.config.js` (add security plugin config)

**Resources:**
- https://www.npmjs.com/package/eslint-plugin-security

---

##### 2. Semgrep Integration (Multi-Language)

**Status:** Planned
**Scope:** Medium
**Created:** 2026-01-20
**Context:** Industry-standard SAST tool, covers both PHP and Node.js

**Goal:** Add Semgrep for comprehensive security scanning across all code.

**What it finds:**
- SQL Injection (string concatenation in queries)
- XSS (unescaped output)
- Command Injection
- Path Traversal
- Hardcoded Secrets
- Insecure Deserialization
- OWASP Top 10 patterns

**Implementation:**
1. Add `.semgrep.yml` with rule configuration
2. Create `make security-scan` target (runs via Docker or pip)
3. Configure rulesets: `p/security-audit`, `p/owasp-top-ten`
4. Add to CI pipeline (optional, can be slow)
5. Document suppression syntax for false positives

**Files to create/modify:**
- `.semgrep.yml` (rule configuration)
- `Makefile` (new `security-scan` target)
- `.gitlab-ci.yml` / `.github/workflows/` (optional CI integration)

**Resources:**
- https://semgrep.dev/docs/
- https://semgrep.dev/r (rule registry)

---

##### 3. Psalm Taint Analysis (PHP)

**Status:** Planned
**Scope:** Medium
**Created:** 2026-01-20
**Context:** Deep PHP-specific dataflow analysis, complements Semgrep

**Goal:** Enable Psalm's taint analysis for tracking untrusted data through PHP code.

**What it finds:**
- SQL Injection via tainted variables
- XSS via unescaped user input
- Command Injection via shell_exec/exec
- File inclusion vulnerabilities
- LDAP Injection
- Custom taint sources/sinks

**How it works:**
Tracks data flow from "sources" (user input) to "sinks" (dangerous functions).
More precise than pattern matching, fewer false positives.

**Implementation:**
1. `make composer CMD="require --dev vimeo/psalm"`
2. Create `psalm.xml` with taint analysis enabled
3. Add taint annotations to existing code (`@psalm-taint-source`, `@psalm-taint-sink`)
4. Create `make security-scan-php` target
5. Integrate with existing `make check` or separate `make security-check`

**Files to create/modify:**
- `composer.json` (new dev dependency)
- `psalm.xml` (Psalm configuration with taint analysis)
- `Makefile` (new `security-scan-php` target)

**Resources:**
- https://psalm.dev/docs/security_analysis/
- https://psalm.dev/docs/security_analysis/custom_taint_sources/

---

##### 4. Unified Security Scan Target

**Status:** Planned
**Scope:** Small
**Created:** 2026-01-20
**Context:** After subtasks 1-3 are complete

**Goal:** Create unified `make security-check` that runs all security tools.

**Implementation:**
```makefile
security-check: security-scan-node security-scan-php security-scan  ## Run all security scans
security-scan-node:    ## ESLint security plugin
security-scan-php:     ## Psalm taint analysis
security-scan:         ## Semgrep (all languages)
```

**Recommended workflow:**
- `make security-check` — Full scan (CI, pre-release)
- Individual targets for focused scanning during development

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

### Web Application Firewall (WAF) - Optional

**Status:** Planned
**Scope:** Large
**Created:** 2026-01-20
**Context:** Advanced security feature for production deployments with external traffic

**Goal:** Add optional WAF (ModSecurity + OWASP CRS) with consistent rules across Docker Compose and Kubernetes.

**Architecture:**

```
Development/Small Production (Docker Compose):
┌─────────────────────────────────────────────────────┐
│ ENABLE_WAF=false (default)                          │
│   Internet → Nginx:8080 → PHP/Node                  │
├─────────────────────────────────────────────────────┤
│ ENABLE_WAF=true                                     │
│   Internet → WAF:8080 → Nginx:80 (internal) → App   │
└─────────────────────────────────────────────────────┘

Production (Kubernetes):
┌─────────────────────────────────────────────────────┐
│ ModSecurity disabled (default)                      │
│   Internet → Ingress-NGINX → Service → Pod          │
├─────────────────────────────────────────────────────┤
│ ModSecurity enabled (annotation/ConfigMap)          │
│   Internet → Ingress-NGINX+WAF → Service → Pod      │
└─────────────────────────────────────────────────────┘
```

**Benefits:**
- Same OWASP CRS rules in both environments
- Resource-conscious: disable for small setups, enable for exposed production
- No extra container in Kubernetes (built into Ingress-NGINX)
- Protection against OWASP Top 10 (SQLi, XSS, LFI, RCE, etc.)

**Implementation:**

##### Part 1: Docker Compose Integration

1. Add WAF service with profile:
   ```yaml
   waf:
     image: owasp/modsecurity-crs:nginx-alpine
     profiles: ["waf"]
     environment:
       BACKEND: http://nginx:80
     ports:
       - "${NGINX_PORT:-8080}:8080"
       - "${NGINX_SSL_PORT:-8443}:8443"
     volumes:
       - ./docker/waf/modsecurity.conf:/etc/modsecurity.d/modsecurity-override.conf:ro
   ```

2. Conditional Nginx port binding (Makefile or entrypoint logic)
3. Add `ENABLE_WAF` to `.env` with documentation
4. Create `make up-waf` or use `COMPOSE_PROFILES=waf make up`

##### Part 2: Kubernetes Integration

1. Add Helm values for ModSecurity:
   ```yaml
   # values.yaml
   waf:
     enabled: false
     modsecurity:
       enabled: true
       owasp: true
   ```

2. Update Ingress template with conditional annotations:
   ```yaml
   {{- if .Values.waf.enabled }}
   nginx.ingress.kubernetes.io/enable-modsecurity: "true"
   nginx.ingress.kubernetes.io/enable-owasp-core-rules: "true"
   {{- end }}
   ```

3. Document ConfigMap option for cluster-wide enablement

##### Part 3: Shared Configuration

1. Create `docker/waf/` directory with:
   - `modsecurity.conf` (base config)
   - `crs-setup.conf` (OWASP CRS tuning)
   - `rules-exclusions.conf` (false positive suppressions)

2. Same rules usable in both environments

**Files to create/modify:**
- `compose.yaml` (new waf service with profile)
- `docker/waf/` (ModSecurity configuration)
- `.env` (ENABLE_WAF documentation)
- `Makefile` (conditional port logic, waf targets)
- `kubernetes/values.yaml` (waf.enabled)
- `kubernetes/templates/ingress.yaml` (ModSecurity annotations)
- `documentation/security/WAF.md` (setup guide)

**Resources:**
- https://github.com/coreruleset/modsecurity-crs-docker
- https://kubernetes.github.io/ingress-nginx/user-guide/third-party-addons/modsecurity/
- https://coreruleset.org/docs/

**Notes:**
- Not a v1.0 requirement — advanced feature for security-conscious deployments
- Requires tuning for false positives (application-specific)
- Consider "detection only" mode as safe default

---

### License Compliance Check

**Status:** Planned
**Scope:** Small
**Created:** 2026-01-20
**Context:** Important for commercial projects using open-source dependencies

**Goal:** Add automated license compliance checking for PHP and Node.js dependencies.

**Why it matters:**
- Detect GPL/AGPL licenses that may conflict with commercial use
- Identify unknown or problematic licenses
- Generate SBOM (Software Bill of Materials) for compliance audits

**Tools:**

| Tool | Language | Purpose |
|------|----------|---------|
| `license-checker` | Node.js | Scan pnpm dependencies |
| `composer licenses` | PHP | Built-in Composer command |
| `cyclonedx-php-composer` | PHP | SBOM generation (optional) |

**Implementation:**
1. `make pnpm CMD="add -D license-checker"`
2. Create `make license-check` target
3. Configure allowed/denied license list
4. Add to CI pipeline (optional, informational)

**Example Makefile target:**
```makefile
license-check: license-check-php license-check-node  ## Check dependency licenses
license-check-php:
	docker compose exec php composer licenses --format=json
license-check-node:
	docker compose exec node pnpm exec license-checker --summary
```

**Files to modify:**
- `package.json` (new dev dependency)
- `Makefile` (new targets)
- `.license-checker.json` (optional: allowed/denied lists)

**Resources:**
- https://www.npmjs.com/package/license-checker
- https://getcomposer.org/doc/03-cli.md#licenses

---

### GitHub Pages Documentation Site

**Status:** Planned
**Scope:** Medium
**Created:** 2026-01-20
**Context:** Professional documentation site for better discoverability and UX

**Goal:** Host documentation on GitHub Pages using Docsify.

**Why Docsify:**
- No build step (loads Markdown directly)
- `documentation/_sidebar.md` already exists
- Minimal setup, maximum benefit

**Directory structure:**
- `documentation/` = Boilerplate docs (manual, for GitHub Pages)
- `docs/` = Autogenerated code docs (for devs using the boilerplate)

**Implementation:**
1. Create `documentation/index.html` with Docsify setup
2. Configure GitHub Pages to serve from `documentation/`
3. Add search plugin, theme customization
4. **Important:** Remove "API Documentation" section from `_sidebar.md` (lines 46-49) — these are external links to `/docs/api/*` which is dev-autogenerated code docs, not part of the boilerplate documentation

**Files to modify/create:**
- `documentation/index.html` (Docsify entry point)
- `documentation/_sidebar.md` (remove API Documentation links)
- GitHub repository settings (enable Pages from `documentation/`)

**Notes:**
- Not a v1.0 blocker — good post-release enhancement
- Consider custom domain later (e.g., zappzarapp.dev)

---

## Completed

(Completed items are moved to CHANGELOG.md)
