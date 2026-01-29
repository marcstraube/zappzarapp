# Task 07: CI Security Enhancements (SAST/Dockerfile Linting)

## Priority

MEDIUM - Pre-Release (Security Hardening)

## Estimated Effort

30-45 minutes

## Context

The CI already has comprehensive security scanning (Trivy, TruffleHog, dependency
audits). Additional tooling can provide deeper static analysis and Dockerfile
best practice enforcement.

**Current state:** PHPStan and ESLint now include security plugins
(`ekino/phpstan-banned-code`, `eslint-plugin-security`) that run automatically
in CI via `make analyse` and `pnpm run lint`.

## Current Security Coverage

### Already Implemented

| Category | Tool | Runs In |
|----------|------|---------|
| Container CVEs | Trivy | security-scan.yml (weekly) |
| Dependency CVEs | composer/pnpm audit | ci.yml + security-scan.yml |
| Secret Detection | Trivy + TruffleHog | security-scan.yml |
| IaC Misconfiguration | Trivy config | security-scan.yml |
| PHP Dangerous Functions | PHPStan (ekino/phpstan-banned-code) | ci.yml |
| Node Dangerous Patterns | ESLint (eslint-plugin-security) | ci.yml |
| Pre-commit Secrets | CaptainHook BlockSecrets | Local (pre-commit) |

### Potential Additions

| Category | Tool | Benefit | Priority |
|----------|------|---------|----------|
| SAST | Semgrep | Dataflow analysis, custom rules | Medium |
| DAST | OWASP ZAP | Runtime vulnerability scanning | Medium |
| Dockerfile Lint | Dockle | Best practices, CIS benchmarks | Low |
| License Scan | Trivy license | License compliance | Low |

## Target State

Add Semgrep for deeper SAST analysis and Dockle for Dockerfile best practices.

## Implementation Steps

### Step 1: Add Semgrep to CI

Edit `.github/workflows/ci.yml` or create new job:

```yaml
# Add to ci.yml after dependency-audit job
sast-scan:
  name: SAST Analysis (Semgrep)
  runs-on: ubuntu-latest
  timeout-minutes: 10

  steps:
    - name: Checkout code
      uses: actions/checkout@v4

    - name: Run Semgrep
      uses: returntocorp/semgrep-action@v1
      with:
        config: >-
          p/security-audit
          p/secrets
          p/php
          p/typescript
```

### Step 2: Add Dockle to Security Scan

Edit `.github/workflows/security-scan.yml`:

```yaml
# Add new job after image-scan
dockerfile-lint:
  name: Dockerfile Best Practices
  runs-on: ubuntu-latest
  timeout-minutes: 15

  strategy:
    fail-fast: false
    matrix:
      image:
        - name: php
          dockerfile: docker/php/Dockerfile
        - name: node
          dockerfile: docker/node/Dockerfile
        - name: nginx
          dockerfile: docker/nginx/Dockerfile
        # ... other Dockerfiles

  steps:
    - name: Checkout code
      uses: actions/checkout@v4

    - name: Build image
      run: |
        DOCKER_BUILDKIT=0 docker build -t dockle-${{ matrix.image.name }}:test \
          -f ${{ matrix.image.dockerfile }} .

    - name: Run Dockle
      uses: erzz/dockle-action@v1
      with:
        image: dockle-${{ matrix.image.name }}:test
        failure-threshold: high
        accept-keys: USER
```

### Step 3: Add DAST with OWASP ZAP

DAST requires a running application. Best suited for integration test phase.

Edit `.github/workflows/ci.yml` after `bats-integration` job:

