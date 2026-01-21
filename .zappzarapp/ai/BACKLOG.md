# Project Backlog

Future tasks and improvements to be implemented.

## Task Scope Guide

Each task has a **Scope** indicator to help with session planning:

| Scope  | Time        | Files                   | Session                 |
| ------ | ----------- | ----------------------- | ----------------------- |
| Small  | <30 min     | 1-3 files               | Same session OK         |
| Medium | 30 min - 2h | 3-10 files              | Flexible                |
| Large  | >2h         | Many files, exploration | New session recommended |

---

## High Priority

### v1.0 Release Preparation

**Status:** Planned **Scope:** Large **Created:** 2026-01-19 **Context:**
Project approaching first public release, git history needs cleanup

**Goal:** Prepare clean v1.0 release with squashed git history and consolidated
changelog.

**Prerequisites (complete before release):**

- [ ] All critical bugs fixed
- [ ] All high-priority tasks completed (or deferred to v1.1)
- [ ] Full test suite passes (`make check`, `make goss-test-matrix`)
- [ ] Documentation complete and accurate
- [ ] **Opus Pre-Release Consistency Audit** (see below)

#### Opus Pre-Release Consistency Audit

Deep consistency analysis using Opus model in separate contexts per area. Each
subtask follows: **Discovery → User Approval → Deep Analysis**

| #   | Subtask             | Focus                               | Key Files                           |
| --- | ------------------- | ----------------------------------- | ----------------------------------- |
| 1   | Makefile Audit      | Targets, dependencies, docs sync    | `Makefile`, `MAKEFILE-REFERENCE.md` |
| 2   | Docker Audit        | Compose ↔ Dockerfiles ↔ Entrypoints | `docker/`, `compose.*`              |
| 3   | Environment Audit   | .env.example ↔ Docs ↔ Code usage    | All env references                  |
| 4   | Documentation Audit | Cross-refs, code examples, links    | `documentation/`                    |
| 5   | Claude Config Audit | Commands ↔ Targets, settings logic  | `.claude/`                          |
| 6   | Test Coverage Audit | Goss ↔ Docker, PHPUnit ↔ Code       | `tests/`                            |

**Workflow per subtask:**

1. Start fresh context with Opus (`--model opus`)
2. Opus analyzes area, identifies additional check points
3. User approves/adjusts scope
4. Opus performs systematic deep analysis
5. Findings documented, issues added to backlog or fixed

**Why separate contexts:**

- Fresh perspective per area (no bias from previous findings)
- Domain-specific focus
- Parallelizable if needed
- Easy to resume if interrupted

#### Subtask 7: Opus Fresh Clone Walkthrough (Final Pre-Release)

**Goal:** Complete end-to-end validation from a user's perspective before
release.

**Timing:** LAST task before release (after all other audits and fixes).

**What Opus tests:**

1. **Initial Setup** (README → GETTING-STARTED)
   - Fresh clone simulation (`make factory-reset` or clean environment)
   - `make setup` → `make up` → verify all containers healthy
   - All prerequisites documented and accurate

2. **First-Time User Experience**
   - Health endpoints respond correctly
   - DevDashboard accessible and functional
   - Basic configuration (.env) works as documented

3. **Feature Workflows**
   - `make db-migrate` executes successfully
   - Frontend scaffolding (`make node-frontend-nuxt` etc.)
   - Optional services activation (Redis, RabbitMQ, etc.)
   - All `COMPOSE_PROFILES` combinations work

4. **Development Workflow**
   - Code change → `make check` → passes
   - Test execution → `make test` → passes
   - Commit workflow with CaptainHook hooks

5. **Every Documented Example**
   - Execute ALL code examples from documentation
   - Verify outputs match descriptions
   - API examples, CLI examples, config examples

**Unique value (beyond automated tests):**

- Finds UX problems (confusing docs, missing steps)
- Discovers logical gaps (order of operations)
- Tests "soft" aspects (error messages, helpful output)
- Validates documentation accuracy holistically

**Output:**

- List of issues found (add to backlog or fix immediately)
- Confirmation that release is ready
- Optional: Suggestions for documentation improvements

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

- `.zappzarapp/CHANGELOG.md` (consolidate to v1.0 summary)
- `.zappzarapp/ai/BACKLOG.md` (clean up completed items)

