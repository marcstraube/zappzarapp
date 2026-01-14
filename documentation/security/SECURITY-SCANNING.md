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

| Platform | Main Pipeline              | Security Scan                         |
| -------- | -------------------------- | ------------------------------------- |
| GitHub   | `.github/workflows/ci.yml` | `.github/workflows/security-scan.yml` |
| GitLab   | `.gitlab-ci.yml`           | `.gitlab/security-scan.gitlab-ci.yml` |

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

## Optional: OWASP ZAP

For dynamic application security testing (DAST):

```yaml
# Add to security-scan.yml
dast-scan:
  name: OWASP ZAP Scan
  runs-on: ubuntu-latest
  steps:
    - name: Start application
      run: docker compose up -d

    - name: OWASP ZAP Baseline Scan
      uses: zaproxy/action-baseline@v0.10.0
      with:
        target: 'http://localhost:8080'
        rules_file_name: '.zap/rules.tsv'
        allow_issue_writing: false
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
