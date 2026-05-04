# Security Scanning Guide

> GDPR Art. 32 Compliance: Continuous Security Assessment

---

## Overview

This project includes automated security scanning via GitHub Actions to identify
vulnerabilities in:

- Docker images (OS packages, libraries)
- Application dependencies (Composer, pnpm)
- Filesystem (misconfigurations, vulnerable files)
- Secrets (leaked credentials, API keys)

---

## CI/CD Workflows

This project provides security scanning workflows for both GitHub Actions and
GitLab CI/CD.

| Platform | Main Pipeline              | Security Scan                         | ZAP Scan                         |
| -------- | -------------------------- | ------------------------------------- | -------------------------------- |
| GitHub   | `.github/workflows/ci.yml` | `.github/workflows/security-scan.yml` | `.github/workflows/zap-scan.yml` |
| GitLab   | `.gitlab-ci.yml`           | `.gitlab/security-scan.gitlab-ci.yml` | Included in security-scan        |

---

## GitHub Workflow

**Location:** `.github/workflows/security-scan.yml`

### Triggers

| Trigger  | Description                                    |
| -------- | ---------------------------------------------- |
| Schedule | Weekly (Sunday 2 AM UTC)                       |
| Manual   | `workflow_dispatch` with configurable severity |
| Push     | On changes to Docker/dependency files          |

### Scan Types

#### 1. Docker Image Scan

Scans all Docker images for vulnerabilities:

- PHP
- Node.js
- Nginx
- PostgreSQL
- MariaDB