**Notes:**

- Force-push required - coordinate if others have cloned
- Consider keeping old branch as `archive/pre-v1.0` for reference

---

### Makefile Target Testing with BATS + Goss Integration

**Status:** Planned **Scope:** Large **Created:** 2026-01-19 **Planning:**
Required **Context:** Feature request for environment-specific testing of Make
targets

**Goal:** Add automated tests for Makefile targets using BATS for command
execution and Goss for state validation.

**Before starting:** Use Plan Mode to analyze:

- Current Makefile structure and target dependencies
- Which targets are environment-sensitive (DB_TYPE, NODE_MODE, etc.)
- Existing Goss test structure and how to integrate
- CI/CD pipeline integration

**Tools:**

| Tool | Purpose                                          |
| ---- | ------------------------------------------------ |
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

#### Subtask: Documentation Workflow Tests (Doc-Parsing)

**Goal:** Ensure documented workflows actually work by parsing and executing
commands from documentation files.

**Approach:** Parse Markdown docs → extract shell commands → execute in test
environment → verify success.

**Why this matters:**

- **Single Source of Truth**: Documentation IS the test specification
- **No divergence**: Tests can't say X while docs say Y
- **Catches stale docs**: If a command changes, tests fail

**Test scenarios:**

1. **GETTING-STARTED.md**: Full setup workflow (clone → setup → up → health)
2. **README.md**: Quick start commands
3. **MAKEFILE-REFERENCE.md**: All documented make targets exist and work
4. **OPTIONAL-SERVICES.md**: Service activation workflows

**Implementation:**

```bash
# tests/bats/workflow/docs-getting-started.bats
load '../helpers/doc-parser.bash'

@test "GETTING-STARTED: all commands execute successfully" {
    commands=$(extract_shell_commands "documentation/GETTING-STARTED.md")
    for cmd in $commands; do
        # Skip interactive/dangerous commands
        [[ "$cmd" =~ ^(vim|nano|sudo) ]] && continue
        run bash -c "$cmd"
        [ "$status" -eq 0 ]
    done
}
```

**Files to create:**

- `tests/bats/helpers/doc-parser.bash` (command extraction helper)
- `tests/bats/workflow/docs-getting-started.bats`
- `tests/bats/workflow/docs-makefile-reference.bats`

---

### Service Integration Examples

**Status:** In Progress **Scope:** Large **Created:** 2026-01-19 **Context:**
Boilerplate should demonstrate best-practice integration patterns

**Goal:** Add production-ready example code for integrating all optional
services in both PHP and Node.js backends.

**Requirements:**

- Full test coverage (unit + integration tests)
- Security by design (input validation, prepared statements, secure defaults)
- Consistent error handling patterns
- Documentation with usage examples

**Services to integrate:**

| Service       | PHP                        | Node.js                    | Status      |
| ------------- | -------------------------- | -------------------------- | ----------- |
| Redis         | Session/Cache example      | Session/Cache example      | ✅ Complete |
| RabbitMQ      | Producer/Consumer example  | Producer/Consumer example  | ✅ Complete |
| PostgreSQL    | Repository pattern example | Repository pattern example | ✅ Complete |
| Meilisearch   | Search indexing example    | Search indexing example    | ✅ Complete |
| Elasticsearch | Search/Analytics example   | Search/Analytics example   | Planned     |
| SeaweedFS/S3  | File upload example        | File upload example        | Planned     |

**Completed (2026-01-20):** Redis Cache + Session services for PHP and Node.js
with Interface+Implementation pattern, full unit tests, TLS support.

**Completed (2026-01-20):** RabbitMQ Queue services for PHP and Node.js with
QueueInterface + RabbitMQQueue/QueueService implementation, full unit tests, TLS
support, Docker secrets integration.

**Completed (2026-01-21):** PostgreSQL/MariaDB Repository pattern for PHP and
Node.js with AbstractPdoRepository/AbstractRepository base classes,
UserRepository example, DatabaseConfigInterface, migrations for users table,
full unit tests.

**Completed (2026-01-21):** Meilisearch Search services for PHP and Node.js with
SearchInterface/SearchServiceInterface + MeilisearchSearch/SearchService
implementation, health check integration, full unit tests, TLS support.

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

#### Make Integrations in make setup einbinden

