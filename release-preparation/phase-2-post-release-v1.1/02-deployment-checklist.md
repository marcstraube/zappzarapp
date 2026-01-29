# Task 02: Create Deployment Pre-Flight Checklist

## Priority

MEDIUM - Post-Release v1.1

## Estimated Effort

1-2 hours

## Context

During the Performance & Operations review, it was identified that while
deployment documentation exists, a structured pre-flight checklist would help
ensure production readiness. An executable script (`make deploy-check`) would
automate verification of common issues.

## Current State

- `DEPLOYMENT.md` exists with good information
- No automated pre-flight checks
- No single checklist document

## Target State

1. Create comprehensive deployment checklist document
2. Implement `make deploy-check` target that automates verification
3. Add checklist validation to CI for production builds

## Implementation Steps

### Step 1: Create Deployment Checklist Document

Create `.zappzarapp/docs/infrastructure/DEPLOYMENT-CHECKLIST.md`:

```markdown
# Production Deployment Checklist

Use this checklist before every production deployment. Automated checks are
available via `make deploy-check`.

## Pre-Deployment Checks

### 1. Environment Configuration

- [ ] `ENV=production` set
- [ ] `XDEBUG_MODE=off`
- [ ] `LOG_LEVEL=warning` or higher
- [ ] `LOG_FORMAT=json`
- [ ] `CORS_ORIGINS` set to specific domains (not `*`)

### 2. Security

- [ ] Docker secrets generated (`make secrets`)
- [ ] All passwords are unique and strong
- [ ] TLS certificates from trusted CA (not self-signed)
- [ ] Elasticsearch anonymous access disabled (if using ES)
- [ ] No sensitive data in environment variables
- [ ] `.env.local` not tracked in git

### 3. Services

- [ ] Required services enabled in `.env`
- [ ] Optional services disabled if not needed
- [ ] Database migrations applied
- [ ] Database backups configured

### 4. Build

- [ ] Production images built (`ENV=production make build`)
- [ ] All quality checks pass (`make check`)
- [ ] All tests pass (`make test`)
- [ ] No security vulnerabilities (`make audit`)

### 5. Infrastructure

- [ ] Resource limits defined (CPU, memory)
- [ ] Health checks enabled for all services
- [ ] Logging configured with rotation
- [ ] Monitoring/alerting set up

### 6. Networking

- [ ] Ports configured correctly
- [ ] Firewall rules in place
- [ ] Reverse proxy configured (if applicable)
- [ ] DNS records updated

### 7. Backup & Recovery

- [ ] Database backup verified
- [ ] Rollback procedure documented
- [ ] Recovery tested

## Post-Deployment Verification

- [ ] Health endpoint returns 200: `curl -f https://your-domain/health`
- [ ] All services healthy: `make check-health`
- [ ] No errors in logs: `make logs | grep -i error`
- [ ] Response times acceptable
- [ ] Monitoring confirms deployment

## Automated Checks

Run automated verification:

```bash
make deploy-check
```

This will verify:
- Environment configuration
- Security settings
- Build status
- Container health
```

### Step 2: Create Deploy Check Script

Create `scripts/deploy-check.sh`:

```bash
#!/bin/bash
set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PASSED=0
FAILED=0
WARNINGS=0

check_pass() {
    echo -e "${GREEN}✓${NC} $1"
    ((PASSED++))
}

check_fail() {
    echo -e "${RED}✗${NC} $1"
    ((FAILED++))
}

check_warn() {
    echo -e "${YELLOW}!${NC} $1"
    ((WARNINGS++))
}

echo "================================"
echo "Production Deployment Pre-Flight"
echo "================================"
echo ""

# 1. Environment Configuration
echo "1. Environment Configuration"
echo "----------------------------"

if grep -q "^ENV=production" .env 2>/dev/null || grep -q "^ENV=production" .env.local 2>/dev/null; then
    check_pass "ENV=production"
else
    check_fail "ENV should be 'production'"
fi

if grep -q "^XDEBUG_MODE=off" .env 2>/dev/null || grep -q "^XDEBUG_MODE=off" .env.local 2>/dev/null; then
    check_pass "XDEBUG_MODE=off"
else
    check_warn "XDEBUG_MODE should be 'off' in production"
fi

CORS=$(grep "^CORS_ORIGINS=" .env .env.local 2>/dev/null | tail -1 | cut -d= -f2)
if [ "$CORS" != "*" ] && [ -n "$CORS" ]; then
    check_pass "CORS_ORIGINS configured: $CORS"
else
    check_fail "CORS_ORIGINS should not be '*' in production"