Uses [Trivy](https://trivy.dev/) to detect:

- OS package vulnerabilities (CVEs)
- Language-specific vulnerabilities
- Misconfigurations

#### 2. Dependency Scan

Audits application dependencies:

- `composer audit` for PHP packages
- `pnpm audit` for Node.js packages

Results uploaded as artifacts for review.

#### 3. Filesystem Scan

Scans the repository for:

- Vulnerable dependencies in lock files
- Infrastructure as Code (IaC) misconfigurations
- Dockerfile best practice violations

#### 4. Secret Scan

Detects accidentally committed secrets:

- API keys
- Passwords
- Private keys
- Tokens

Uses both Trivy and [TruffleHog](https://github.com/trufflesecurity/trufflehog)
for comprehensive detection.

---

## Viewing Results

### GitHub Security Tab

SARIF results are uploaded to GitHub's Security tab:

1. Navigate to your repository
2. Click "Security" tab
3. Select "Code scanning alerts"

### Workflow Summary

Each run generates a summary with pass/fail status for each scan type.

### Artifacts

Dependency audit results are available as downloadable artifacts (retained 30
days).

---

## GitLab CI/CD

**Location:** `.gitlab/security-scan.gitlab-ci.yml`

### Setup Options

#### Option 1: Include in Main Pipeline

```yaml
# .gitlab-ci.yml
include:
  - local: '.gitlab/security-scan.gitlab-ci.yml'
```

#### Option 2: Scheduled Pipeline (Recommended)

1. Go to **CI/CD > Schedules > New schedule**
2. Set cron expression: `0 2 * * 0` (Weekly Sunday 2 AM)
3. Target branch: `master`
4. Optional variables: `SEVERITY: HIGH,CRITICAL`

### Viewing Results

- **Job artifacts:** Download JSON reports from job details
- **Security summary:** Generated `security-summary.md` in report stage
- **Container scanning reports:** Integrated with GitLab's Security Dashboard
  (Ultimate tier)

### Key Differences from GitHub

| Feature            | GitHub              | GitLab                     |
| ------------------ | ------------------- | -------------------------- |
| Secret scanning    | TruffleHog          | Gitleaks                   |
| SARIF upload       | GitHub Security tab | Artifacts                  |
| Security dashboard | Code scanning       | Container scanning reports |

---

## Severity Levels

| Level    | Description                          | Action              |
| -------- | ------------------------------------ | ------------------- |
| CRITICAL | Actively exploited, easy to exploit  | Fix immediately     |
| HIGH     | Significant risk, likely exploitable | Fix within days     |
| MEDIUM   | Moderate risk, harder to exploit     | Fix within sprint   |
| LOW      | Minor risk, unlikely to exploit      | Fix when convenient |

**Default threshold:** HIGH and CRITICAL

### Customizing Severity

Manual runs allow selecting the minimum severity:

```yaml
# Via GitHub UI: Actions > Security Scan > Run workflow
# Select severity from dropdown
```

---

## Local Scanning

### Install Trivy

```bash
# macOS
brew install trivy

# Ubuntu/Debian
sudo apt-get install trivy

# Docker
docker pull aquasec/trivy
```

### Scan Docker Images

```bash
# Build images first
make build

# Scan specific image
trivy image zappzarapp-php:latest

# Scan with severity filter
trivy image --severity HIGH,CRITICAL zappzarapp-php:latest

# Scan ignoring unfixed vulnerabilities
trivy image --ignore-unfixed zappzarapp-php:latest
```

### Scan Filesystem

```bash
# Scan current directory
trivy fs .

# Scan for secrets
trivy fs --scanners secret .

# Scan IaC configurations
trivy config .
```

### Scan Dependencies

```bash
# PHP
docker compose exec php composer audit

# Node.js
docker compose exec node pnpm audit
```

---

## Ignoring False Positives

### Trivy Ignore File

Create `.trivyignore` in project root:

```text
# Ignore specific CVE (with reason)
CVE-2023-12345  # False positive: not exploitable in our context

# Ignore by package
pkg:npm/example-package@1.0.0
```

### Inline Suppression

For Dockerfile misconfigurations:

```dockerfile
# trivy:ignore:DS002
USER root  # Required for specific operation
```

---

## Best Practices

### 1. Regular Updates

```bash
# Update base images regularly
docker pull php:8.3-fpm-alpine
docker pull node:22-alpine
docker pull nginx:alpine
docker pull postgres:16-alpine
docker pull mariadb:11-ubi

# Rebuild images
make build
```

### 2. Dependency Updates

```bash
# PHP: Update within constraints
docker compose exec php composer update

# Node.js: Update within constraints
docker compose exec node pnpm update

# Check for outdated packages
docker compose exec php composer outdated
docker compose exec node pnpm outdated
```

### 3. Pin Versions

In Dockerfiles, prefer specific versions:

```dockerfile
# Good: Specific version
FROM php:8.3.1-fpm-alpine3.19

# Acceptable: Minor version pinning
FROM php:8.3-fpm-alpine

# Avoid: Latest tag
FROM php:latest
```

### 4. Multi-Stage Builds

Keep production images minimal:

```dockerfile
# Build stage with dev dependencies
FROM node:22-alpine AS builder
RUN pnpm install
RUN pnpm build

# Production stage - minimal
FROM node:22-alpine AS production
COPY --from=builder /app/dist /app/dist
```

### 5. Non-Root Users

Run containers as non-root:

```dockerfile
# Create non-root user
RUN adduser -D -u 1000 appuser
USER appuser
```

---

## Integration with CI/CD

The main CI pipeline (`.github/workflows/ci.yml`) includes basic security scans.
The dedicated security scan workflow provides:

- **More comprehensive scanning** (all images, not just PHP/Nginx)
- **Scheduled runs** (catch new CVEs in existing images)
- **Secret scanning** (not in main CI)
- **Configurable severity** (manual runs)

### Recommended Setup

1. **CI Pipeline:** Quick scans on every PR (existing)
2. **Security Scan:** Comprehensive weekly scans (this workflow)
3. **Dependabot:** Automated dependency PRs (optional, see below)

---

## Optional: Dependabot

Enable automated dependency updates:

```yaml
# .github/dependabot.yml
version: 2
updates:
  # PHP Composer
  - package-ecosystem: composer
    directory: /
    schedule:
      interval: weekly
    open-pull-requests-limit: 5

  # Node.js pnpm
  - package-ecosystem: npm
    directory: /
    schedule:
      interval: weekly
    open-pull-requests-limit: 5

  # Docker
  - package-ecosystem: docker
    directory: /docker/php
    schedule:
      interval: weekly

  # GitHub Actions
  - package-ecosystem: github-actions
    directory: /
    schedule:
      interval: weekly
```

---

## OWASP ZAP DAST Scan

Dynamic Application Security Testing (DAST) is performed using OWASP ZAP to
identify runtime security issues like CSP violations, missing headers, and
authentication flaws.

### GitHub Workflow

**Location:** `.github/workflows/zap-scan.yml`

**Triggers:**

- Push to `develop` branch
- Weekly schedule (Sunday 3 AM UTC, after Trivy scans)
- Manual via `workflow_dispatch`

**Key Configuration:**

- Uses `make security-zap-start` for environment setup (respects `ENABLE_*` and
  `NODE_MODE` from `.env`)
- Uses `zaproxy/action-baseline@v0.14.0` for scanning (GitHub-optimized)
- Uses `make security-zap-stop` for cleanup
- Runs against production configuration (strict CSP without
  unsafe-eval/unsafe-inline)
- Uses `.zap/rules.tsv` for custom rules
- Generates HTML and JSON reports (retained 30 days)

**Implementation:**

```yaml
- name: Start application in production mode
  run: make security-zap-start

- name: Run ZAP baseline scan
  uses: zaproxy/action-baseline@v0.14.0

- name: Stop application
  if: always()
  run: make security-zap-stop
```

### GitLab CI Pipeline

**Location:** `.gitlab/security-scan.gitlab-ci.yml`

**Integration:** The ZAP scan is part of the comprehensive security scan
pipeline and uses Make targets for consistency with local development:

```yaml
scan:zap:
  stage: scan
  script:
    - make security-zap-start # Setup (uses production config)
    - make security-zap-scan # Scan
  after_script:
    - make security-zap-stop # Cleanup (runs always)
```

**Viewing Results:**

- Job artifacts contain `zap-report.html` and `zap-report.json`
- Security summary includes ZAP scan status

**Benefits of Make-Target Integration:**

- ✅ DRY: No code duplication between local/CI environments
- ✅ Consistency: Identical behavior locally and in CI/CD
- ✅ Maintainability: Changes only needed in Makefile

### Local ZAP Scan

**IMPORTANT:** Always run ZAP scans against **production configuration** to test
strict CSP headers without `unsafe-eval` and `unsafe-inline`.

#### Quick Scan (All-in-One)

```bash
# Standard scan: respects .env configuration
make security-zap

# Comprehensive scan: tests ALL services (ignores .env ENABLE_* flags)
make security-zap-full
```

**Which to use?**

- **Project development**: `make security-zap` (tests your configured stack)
- **Before boilerplate release**: `make security-zap-full` (validates all
  services with maximum attack surface)
- **Custom projects**: Usually `make security-zap` is sufficient

**Note:** Full mode always tests **both** Node.js services (frontend + backend
API) regardless of your `NODE_MODE` setting, ensuring complete platform security
validation.

**Note:** No need to specify `ENV=production` - it's automatically set by the
targets to ensure production CSP is always tested.

#### Manual Workflow

**Standard mode (.env configuration):**

```bash
# 1. Start services based on .env ENABLE_* flags
make security-zap-start

# 2. Run scan (can be repeated without restarting)
make security-zap-scan

# 3. Stop services when done
make security-zap-stop
```

**Full mode (all services):**

```bash
# 1. Start ALL services (ignores .env ENABLE_* flags)
make security-zap-full-start

# 2. Run scan (can be repeated without restarting)
make security-zap-scan

# 3. Stop services when done
make security-zap-stop
```

**How it works:**

- `security-zap-start` uses `docker compose -f compose.production.yaml` directly
- Sets `ENV=production` explicitly before starting containers
- This ensures PHP container receives correct ENV for strict CSP headers
- Reads `ENABLE_*` flags from `.env` to determine which services to start

**Scan Modes:**

**`security-zap` (Standard):**

- Respects `.env` ENABLE\_\* configuration
- Tests only services you have enabled
- Use Case: Project development, custom stack testing

**`security-zap-full` (Comprehensive):**

- Tests ALL available services regardless of `.env`
- Forces: PHP, Database, Redis, **both Node.js services** (frontend + backend
  API), Mercure, Meilisearch, Elasticsearch, SeaweedFS
- Use Case: Boilerplate releases, platform security validation
- **Maximum attack surface**: Always tests both Node.js profiles for complete
  coverage

**Services Started (by mode):**

| Service           | Normal Mode (`.env`)   | Full Mode (forced)                            |
| ----------------- | ---------------------- | --------------------------------------------- |
| **nginx**         | Always (no profile)    | Always (no profile)                           |
| **PHP**           | `ENABLE_PHP=true`      | Always                                        |
| **Database**      | `ENABLE_DATABASE`      | Always (via DB_TYPE, one of postgres/mariadb) |
| **Redis**         | `ENABLE_REDIS=true`    | Always                                        |
| **Node.js**       | `ENABLE_NODE=true`     | **Always (both node + node-backend)**         |
| **Mercure**       | `ENABLE_MERCURE`       | Always                                        |
| **Meilisearch**   | `ENABLE_MEILISEARCH`   | Always                                        |
| **Elasticsearch** | `ENABLE_ELASTICSEARCH` | Always                                        |
| **SeaweedFS**     | `ENABLE_SEAWEEDFS`     | Always                                        |
| RabbitMQ/Mailpit  | Never (not in scope)   | Never                                         |

**Node.js Profile Selection:**

**Normal Mode:** Determined by `NODE_MODE` from `.env`:

- `assets|idle` → `--profile node` (Vite dev server)
- `api` → `--profile node-backend` (Express API only)
- `assets-api` → `--profile node --profile node-backend` (both, default)
- `framework` → `--profile node` (framework server)
- `framework-api` → `--profile node --profile node-backend` (framework + API)

**Full Mode:** Always `--profile node --profile node-backend` (maximum coverage,
ignores NODE_MODE)

**Node.js API Coverage:**

By default (`NODE_MODE=assets-api`), ZAP scans both PHP and Node.js APIs:

- PHP Backend: `/`, `/api/*`, `/status`, `/ready`, `/health`
- Node Backend: `/api/node/*` (proxied via nginx to node-backend:3000)
- Frontend Framework: If `NODE_MODE=framework` or `framework-api`

#### CI/CD Scan Mode Selection

ZAP scans in CI/CD automatically select the appropriate mode based on the
repository:

**GitHub Actions & GitLab CI:**

| Repository             | Branch         | Trigger  | Mode       | Rationale                           |
| ---------------------- | -------------- | -------- | ---------- | ----------------------------------- |
| marcstraube/zappzarapp | develop/master | push     | **full**   | Boilerplate → test all services     |
| marcstraube/zappzarapp | develop/master | schedule | **full**   | Weekly comprehensive validation     |
| marcstraube/zappzarapp | feature/\*     | push     | normal     | Feature dev → .env config           |
| fork/custom-project    | any            | any      | **normal** | Projects use their configured stack |
| any                    | any            | manual   | selectable | Override via workflow input         |

**Benefits:**

- ✅ Boilerplate maintainers test all services comprehensively
- ✅ Fork projects only test their enabled stack (faster, relevant)
- ✅ No manual configuration needed (auto-detects repository)
- ✅ Manual override available via workflow dispatch

**Manual Override:**

```bash
# GitHub: Actions → ZAP Scan → Run workflow → Select "full" or "normal"

# GitLab: CI/CD → Run Pipeline → Add variable:
ZAP_SCAN_MODE=full  # or "normal"
```

#### Expected Scan Results

Some ZAP warnings are expected and acceptable in this configuration:

##### 90005 - Sec-Fetch-Dest Header is Missing

- **Status:** Expected (ZAP limitation, not a security issue)
- **Reason:** `Sec-Fetch-*` headers are client-side browser features sent by
  modern browsers, not server-controlled
- **Why it appears:** ZAP scans without a real browser and therefore doesn't
  send these headers
- **Action:** No action needed

##### 10049 - Storable and Cacheable Content

This warning should **not** appear for HTTP→HTTPS 301 redirects, as nginx
includes explicit `Cache-Control: public, max-age=31536000, immutable` headers.
301 redirects are intentionally cacheable for performance (RFC 7231).

**If this warning appears, investigate:**

- ❌ API endpoints (should never be cached without explicit intent)
- ❌ User-specific pages (profile, admin, dashboard)
- ❌ Pages with sensitive data

**Safe to ignore:**

- ✅ Static redirects (HTTP→HTTPS 301)
- ✅ Static resources (images, CSS, JS - already have cache headers)

#### When to Run ZAP Scans

| Scenario          | Command                  | Frequency     |
| ----------------- | ------------------------ | ------------- |
| Pre-push (local)  | ❌ Not recommended       | -             |
| Before PR review  | `make security-zap-full` | As needed     |
| After CSP changes | `make security-zap-scan` | After changes |
| CI on develop     | ✅ Automatic (GitHub)    | Every push    |
| Scheduled         | ✅ Automatic (GitHub)    | Weekly        |
| Pre-release       | `make security-zap-full` | Every release |

**Why not in pre-push?**

- Runtime: 5-10 minutes (too slow for pre-push)
- Requires production environment restart (disrupts workflow)
- Better suited for CI/CD automation
- Pre-push focuses on fast code-level checks

**Instead, pre-push runs:**

- Static security config scans (Trivy)
- CSP syntax validation
- Secret detection
- Fast SAST checks

#### Production ENV Propagation

**Why `compose.production.yaml` is used directly:**

Docker Compose reads `.env` by default and **overrides shell environment
variables**. This means `ENV=production make up` would still start containers
with `ENV=development` (from `.env` file).

**Solution:**

```makefile
# Direct docker compose invocation with production config
ENV=production docker compose -f compose.yaml -f compose.production.yaml up -d
```

**Verification:**

```bash
# Check ENV in PHP container (should show "production")
docker compose exec php printenv ENV

# Check CSP header (should NOT contain unsafe-eval or unsafe-inline)
curl -skI https://localhost:8443 | grep -i content-security-policy
```

**Critical for:**

- CSP headers are built using `zappzarapp/security` library with environment
  detection
- Production CSP: `CspDirectives::strict()` - nonce-based, no `unsafe-eval`, no
  `unsafe-inline`
- Development CSP: `CspDirectives::development()` - allows `unsafe-eval` for
  Vite HMR

### Custom Rules

Configure scan rules in `.zap/rules.tsv`:

```tsv
# Format: rule_id action reason
# Actions: IGNORE, WARN, FAIL
10055 WARN CSP findings tracked separately
```

---

## Compliance Notes

### GDPR Art. 32

Regular security scanning demonstrates:

- **Appropriate technical measures** to ensure security
- **Ongoing assessment** of processing security
- **Ability to detect** vulnerabilities before exploitation

### Documentation

Keep records of:

- Scan schedules and results
- Remediation actions taken
- Accepted risks (with justification)

---

## Related Documentation

- [Access Log Monitoring](./ACCESS-LOG-MONITORING.md) - Runtime security
  monitoring
- [Audit Logging Guide](./AUDIT-LOGGING.md) - Access tracking
- [Encryption Guide](./ENCRYPTION.md) - Data protection