**Status:** Open **Scope:** chore **Size:** Small **Created:** 2026-01-21

**Task:** Integrate the `make integrations` target into the `make setup`
workflow so that service integrations are set up automatically during initial
project setup.

**Files to modify:**

- `Makefile`

---

#### Make Setup: Include API Documentation Generation

**Status:** Planned **Scope:** Small **Created:** 2026-01-21 **Context:**
Improve onboarding experience for new developers

**Task:** Add `make docs` to `make setup` so API documentation is immediately
available after initial setup.

**Benefit:** New developers have API docs from the start without extra steps.

**Files to modify:**

- `Makefile` (`setup` target)

---

#### IDE Tasks Reduction (PhpStorm & VSCode)

**Status:** Planned **Scope:** Small **Created:** 2026-01-21 **Context:** ~140
VSCode tasks and ~130 PhpStorm runConfigurations are overwhelming

**Goal:** Reduce to ~28 core tasks, identical in both IDEs (parity principle).

**Tasks to KEEP:**

| Category      | Tasks                                                           |
| ------------- | --------------------------------------------------------------- |
| Docker        | up, down, restart, build, status, check-health                  |
| Tests         | test, test-php, test-node, test-coverage                        |
| Quality       | check, analyse, cs-fix, lint-node-fix, prettier-fix, type-check |
| Node Dev      | node-dev, node-dev-full, node-build                             |
| Logs          | logs, logs-php, logs-node, logs-nginx                           |
| Shells        | shell-php, shell-node                                           |
| Composer/pnpm | composer-update, pnpm-install, pnpm-update                      |

**Tasks to REMOVE:**

| Category                                         | Reason                                      |
| ------------------------------------------------ | ------------------------------------------- |
| Setup (init, setup, hooks-install)               | One-time at project start                   |
| Backup-\* (12 tasks)                             | Complex ops, terminal better                |
| SSL-\* (7 tasks)                                 | Rare, sensitive                             |
| Security-\* (9 tasks)                            | CI/CD or terminal                           |
| Docs-\*                                          | Rarely manual                               |
| Service-specific Logs                            | Too granular (mariadb, elasticsearch, etc.) |
| Service-specific Shells                          | Too granular (mercure, meilisearch, etc.)   |
| Dangerous Ops (fresh, clean, prune, redis-flush) | Better conscious in terminal                |
| Renovate                                         | CI/CD                                       |
| Database CLI/Dump/Restore                        | Terminal better                             |
| Node PM2-_, Frontend-_, Server-\*                | Too specific                                |
| Redis CLI/Monitor                                | Terminal better                             |

**Files to modify:**

- `.vscode/tasks.json` (reduce from ~95 to ~28 tasks)
- `.idea/runConfigurations/*.xml` (delete ~100 files, keep ~28)

---

#### PhpStorm/VSCode Settings Sync

**Status:** Open **Scope:** chore **Size:** Small **Created:** 2026-01-21

**Task:** Final sync between PhpStorm and VSCode settings. PhpStorm is the
leading IDE for configuration - ensure VSCode has matching settings where
applicable.

**Steps:**

1. Compare `.idea/` settings with `.vscode/` equivalents
2. Identify missing VSCode settings
3. Add missing configurations to VSCode

**Files to check:**

- `.idea/*.xml` (source of truth)
- `.vscode/settings.json`
- `.vscode/extensions.json`

---

### Boilerplate Update Mechanism

**Status:** Planned **Scope:** Medium **Created:** 2026-01-20 **Context:** Users
need a way to pull boilerplate updates into their projects

**Goal:** Add `make update` target to simplify pulling upstream boilerplate
changes.

**Challenge:** Boilerplate updates are complex:

| File Type                           | Update Behavior                  |
| ----------------------------------- | -------------------------------- |
| `Makefile`, `docker/*`, `compose.*` | Should be updated                |
| `.env`, `secrets/`, user code       | Never overwrite                  |
| `composer.json`, `package.json`     | Merge needed (user has own deps) |

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

**Status:** Planned **Scope:** Medium **Created:** 2026-01-19 **Context:**
Continuation of code quality improvements from 2026-01-18

**Task:** Audit remaining `@SuppressWarnings` annotations and reduce class
complexity.

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

**Status:** Planned **Scope:** Medium **Created:** 2026-01-19 **Context:**
Current tests pass, but do they actually catch bugs?