```yaml
dast-scan:
  name: DAST Analysis (OWASP ZAP)
  runs-on: ubuntu-latest
  timeout-minutes: 30
  needs: [bats-integration]
  if: github.event_name == 'push' && github.ref == 'refs/heads/develop'

  steps:
    - name: Checkout code
      uses: actions/checkout@v4

    - name: Set up Docker Buildx
      uses: docker/setup-buildx-action@v3

    - name: Initialize and start application
      run: |
        make init
        make secrets
        make ssl-internal
        # Create lockfiles
        [ -f composer.lock ] || echo '{}' > composer.lock
        [ -f pnpm-lock.yaml ] || touch pnpm-lock.yaml
        # Build and start
        DOCKER_BUILDKIT=0 docker compose build php node nginx
        docker compose up -d php node nginx postgres
        sleep 10
        # Install dependencies
        docker compose exec -T php composer install --no-progress
        docker compose exec -T node pnpm install

    - name: Wait for healthy application
      run: |
        timeout 120 sh -c 'until curl -sf http://localhost:8080/health; do sleep 5; done'

    - name: OWASP ZAP Baseline Scan
      uses: zaproxy/action-baseline@v0.14.0
      with:
        target: 'http://localhost:8080'
        rules_file_name: '.zap/rules.tsv'
        cmd_options: '-a -j'
        allow_issue_writing: false

    - name: Upload ZAP Report
      uses: actions/upload-artifact@v4
      if: always()
      with:
        name: zap-report
        path: report_html.html
        retention-days: 30

    - name: Cleanup
      if: always()
      uses: ./.github/actions/docker-cleanup
```

Create `.zap/rules.tsv` for customizing alerts:

```tsv
# Rule ID	Action	Description
10021	IGNORE	X-Content-Type-Options Header Missing (handled by nginx in prod)
10038	IGNORE	Content Security Policy Header Not Set (configured in prod)
```

**Alternative: ZAP Full Scan (longer, more thorough)**

```yaml
- name: OWASP ZAP Full Scan
  uses: zaproxy/action-full-scan@v0.12.0
  with:
    target: 'http://localhost:8080'
    rules_file_name: '.zap/rules.tsv'
```

### Step 4: Add License Scanning (Optional)

```yaml
# Add to security-scan.yml
license-scan:
  name: License Compliance
  runs-on: ubuntu-latest
  timeout-minutes: 10

  steps:
    - name: Checkout code
      uses: actions/checkout@v4

    - name: Trivy license scan
      uses: aquasecurity/trivy-action@master
      with:
        scan-type: fs
        scan-ref: .
        scanners: license
        format: table
```

### Step 4: Update Summary Job

Add new jobs to the `needs` array in the summary job.

## Verification

1. **Semgrep runs:**
   ```bash
   # Local test
   docker run --rm -v "${PWD}:/src" returntocorp/semgrep \
     semgrep --config p/security-audit /src
   ```

2. **ZAP runs:**
   ```bash
   # Local test (requires running application)
   make up
   docker run --rm --network host ghcr.io/zaproxy/zaproxy:stable \
     zap-baseline.py -t http://localhost:8080 -J report.json
   ```

3. **Dockle runs:**
   ```bash
   # Local test
   docker run --rm -v /var/run/docker.sock:/var/run/docker.sock \
     goodwithtech/dockle:latest zappzarapp-php:latest
   ```

4. **CI passes:** Check GitHub Actions for successful completion

## Files to Modify

| File | Change |
|------|--------|
| `.github/workflows/ci.yml` | Add Semgrep SAST + ZAP DAST jobs |
| `.github/workflows/security-scan.yml` | Add Dockle + License jobs |
| `.zap/rules.tsv` | ZAP alert customization (new file) |

## Notes

- Semgrep free tier has generous limits for open source
- Dockle may flag issues that are intentional (e.g., running as root in dev)
- Consider adding `.dockle.yml` for ignore rules
- License scanning is informational, not blocking
- These are enhancements, not blockers for v1.0 release

## Decision Required

Before implementing, decide:

1. **Semgrep:** Add to every PR or only weekly?
2. **ZAP:** Baseline scan (fast, passive) or Full scan (slow, active)?
3. **ZAP:** Run on every develop push or only weekly/manual?
4. **Dockle:** Which images to scan (all 12 or core 6)?
5. **License:** Blocking or informational only?

## Recommended Approach

| Tool | When | Blocking |
|------|------|----------|
| Semgrep | Every PR | Yes (errors only) |
| ZAP Baseline | Weekly + develop push | No (report only) |
| ZAP Full | Manual trigger | No (report only) |
| Dockle | Weekly (with security-scan) | No (report only) |
| License | Weekly | No (informational) |