fi

echo ""

# 2. Security
echo "2. Security Checks"
echo "------------------"

if [ -d "secrets" ] && [ "$(ls -A secrets 2>/dev/null)" ]; then
    check_pass "Secrets directory exists and is not empty"
else
    check_fail "Secrets not generated - run 'make secrets'"
fi

if [ -f "docker/certs/cert.crt" ] && [ -f "docker/certs/cert.key" ]; then
    CERT_DAYS=$(openssl x509 -in docker/certs/cert.crt -noout -enddate 2>/dev/null | cut -d= -f2)
    if [ -n "$CERT_DAYS" ]; then
        check_pass "TLS certificates exist (expires: $CERT_DAYS)"
    else
        check_warn "TLS certificates exist but couldn't read expiry"
    fi
else
    check_fail "TLS certificates not found"
fi

if git ls-files --error-unmatch .env.local >/dev/null 2>&1; then
    check_fail ".env.local is tracked in git (security risk)"
else
    check_pass ".env.local not tracked in git"
fi

echo ""

# 3. Build Status
echo "3. Build Status"
echo "---------------"

if docker images | grep -q "zappzarapp-php"; then
    check_pass "PHP image exists"
else
    check_fail "PHP image not built"
fi

if docker images | grep -q "zappzarapp-nginx"; then
    check_pass "Nginx image exists"
else
    check_fail "Nginx image not built"
fi

if docker images | grep -q "zappzarapp-node"; then
    check_pass "Node image exists"
else
    check_warn "Node image not built (may not be required)"
fi

echo ""

# 4. Container Health (if running)
echo "4. Container Health"
echo "-------------------"

if docker compose ps --quiet 2>/dev/null | grep -q .; then
    UNHEALTHY=$(docker compose ps --format json 2>/dev/null | jq -r 'select(.Health == "unhealthy") | .Name' 2>/dev/null)
    if [ -z "$UNHEALTHY" ]; then
        check_pass "All running containers healthy"
    else
        check_fail "Unhealthy containers: $UNHEALTHY"
    fi
else
    check_warn "No containers running (run 'make up' to start)"
fi

echo ""

# 5. Quality Checks
echo "5. Quality Checks"
echo "-----------------"

if [ -f "build/coverage/clover.xml" ]; then
    check_pass "Test coverage report exists"
else
    check_warn "No coverage report - run 'make test-coverage'"
fi

echo ""

# Summary
echo "================================"
echo "Summary"
echo "================================"
echo -e "Passed:   ${GREEN}$PASSED${NC}"
echo -e "Failed:   ${RED}$FAILED${NC}"
echo -e "Warnings: ${YELLOW}$WARNINGS${NC}"
echo ""

if [ $FAILED -gt 0 ]; then
    echo -e "${RED}Pre-flight check FAILED${NC}"
    echo "Fix the issues above before deploying."
    exit 1
elif [ $WARNINGS -gt 0 ]; then
    echo -e "${YELLOW}Pre-flight check PASSED with warnings${NC}"
    echo "Review warnings before deploying."
    exit 0
else
    echo -e "${GREEN}Pre-flight check PASSED${NC}"
    echo "Ready for deployment!"
    exit 0
fi
```

### Step 3: Add Makefile Target

Add to `Makefile`:

```makefile
##@ Deployment

.PHONY: deploy-check
deploy-check: ## Run pre-deployment checks
	@chmod +x scripts/deploy-check.sh
	@./scripts/deploy-check.sh
```

### Step 4: Add CI Integration (Optional)

Add to `.github/workflows/ci.yml` (in build-production job):

```yaml
- name: Run deployment checks
  run: make deploy-check
  continue-on-error: true
```

## Verification

1. **Script is executable:**

   ```bash
   test -x scripts/deploy-check.sh && echo "Script is executable"
   ```

2. **Make target works:**

   ```bash
   make deploy-check
   ```

3. **Documentation exists:**

   ```bash
   test -f .zappzarapp/docs/infrastructure/DEPLOYMENT-CHECKLIST.md
   ```

## Files to Create/Modify

1. `.zappzarapp/docs/infrastructure/DEPLOYMENT-CHECKLIST.md` - New document
2. `scripts/deploy-check.sh` - New script
3. `Makefile` - Add `deploy-check` target
4. `.github/workflows/ci.yml` - Optional CI integration

## Notes

- Script should be non-destructive (read-only checks)
- Exit codes: 0 = pass, 1 = fail with errors
- Warnings don't cause failure but are reported
- Can be extended with additional checks over time