**Goal:** Add mutation testing to verify test effectiveness.

**Tools:**

- **PHP:** Infection (<https://infection.github.io/>)
- **Node:** Stryker (<https://stryker-mutator.io/>)

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

#### Markdown Code Linting (Documentation Quality)

**Status:** Planned **Scope:** Medium **Created:** 2026-01-20 **Context:** Code
examples in Markdown files are not validated by existing linters

**Goal:** Lint code blocks in Markdown documentation using existing linter
configurations to ensure examples are syntactically correct and follow project
standards.

**Approach:** Extract code blocks by language tag → write to temp files → run
existing linters with existing configs.

**Languages to support:**

| Language              | Linter             | Config             |
| --------------------- | ------------------ | ------------------ |
| TypeScript/JavaScript | ESLint             | `eslint.config.js` |
| PHP                   | `php -l` + PHPStan | `phpstan.neon`     |
| SQL                   | sqlfluff           | `.sqlfluff` (new)  |
| Bash/Shell            | shellcheck         | (default rules)    |

**Implementation:**

1. Create `scripts/lint-markdown-code.sh` extraction script
2. Add sqlfluff configuration (`.sqlfluff`) for PostgreSQL + MariaDB dialects
3. Add shellcheck for bash code blocks
4. Create `make lint-docs-code` target
5. Integrate into `make check` pipeline
6. Document in MAKEFILE-REFERENCE.md

**Integration points (don't forget!):**

- `Makefile`: new `lint-docs-code` target
- `make check`: add `lint-docs-code` to pipeline
- `.sqlfluff`: new config file (PostgreSQL default, MariaDB support)
- `package.json`: shellcheck if not available system-wide
- `documentation/development/MAKEFILE-REFERENCE.md`: document new target

**Files to create:**

- `scripts/lint-markdown-code.sh`
- `.sqlfluff`

**Files to modify:**

- `Makefile`
- `documentation/development/MAKEFILE-REFERENCE.md`

**Dependencies:**

- sqlfluff (Python, install via pip or Docker)
- shellcheck (system package or Docker)

---

#### Security Static Analysis (SAST)

Security vulnerability scanning split into independent subtasks for systematic
implementation.

##### 1. ESLint Security Plugin (Node.js)

**Status:** Planned **Scope:** Small **Created:** 2026-01-20 **Context:** Quick
win - minimal setup, immediate value

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

- <https://www.npmjs.com/package/eslint-plugin-security>

---

##### 2. Semgrep Integration (Multi-Language)

**Status:** Planned **Scope:** Medium **Created:** 2026-01-20 **Context:**
Industry-standard SAST tool, covers both PHP and Node.js

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

- <https://semgrep.dev/docs/>
- <https://semgrep.dev/r> (rule registry)

---

##### 3. Psalm Taint Analysis (PHP)

**Status:** Planned **Scope:** Medium **Created:** 2026-01-20 **Context:** Deep
PHP-specific dataflow analysis, complements Semgrep

**Goal:** Enable Psalm's taint analysis for tracking untrusted data through PHP
code.

**What it finds:**

- SQL Injection via tainted variables
- XSS via unescaped user input
- Command Injection via shell_exec/exec
- File inclusion vulnerabilities
- LDAP Injection
- Custom taint sources/sinks

**How it works:** Tracks data flow from "sources" (user input) to "sinks"
(dangerous functions). More precise than pattern matching, fewer false
positives.

**Implementation:**

1. `make composer CMD="require --dev vimeo/psalm"`
2. Create `psalm.xml` with taint analysis enabled
3. Add taint annotations to existing code (`@psalm-taint-source`,
   `@psalm-taint-sink`)
4. Create `make security-scan-php` target
5. Integrate with existing `make check` or separate `make security-check`

**Files to create/modify:**

- `composer.json` (new dev dependency)
- `psalm.xml` (Psalm configuration with taint analysis)
- `Makefile` (new `security-scan-php` target)

**Resources:**

- <https://psalm.dev/docs/security_analysis/>
- <https://psalm.dev/docs/security_analysis/custom_taint_sources/>

---

##### 4. Unified Security Scan Target

**Status:** Planned **Scope:** Small **Created:** 2026-01-20 **Context:** After
subtasks 1-3 are complete

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

**Status:** Planned **Scope:** Medium **Created:** 2026-01-19 **Context:** User
question about separate certs for frontend/backend services

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

#### Shell Script Linting (shellcheck)

**Status:** Planned **Scope:** Small **Created:** 2026-01-20 **Context:** 20+
shell scripts in `docker/` directory are not validated

**Goal:** Add shellcheck validation for all shell scripts to catch common
errors.

**What shellcheck finds:**

- Unquoted variables (`$var` vs `"$var"`)
- Deprecated syntax, bashisms in sh scripts
- Missing error handling (`set -e`, `set -u`)
- Subshell pitfalls, word splitting issues
- SC2086, SC2046, and other common issues

**Scripts to lint:**

- `docker/*/entrypoint*.sh`
- `docker/*/healthcheck.sh`
- `docker/scripts/*.sh`
- `docker/certs/*.sh`
- `docker/hooks/*.sh`

**Implementation:**

1. Add `make lint-shell` target (via Docker: `koalaman/shellcheck`)
2. Create `.shellcheckrc` for project-wide config
3. Integrate into `make check` pipeline
4. Document in MAKEFILE-REFERENCE.md

**Files to create/modify:**

- `.shellcheckrc` (shellcheck configuration)
- `Makefile` (new `lint-shell` target, add to `check`)
- `documentation/development/MAKEFILE-REFERENCE.md`

---

#### Docker Compose Validation

**Status:** Planned **Scope:** Small **Created:** 2026-01-20 **Context:**
Complex compose.yaml files should be validated before `make up`

**Goal:** Add `make compose-validate` to catch syntax errors early.

**What it validates:**

- YAML syntax errors
- Invalid service configurations
- Missing required fields
- Environment variable interpolation
- Profile configuration

**Implementation:**

```makefile
compose-validate: ## Validate Docker Compose configuration
    @docker compose config --quiet && echo "✓ compose.yaml valid"
    @docker compose -f compose.production.yaml config --quiet && echo "✓ compose.production.yaml valid"
```

**Integration:**

- Add to `make check` pipeline
- Run before `make up` (optional, adds latency)
- Document validation in troubleshooting guide

**Files to modify:**

- `Makefile`
- `documentation/development/MAKEFILE-REFERENCE.md`

---

#### Helm Chart Linting

**Status:** Planned **Scope:** Small **Created:** 2026-01-20 **Context:**
Kubernetes Helm charts in `kubernetes/` should be validated

**Goal:** Add `make helm-lint` to validate Helm chart syntax and best practices.

**What it validates:**

- Chart.yaml validity
- Template syntax (Go templates)
- Values.yaml structure
- Kubernetes manifest validity
- Best practices (labels, resources, etc.)

**Implementation:**

```makefile
helm-lint: ## Lint Helm charts
    @helm lint kubernetes/
    @helm template kubernetes/ --dry-run > /dev/null && echo "✓ Templates render successfully"
```

**Additional checks:**

- `helm template --debug` for detailed output
- `kubeval` or `kubeconform` for K8s schema validation (optional)

**Files to modify:**

- `Makefile`
- `documentation/development/MAKEFILE-REFERENCE.md`

---

### UI/UX

#### Page Design Customization

**Status:** Planned **Scope:** Medium **Created:** 2026-01-19

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

**Status:** Planned **Scope:** Large **Created:** 2026-01-19

**Task:** Evaluate Storybook or similar for component testing and accessibility.

**Benefits:**

- Visual component documentation
- Isolated component development
- Accessibility testing (color contrast, WCAG)
- Screenshot testing for visual regression

**Tool Comparison:**

| Tool          | Vite Support           | Bundle Size   | DX               | Best For                    |
| ------------- | ---------------------- | ------------- | ---------------- | --------------------------- |
| **Storybook** | ✅ Native              | Large (~20MB) | ⭐⭐⭐ Ecosystem | Established projects, teams |
| **Histoire**  | ✅ Native (Vite-first) | Small (~2MB)  | ⭐⭐⭐⭐ Fast    | Vue/Svelte, Vite-native     |
| **Ladle**     | ✅ Native              | Tiny (~1MB)   | ⭐⭐⭐ Minimal   | React, minimal footprint    |

**Storybook Pros:**

- Largest ecosystem (addons, integrations)
- Best documentation, community support
- Works with any framework
- Chromatic for visual regression (paid)

**Storybook Cons:**

- Heavyweight, slow startup
- Complex configuration
- Overkill for small projects

**Histoire Pros:**

- Built for Vite (instant HMR)
- Vue/Svelte first-class support
- Much smaller bundle
- Markdown stories support

**Histoire Cons:**

- Smaller ecosystem
- Less React support
- Fewer addons

**Ladle Pros:**

- Fastest, smallest
- Zero-config for React
- Compatible with Storybook stories (MDX)

**Ladle Cons:**

- React only
- Minimal features
- No addons ecosystem

**Recommendation:**

- **Vue/Svelte project → Histoire** (Vite-native, fast)
- **React project → Ladle** (minimal) or **Storybook** (full-featured)
- **Unknown/Mixed → Storybook** (most flexible)

**Questions to answer:**

- Which frontend framework will be used?
- How important is visual regression testing?
- CI/CD integration requirements?

---

### Future Ideas (Brainstorm)

#### DevDashboard Feature Ideas

**Status:** Brainstorm **Created:** 2026-01-19

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

**Status:** Planned **Scope:** Large **Created:** 2026-01-19 **Context:**
Feature request for DevDashboard

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

### Refactor DatabaseConfig to Reduce Complexity

**Status:** Planned **Scope:** Small **Created:** 2026-01-21 **Context:** PHPMD
reports ExcessiveClassComplexity

**Task:** Refactor `DatabaseConfig` to reduce class complexity. Currently
suppressed with `@SuppressWarnings("PHPMD.ExcessiveClassComplexity")`.

**Potential approaches:**

- Extract SSL configuration logic into separate `SslConfig` class
- Extract URL parsing into separate `DatabaseUrlParser` class
- Split environment variable handling into trait or helper

**Files:**

- `src/php/App/Infrastructure/DatabaseConfig.php`

---

### DevDashboard: Code Examples Integration

**Status:** Planned **Scope:** Medium **Created:** 2026-01-21 **Context:** Show
usage examples for integrated services (Redis, RabbitMQ, DB, etc.)

**Task:** Integrate code examples into DevDashboard. Decision needed on where.

**Options to discuss:**

| Option                     | Description                | Pro                           | Contra                     |
| -------------------------- | -------------------------- | ----------------------------- | -------------------------- |
| A: Welcome-Seite erweitern | Examples direkt in Welcome | Alles an einem Ort            | Seite wird lang            |
| B: Eigene Unterseite       | Neue Seite `/dev/examples` | Saubere Trennung, erweiterbar | Extra Navigation           |
| C: Tabs auf Welcome        | Examples als Tab           | Kompakt, schneller Zugriff    | Komplexere UI, JS nötig    |
| D: Collapsible Sections    | Ausklappbare Code-Blöcke   | Platzsparend                  | Unübersichtlich bei vielen |

**Empfehlung:** Option B (eigene Unterseite) - Welcome bleibt "Quick Overview",
Examples-Seite kann pro Service strukturiert werden (PHP + Node.js
nebeneinander).

**Files to modify (depends on option):**

- Option A/C/D: `templates/dev-dashboard/welcome.php`
- Option B: New `src/php/DevDashboard/Controller/ExamplesController.php`, new
  `templates/dev-dashboard/examples.php`

---

### Missing API Documentation Warnings

**Status:** Planned **Scope:** Small **Created:** 2026-01-21 **Context:**
Improve developer experience when API docs are not yet generated

**Goal:** Show warnings/hints when API documentation is missing, guiding users
to run `make docs` (or `make docs-php` / `make docs-node`).

#### Subtask 1: DevDashboard Welcome Page

**Location:** DevDashboard (`/dev/`)

**Task:** In the "Documentation" category, show warning when docs not generated.

**Implementation:**

1. Check if `docs/api/php/` and `docs/api/node/` exist
2. Display warning banner if missing
3. Show appropriate make command based on what's missing

**Files:**

- `src/php/DevDashboard/Controller/WelcomeController.php`
- `templates/dev-dashboard/welcome.php`

#### Subtask 2: Nginx /docs Page

**Location:** Static docs page (`/docs/`)

**Task:** Hide or warn about API Documentation links when docs not generated.

**Options:**

- A: Hide links to non-existent docs entirely
- B: Show links with warning icon/text
- C: Redirect to info page explaining how to generate

**Implementation:**

1. Check if `docs/api/php/index.html` and `docs/api/node/index.html` exist
2. Conditionally show/hide or annotate links

**Files:**

- Depends on how `/docs` is served (static HTML or PHP template)

---

### Make Reset: IDE-Generated Files Cleanup

**Status:** Planned **Scope:** Small **Created:** 2026-01-21 **Context:**
Inconsistency between `make reset` and IDE credentials

**Problem:** `make reset` deletes `secrets/` but IDE-generated files with cached
DB credentials remain. This is inconsistent for a "factory reset".

**Files affected (gitignored, locally generated):**

| File                          | Content                     |
| ----------------------------- | --------------------------- |
| `.idea/dataSources.local.xml` | DB passwords/credentials    |
| `.idea/dataSources/`          | Schema cache, introspection |
| `.idea/sshConfigs.xml`        | SSH tunnel configurations   |

**Not affected (committed templates):**

- `.idea/dataSources.xml` — DB connection templates without passwords

**Implementation:**

1. Add IDE cleanup to `make reset`:

   ```bash
   rm -f .idea/dataSources.local.xml
   rm -rf .idea/dataSources/
   rm -f .idea/sshConfigs.xml
   ```

2. Optional: Create separate `make ide-clean` target for IDE-only reset

**Files to modify:**

- `Makefile` (`reset` target, optional `ide-clean` target)

---

### Frontend Quality Testing Research

**Status:** Planned **Scope:** Small **Created:** 2026-01-20 **Context:**
Research task to evaluate additional frontend quality tools for future
implementation

**Goal:** Research and document tools for accessibility, performance, and visual
regression testing to inform future decisions.

**Areas to research:**

#### 1. Accessibility Testing (a11y)

| Tool           | Type              | Integration                           |
| -------------- | ----------------- | ------------------------------------- |
| **axe-core**   | Runtime/CI        | Jest, Playwright, Storybook addon     |
| **pa11y**      | CLI/CI            | Standalone, CI pipelines              |
| **Lighthouse** | Browser/CI        | Chrome DevTools, CI via lighthouse-ci |
| **WAVE**       | Browser extension | Manual testing                        |

**Questions:**

- Which level of WCAG compliance is needed (A, AA, AAA)?
- Automated vs. manual testing balance?
- Integration with chosen component library?

#### 2. Performance Testing

| Tool           | Type         | Best For                    |
| -------------- | ------------ | --------------------------- |
| **Lighthouse** | Synthetic    | Core Web Vitals, SEO, a11y  |
| **k6**         | Load testing | API endpoints, stress tests |
| **Artillery**  | Load testing | HTTP, WebSocket, scenarios  |
| **Web Vitals** | RUM          | Real user metrics           |

**Questions:**

- API load testing vs. frontend performance?
- Synthetic vs. real user monitoring?
- CI/CD integration (performance budgets)?

#### 3. Visual Regression Testing

| Tool           | Type             | Cost             |
| -------------- | ---------------- | ---------------- |
| **Playwright** | Screenshots      | Free, built-in   |
| **Percy**      | Cloud comparison | Paid (free tier) |
| **Chromatic**  | Storybook-native | Paid (free tier) |
| **BackstopJS** | Self-hosted      | Free             |
| **reg-suit**   | Self-hosted      | Free             |

**Questions:**

- Self-hosted vs. cloud service?
- Storybook integration needed?
- How many snapshots/components?

#### 4. E2E/Integration Testing

| Tool           | Language | Best For              |
| -------------- | -------- | --------------------- |
| **Playwright** | JS/TS    | Cross-browser, modern |
| **Cypress**    | JS/TS    | Developer experience  |
| **Puppeteer**  | JS/TS    | Chrome-specific       |

**Note:** See also "Frontend Testing Framework (Storybook)" task in UI/UX
section for component-level testing.

**Deliverable:** Update this task with findings and recommendations after
research, then create specific implementation tasks as needed.

---

### Make Help Autocompletion (direnv)

**Status:** Planned **Scope:** Small **Created:** 2026-01-20 **Context:**
`make help FILTER=` parameter exists but lacks shell autocompletion

**Goal:** Add bash/zsh completion for `make help FILTER=<TAB>` that suggests
available categories.

**Current state:**

- `make help FILTER=docker` works
- Categories shown at end of filtered output
- No autocompletion for FILTER values

**Implementation:**

```bash
# scripts/make-completion.bash
_make_zappzarapp_help() {
    local cur="${COMP_WORDS[COMP_CWORD]}"
    if [[ "$cur" == FILTER=* ]]; then
        local filter_val="${cur#FILTER=}"
        local categories=$(grep -oP '(?<=^##@ ).*' Makefile | tr '[:upper:]' '[:lower:]' | tr ' ' '-')
        COMPREPLY=($(compgen -P "FILTER=" -W "$categories" -- "$filter_val"))
    fi
}
```

**direnv integration (.envrc):**

```bash
source_up_if_exists
source scripts/make-completion.bash
```

**Files to create:**

- `scripts/make-completion.bash`
- `.envrc` (or update existing)

**Notes:**

- Must be project-local (not override global make completion)
- direnv ensures completion loads/unloads on directory change
- Document in CONTRIBUTING.md or GETTING-STARTED.md

---

### Database Schema Diff / Advanced Migrations

**Status:** Planned **Scope:** Large **Planning:** Required **Created:**
2026-01-19 **Context:** Feature request for automated schema diff generation

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

| Tool   | PostgreSQL | MariaDB | Notes                   |
| ------ | ---------- | ------- | ----------------------- |
| Atlas  | ✅         | ✅      | Go, declarative, modern |
| Flyway | ✅         | ✅      | Java, widely adopted    |
| Skeema | ❌         | ✅      | MySQL/MariaDB only      |
| migra  | ✅         | ❌      | Python, PostgreSQL only |

**Notes:**

- Current simple SQL-file approach may be sufficient for most users
- This is an enhancement for larger projects
- Keep backwards compatibility with existing `migrations/` structure

---

### Web Application Firewall (WAF) - Optional

**Status:** Planned **Scope:** Large **Created:** 2026-01-20 **Context:**
Advanced security feature for production deployments with external traffic

**Goal:** Add optional WAF (ModSecurity + OWASP CRS) with consistent rules
across Docker Compose and Kubernetes.

**Architecture:**

```text
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

#### Part 1: Docker Compose Integration

1. Add WAF service with profile:

   ```yaml
   waf:
     image: owasp/modsecurity-crs:nginx-alpine
     profiles: ['waf']
     environment:
       BACKEND: http://nginx:80
     ports:
       - '${NGINX_PORT:-8080}:8080'
       - '${NGINX_SSL_PORT:-8443}:8443'
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

- <https://github.com/coreruleset/modsecurity-crs-docker>
- <https://kubernetes.github.io/ingress-nginx/user-guide/third-party-addons/modsecurity/>
- <https://coreruleset.org/docs/>

**Notes:**

- Not a v1.0 requirement — advanced feature for security-conscious deployments
- Requires tuning for false positives (application-specific)
- Consider "detection only" mode as safe default

---

### License Compliance Check

**Status:** Planned **Scope:** Small **Created:** 2026-01-20 **Context:**
Important for commercial projects using open-source dependencies

**Goal:** Add automated license compliance checking for PHP and Node.js
dependencies.

**Why it matters:**

- Detect GPL/AGPL licenses that may conflict with commercial use
- Identify unknown or problematic licenses
- Generate SBOM (Software Bill of Materials) for compliance audits

**Tools:**

| Tool                     | Language | Purpose                    |
| ------------------------ | -------- | -------------------------- |
| `license-checker`        | Node.js  | Scan pnpm dependencies     |
| `composer licenses`      | PHP      | Built-in Composer command  |
| `cyclonedx-php-composer` | PHP      | SBOM generation (optional) |

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

- <https://www.npmjs.com/package/license-checker>
- <https://getcomposer.org/doc/03-cli.md#licenses>

---

### GitHub Pages Documentation Site

**Status:** Planned **Scope:** Medium **Created:** 2026-01-20 **Context:**
Professional documentation site for better discoverability and UX

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
4. **Important:** Remove "API Documentation" section from `_sidebar.md` (lines
   46-49) — these are external links to `/docs/api/*` which is dev-autogenerated
   code docs, not part of the boilerplate documentation

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
